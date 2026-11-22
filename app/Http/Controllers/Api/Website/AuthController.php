<?php

namespace App\Http\Controllers\Api\Website;

use App\Exceptions\SocialIdentityVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Website\Auth\ResetPasswordRequest;
use App\Http\Requests\Website\Auth\SendOtpRequest;
use App\Http\Requests\Website\Auth\VerifyOtpRequest;
use App\Http\Requests\Website\ChangePasswordRequest;
use App\Http\Requests\Website\LoginRequest;
use App\Http\Requests\Website\RegisterRequest;
use App\Http\Requests\Website\SocialMediaLoginRequest;
use App\Http\Resources\Website\UserResource;
use App\Mail\Website\OtpMail;
use App\Models\OtpVerification;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => $request->validated('password'),
                'is_active' => true,
            ]);

            $user->forceFill(['last_login_at' => now()])->save();

            return $this->authSuccess($user, 'تم إنشاء الحساب بنجاح');
        } catch (\Throwable $e) {
            Log::error('User registration failed', ['exception' => $e]);
            return $this->error('حدث خطأ أثناء إنشاء الحساب', 500);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($request->validated('email'))])->first();

            if (!$user || !$user->password || !Hash::check($request->validated('password'), $user->password)) {
                return $this->error('بيانات الدخول غير صحيحة', 401);
            }

            if ($user->is_active === false || $user->deactivated_at !== null) {
                return $this->error('الحساب غير متاح حاليًا', 403);
            }

            $user->forceFill(['last_login_at' => now()])->save();
            return $this->authSuccess($user, 'تم تسجيل الدخول بنجاح');
        } catch (\Throwable $e) {
            Log::error('User login failed', ['email' => $request->input('email'), 'exception' => $e]);
            return $this->error('حدث خطأ أثناء تسجيل الدخول', 500);
        }
    }

    public function socialLogin(SocialMediaLoginRequest $request, SocialIdentityVerifier $verifier)
    {
        try {
            $identity = $verifier->verify($request->validated());

            $user = DB::transaction(function () use ($identity, $request) {
                $provider = $identity['provider'];
                $aliases = array_values(array_unique(array_filter(array_merge(
                    [$identity['provider_user_id']],
                    $identity['provider_aliases'] ?? []
                ))));

                $social = SocialIdentity::where('provider', $provider)
                    ->whereIn('provider_user_id', $aliases)
                    ->lockForUpdate()
                    ->first();

                $user = $social?->user;

                // Backward compatibility with the legacy provider id columns.
                if (!$user) {
                    $legacyColumn = $provider . '_id';
                    $user = User::whereIn($legacyColumn, $aliases)->lockForUpdate()->first();
                }

                // An existing local account may only be linked by a provider-verified email.
                if (!$user && !empty($identity['email']) && !empty($identity['email_verified'])) {
                    $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($identity['email'])])
                        ->lockForUpdate()
                        ->first();
                }

                if (!$user) {
                    if (empty($identity['email']) || empty($identity['email_verified'])) {
                        throw new SocialIdentityVerificationException('تعذر إنشاء الحساب لأن مزود الدخول لم يرسل بريدًا إلكترونيًا موثقًا.');
                    }

                    $user = User::create([
                        'name' => $identity['name'] ?: ($request->input('name') ?: 'User'),
                        'email' => mb_strtolower($identity['email']),
                        'password' => Hash::make(Str::random(48)),
                        'is_active' => true,
                    ]);
                }

                if ($user->is_active === false || $user->deactivated_at !== null) {
                    throw new SocialIdentityVerificationException('الحساب غير متاح حاليًا.');
                }

                $existingForProvider = SocialIdentity::where('provider', $provider)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingForProvider && !in_array($existingForProvider->provider_user_id, $aliases, true)) {
                    throw new SocialIdentityVerificationException('الحساب مرتبط بهوية مختلفة لدى مزود تسجيل الدخول.');
                }

                SocialIdentity::updateOrCreate(
                    ['provider' => $provider, 'provider_user_id' => $identity['provider_user_id']],
                    [
                        'user_id' => $user->id,
                        'email' => $identity['email'],
                        'metadata' => $identity['metadata'] ?? [],
                        'last_verified_at' => now(),
                    ]
                );

                // Keep legacy columns synchronized so old code/data continues to work.
                $legacyColumn = $provider . '_id';
                if (empty($user->{$legacyColumn}) || in_array((string) $user->{$legacyColumn}, $aliases, true)) {
                    $user->{$legacyColumn} = $identity['provider_user_id'];
                }

                if (!$user->email_verified_at && !empty($identity['email_verified'])) {
                    $user->email_verified_at = now();
                }
                if (!$user->name && !empty($identity['name'])) {
                    $user->name = $identity['name'];
                }
                if (!$user->image && $this->isSafeSocialImage($identity['image'] ?? null, $provider)) {
                    $user->image = $this->saveSocialImageToServer($identity['image'], $provider);
                }
                $user->last_login_at = now();
                $user->save();

                return $user->fresh();
            }, 3);

            return $this->authSuccess($user, 'تم تسجيل الدخول بنجاح عبر ' . ucfirst($request->provider));
        } catch (SocialIdentityVerificationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Social login failed', ['provider' => $request->input('provider'), 'exception' => $e]);
            return $this->error('تعذر إتمام تسجيل الدخول الاجتماعي', 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return $this->success(null, 'تم تسجيل الخروج بنجاح');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return $this->success(null, 'تم تسجيل الخروج من جميع الأجهزة بنجاح');
    }

    public function profile(Request $request)
    {
        return $this->success(new UserResource($request->user()), 'تم جلب بيانات المستخدم');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $oldImage = $user->image;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('users', 'public');
        }

        $user->update($validated);

        if ($request->hasFile('image') && $oldImage && !Str::startsWith($oldImage, ['http://', 'https://'])) {
            Storage::disk('public')->delete($oldImage);
        }

        return $this->success(['user' => new UserResource($user->fresh())], 'تم تحديث الملف الشخصي بنجاح');
    }

    public function sendOtp(SendOtpRequest $request)
    {
        $email = mb_strtolower($request->validated('email'));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // Deliberately return the same response whether or not the address exists.
        if (!$user || $user->is_active === false || $user->deactivated_at !== null) {
            return $this->success(null, 'إذا كان البريد مسجلًا فسيتم إرسال رمز التحقق إليه');
        }

        try {
            OtpVerification::where('email', $email)->where('purpose', 'password_reset')->delete();

            $otp = (string) random_int(100000, 999999);
            OtpVerification::create([
                'email' => $email,
                'otp' => Hash::make($otp),
                'purpose' => 'password_reset',
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) env('OTP_EXPIRATION_MINUTES', 5)),
            ]);

            Mail::to($email)->send(new OtpMail($otp));
            return $this->success(null, 'إذا كان البريد مسجلًا فسيتم إرسال رمز التحقق إليه');
        } catch (\Throwable $e) {
            Log::error('OTP send failed', ['email' => $email, 'exception' => $e]);
            return $this->error('تعذر إرسال رمز التحقق حاليًا', 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $result = $this->validateOtp($request->validated('email'), $request->validated('otp'), false);
        if ($result !== true) {
            return $result;
        }

        OtpVerification::where('email', mb_strtolower($request->validated('email')))
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->latest('id')
            ->first()?->update(['verified_at' => now()]);

        return $this->success(['email' => mb_strtolower($request->validated('email'))], 'تم التحقق من رمز OTP بنجاح');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $email = mb_strtolower($request->validated('email'));
        $validation = $this->validateOtp($email, $request->validated('otp'), true);
        if ($validation !== true) {
            return $validation;
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user || $user->is_active === false || $user->deactivated_at !== null) {
            return $this->error('تعذر إعادة تعيين كلمة المرور بهذه البيانات', 422);
        }

        DB::transaction(function () use ($user, $email, $request) {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->delete();
            OtpVerification::where('email', $email)->where('purpose', 'password_reset')->update([
                'used_at' => now(),
                'verified_at' => now(),
            ]);
        });

        return $this->authSuccess($user->fresh(), 'تم إعادة تعيين كلمة المرور بنجاح');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        if (!$user->password || !Hash::check($request->validated('old_password'), $user->password)) {
            return $this->error('كلمة المرور الحالية غير صحيحة', 422);
        }
        if (Hash::check($request->validated('new_password'), $user->password)) {
            return $this->error('لا يمكن استخدام نفس كلمة المرور القديمة', 422);
        }

        $user->update(['password' => $request->validated('new_password')]);
        $user->tokens()->delete();

        return $this->authSuccess($user->fresh(), 'تم تغيير كلمة المرور بنجاح');
    }

    public function deactivateAccount(Request $request)
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (!$user->password || !Hash::check($validated['password'], $user->password)) {
            return $this->error('كلمة المرور غير صحيحة', 422);
        }

        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
        $user->tokens()->delete();

        return $this->success(null, 'تم تعطيل الحساب بنجاح');
    }

    private function validateOtp(string $email, string $plainOtp, bool $forReset)
    {
        $email = mb_strtolower($email);
        $record = OtpVerification::where('email', $email)
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (!$record || $record->isExpired()) {
            return $this->error('رمز OTP غير صالح أو منتهي الصلاحية', 422);
        }

        $maxAttempts = (int) env('OTP_MAX_ATTEMPTS', 5);
        if ((int) $record->attempts >= $maxAttempts) {
            $record->update(['used_at' => now()]);
            return $this->error('تم تجاوز عدد المحاولات المسموح بها، اطلب رمزًا جديدًا', 429);
        }

        if (!Hash::check($plainOtp, $record->otp)) {
            $record->increment('attempts');
            return $this->error('رمز OTP غير صحيح', 422);
        }

        if ($forReset && $record->verified_at === null) {
            $record->update(['verified_at' => now()]);
        }

        return true;
    }

    private function authSuccess(User $user, string $message)
    {
        $token = $user->createToken('api_token')->plainTextToken;
        return $this->success(['user' => new UserResource($user), 'token' => $token], $message);
    }

    private function isSafeSocialImage(?string $url, string $provider): bool
    {
        return app(SocialIdentityVerifier::class)->isTrustedImageUrl($url, $provider);
    }

    private function saveSocialImageToServer(?string $imageUrl, string $provider): ?string
    {
        if (!$this->isSafeSocialImage($imageUrl, $provider)) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withOptions(['allow_redirects' => false])
                ->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($extensions[$contentType]) || strlen($response->body()) > 5 * 1024 * 1024) {
                return null;
            }

            $fileName = 'users/social_' . Str::uuid() . '.' . $extensions[$contentType];
            Storage::disk('public')->put($fileName, $response->body());
            return $fileName;
        } catch (\Throwable $e) {
            Log::warning('Social profile image download failed', ['provider' => $provider, 'host' => parse_url((string) $imageUrl, PHP_URL_HOST)]);
            return null;
        }
    }
}
