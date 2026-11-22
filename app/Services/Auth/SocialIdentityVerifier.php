<?php

namespace App\Services\Auth;

use App\Exceptions\SocialIdentityVerificationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialIdentityVerifier
{
    /**
     * Verify identity with the provider. Never trusts provider_id/email from the browser.
     *
     * @return array{provider:string,provider_user_id:string,provider_aliases:array,email:?string,email_verified:bool,name:?string,image:?string,metadata:array}
     */
    public function verify(array $payload): array
    {
        return match ($payload['provider'] ?? null) {
            'google' => $this->verifyGoogle($payload),
            'facebook' => $this->verifyFacebook($payload),
            'apple' => $this->verifyApple($payload),
            default => throw new SocialIdentityVerificationException('مزود تسجيل الدخول غير مدعوم.'),
        };
    }

    private function verifyGoogle(array $payload): array
    {
        $idToken = (string) ($payload['id_token'] ?? '');
        if ($idToken === '') {
            throw new SocialIdentityVerificationException('Google ID token مطلوب.');
        }

        if (($payload['auth_source'] ?? null) === 'firebase') {
            $apiKey = (string) config('services.firebase.web_api_key');
            if ($apiKey === '') {
                throw new SocialIdentityVerificationException('Firebase verification غير مُعد على الخادم.');
            }

            $response = Http::asJson()
                ->timeout(10)
                ->retry(2, 200)
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey), [
                    'idToken' => $idToken,
                ]);

            if (!$response->successful()) {
                throw new SocialIdentityVerificationException('تعذر التحقق من حساب Google.');
            }

            $account = $response->json('users.0');
            if (!is_array($account) || empty($account['localId']) || !empty($account['disabled'])) {
                throw new SocialIdentityVerificationException('بيانات حساب Google غير صالحة.');
            }

            $providers = collect($account['providerUserInfo'] ?? []);
            $googleProvider = $providers->first(fn ($item) => ($item['providerId'] ?? null) === 'google.com');
            if (!$googleProvider) {
                throw new SocialIdentityVerificationException('رمز Firebase لا يمثل حساب Google.');
            }

            $aliases = array_values(array_unique(array_filter([
                (string) $account['localId'],
                (string) ($googleProvider['rawId'] ?? ''),
                (string) ($googleProvider['federatedId'] ?? ''),
            ])));

            return [
                'provider' => 'google',
                'provider_user_id' => (string) $account['localId'],
                'provider_aliases' => $aliases,
                'email' => $account['email'] ?? ($googleProvider['email'] ?? null),
                'email_verified' => (bool) ($account['emailVerified'] ?? false),
                'name' => $account['displayName'] ?? ($googleProvider['displayName'] ?? null),
                'image' => $account['photoUrl'] ?? ($googleProvider['photoUrl'] ?? null),
                'metadata' => [
                    'source' => 'firebase',
                    'provider_id' => $googleProvider['rawId'] ?? null,
                ],
            ];
        }

        $response = Http::timeout(10)
            ->retry(2, 200)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if (!$response->successful()) {
            throw new SocialIdentityVerificationException('تعذر التحقق من حساب Google.');
        }

        $claims = $response->json();
        $clientId = (string) config('services.google.client_id');
        if ($clientId !== '' && ($claims['aud'] ?? null) !== $clientId) {
            throw new SocialIdentityVerificationException('Google token audience غير صحيح.');
        }
        if (empty($claims['sub'])) {
            throw new SocialIdentityVerificationException('Google token لا يحتوي هوية مستخدم صالحة.');
        }

        $jwtClaims = $this->decodeJwtPayload($idToken);

        return [
            'provider' => 'google',
            'provider_user_id' => (string) $claims['sub'],
            'provider_aliases' => [(string) $claims['sub']],
            'email' => $claims['email'] ?? ($jwtClaims['email'] ?? null),
            'email_verified' => filter_var($claims['email_verified'] ?? ($jwtClaims['email_verified'] ?? false), FILTER_VALIDATE_BOOLEAN),
            'name' => $jwtClaims['name'] ?? null,
            'image' => $jwtClaims['picture'] ?? null,
            'metadata' => ['source' => 'google_oidc'],
        ];
    }

    private function verifyFacebook(array $payload): array
    {
        $accessToken = (string) ($payload['access_token'] ?? '');
        $appId = (string) config('services.facebook.app_id');
        $appSecret = (string) config('services.facebook.app_secret');
        $version = (string) config('services.facebook.graph_version', 'v23.0');

        if ($accessToken === '' || $appId === '' || $appSecret === '') {
            throw new SocialIdentityVerificationException('Facebook verification غير مُعد أو رمز الدخول مفقود.');
        }

        $debug = Http::timeout(10)->retry(2, 200)->get("https://graph.facebook.com/{$version}/debug_token", [
            'input_token' => $accessToken,
            'access_token' => $appId . '|' . $appSecret,
        ]);

        $data = $debug->json('data');
        if (!$debug->successful() || !is_array($data) || empty($data['is_valid']) || (string) ($data['app_id'] ?? '') !== $appId) {
            throw new SocialIdentityVerificationException('Facebook access token غير صالح.');
        }

        $me = Http::timeout(10)->retry(2, 200)->get("https://graph.facebook.com/{$version}/me", [
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if (!$me->successful() || !$me->json('id')) {
            throw new SocialIdentityVerificationException('تعذر قراءة بيانات حساب Facebook.');
        }

        return [
            'provider' => 'facebook',
            'provider_user_id' => (string) $me->json('id'),
            'provider_aliases' => [(string) $me->json('id')],
            'email' => $me->json('email'),
            // Facebook's /me endpoint only returns an email granted to this app; treat it as provider asserted.
            'email_verified' => (bool) $me->json('email'),
            'name' => $me->json('name'),
            'image' => $me->json('picture.data.url'),
            'metadata' => ['source' => 'facebook_graph'],
        ];
    }

    private function verifyApple(array $payload): array
    {
        $code = (string) ($payload['authorization_code'] ?? '');
        $clientId = (string) config('services.apple.client_id');
        $clientSecret = (string) config('services.apple.client_secret');

        if ($code === '' || $clientId === '' || $clientSecret === '') {
            throw new SocialIdentityVerificationException('Apple authorization code أو إعدادات الخادم مفقودة.');
        }

        $response = Http::asForm()->timeout(10)->retry(2, 200)->post('https://appleid.apple.com/auth/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.apple.redirect_uri'),
        ]);

        if (!$response->successful() || !$response->json('id_token')) {
            throw new SocialIdentityVerificationException('تعذر التحقق من حساب Apple.');
        }

        // The ID token is returned by Apple's token endpoint after validating the one-time code/client secret.
        // We still validate its critical claims before accepting the identity.
        $claims = $this->decodeJwtPayload((string) $response->json('id_token'));
        $audience = $claims['aud'] ?? null;
        $audienceValid = is_array($audience) ? in_array($clientId, $audience, true) : $audience === $clientId;
        if (($claims['iss'] ?? null) !== 'https://appleid.apple.com' || !$audienceValid || empty($claims['sub']) || (int) ($claims['exp'] ?? 0) <= time()) {
            throw new SocialIdentityVerificationException('Apple identity token غير صالح.');
        }

        return [
            'provider' => 'apple',
            'provider_user_id' => (string) $claims['sub'],
            'provider_aliases' => [(string) $claims['sub']],
            'email' => $claims['email'] ?? null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => $payload['name'] ?? null, // Apple returns name only on first authorization; identity is already verified by code.
            'image' => null,
            'metadata' => [
                'source' => 'apple_token_endpoint',
                'is_private_email' => filter_var($claims['is_private_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new SocialIdentityVerificationException('Identity token format غير صالح.');
        }
        $decoded = $this->base64UrlDecode($parts[1]);
        $claims = json_decode($decoded, true);
        if (!is_array($claims)) {
            throw new SocialIdentityVerificationException('Identity token payload غير صالح.');
        }
        return $claims;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new SocialIdentityVerificationException('Identity token encoding غير صالح.');
        }
        return $decoded;
    }

    public function isTrustedImageUrl(?string $url, string $provider): bool
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = Str::lower($parts['host']);
        $allowed = match ($provider) {
            'google' => ['googleusercontent.com', 'ggpht.com'],
            'facebook' => ['facebook.com', 'fbcdn.net', 'fbsbx.com'],
            'apple' => ['apple.com', 'icloud.com'],
            default => [],
        };
        foreach ($allowed as $domain) {
            if ($host === $domain || Str::endsWith($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }
}
