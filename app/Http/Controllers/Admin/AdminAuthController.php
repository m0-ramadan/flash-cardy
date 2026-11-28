<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\Admin\Auth\SendOtpRequest;
use App\Mail\AdminPasswordResetMail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminAuthController extends Controller
{
    public function loginPage()
    {
        return auth('admin')->check() ? redirect('/') : view('Admin.auth.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $admin = Admin::whereRaw('LOWER(email) = ?', [$request->validated('email')])->first();
        if (!$admin || $admin->is_active === false || !Hash::check($request->validated('password'), $admin->password)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'بيانات الدخول غير صحيحة']);
        }

        Auth::guard('admin')->login($admin, (bool) $request->validated('remember'));
        $request->session()->regenerate();
        $request->session()->put('admin_last_activity', time());
        $admin->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login.page');
    }

    public function showForgotPasswordForm()
    {
        return view('Admin.auth.forgot-password');
    }

    public function sendResetOtp(SendOtpRequest $request)
    {
        $email = $request->validated('email');
        $admin = Admin::whereRaw('LOWER(email) = ?', [$email])->where('is_active', true)->first();

        // Generic response prevents account enumeration.
        if (!$admin) {
            return back()->with('status', 'إذا كان البريد مسجلًا ونشطًا فسيصله رابط إعادة التعيين.');
        }

        try {
            $plainToken = Str::random(64);
            DB::table('admin_password_reset_tokens')->updateOrInsert(
                ['email' => $admin->email],
                ['token' => Hash::make($plainToken), 'created_at' => now()]
            );

            $url = route('admin.password.reset', ['token' => $plainToken, 'email' => $admin->email]);
            Mail::to($admin->email)->send(new AdminPasswordResetMail($url));
            return back()->with('status', 'إذا كان البريد مسجلًا ونشطًا فسيصله رابط إعادة التعيين.');
        } catch (\Throwable $e) {
            Log::error('Admin password reset mail failed', ['admin_id' => $admin->id, 'exception' => $e]);
            return back()->withErrors(['email' => 'تعذر إرسال رابط إعادة التعيين حاليًا.']);
        }
    }

    public function showResetPasswordForm(string $token, Request $request)
    {
        return view('Admin.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $record = DB::table('admin_password_reset_tokens')->where('email', mb_strtolower($validated['email']))->first();
        $expires = now()->subMinutes((int) config('auth.passwords.admins.expire', 30));

        if (!$record || !$record->created_at || $record->created_at < $expires || !Hash::check($validated['token'], $record->token)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو منتهي.']);
        }

        $admin = Admin::whereRaw('LOWER(email) = ?', [mb_strtolower($validated['email'])])->where('is_active', true)->first();
        if (!$admin) {
            return back()->withErrors(['email' => 'تعذر إعادة تعيين كلمة المرور.']);
        }

        DB::transaction(function () use ($admin, $validated) {
            $admin->forceFill(['password' => $validated['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('admin_password_reset_tokens')->where('email', $admin->email)->delete();
        });

        return redirect()->route('admin.login.page')->with('status', 'تم تغيير كلمة المرور بنجاح.');
    }

    public function home()
    {
        return view('Admin.index', DashboardController::getDashboardData());
    }
}
