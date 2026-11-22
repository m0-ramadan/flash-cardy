<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\ImportantModel;
use App\Models\Language;
use App\Models\Setting;
use App\Services\IpInfoService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, IpInfoService $ipInfoService)
    {
        $ip = $request->ip();
        $ipInfo = $ipInfoService->getCountryAndCurrency($ip);
        $country = $ipInfo['country'] ?? null;

        $countryLocales = [
            // Arabic (RTL)
            'Egypt' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Saudi Arabia' => ['lang' => 'ar', 'dir' => 'rtl'],
            'United Arab Emirates' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Kuwait' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Qatar' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Bahrain' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Oman' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Jordan' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Palestine' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Iraq' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Syria' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Lebanon' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Yemen' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Sudan' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Libya' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Morocco' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Algeria' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Tunisia' => ['lang' => 'ar', 'dir' => 'rtl'],
            'Mauritania' => ['lang' => 'ar', 'dir' => 'rtl'],

            // English (LTR)
            'United States' => ['lang' => 'en', 'dir' => 'ltr'],
            'United Kingdom' => ['lang' => 'en', 'dir' => 'ltr'],
            'Canada' => ['lang' => 'en', 'dir' => 'ltr'],
            'Australia' => ['lang' => 'en', 'dir' => 'ltr'],

            // French
            'France' => ['lang' => 'fr', 'dir' => 'ltr'],
            'Belgium' => ['lang' => 'fr', 'dir' => 'ltr'],
            'Switzerland' => ['lang' => 'fr', 'dir' => 'ltr'],

            // German
            'Germany' => ['lang' => 'de', 'dir' => 'ltr'],

            // Spanish
            'Spain' => ['lang' => 'es', 'dir' => 'ltr'],
            'Mexico' => ['lang' => 'es', 'dir' => 'ltr'],

            // Turkish
            'Turkey' => ['lang' => 'tr', 'dir' => 'ltr'],
        ];

        $supported = Language::pluck('code')
            ->filter()
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->unique()
            ->values()
            ->toArray();

        $requestedLang = strtolower(trim((string) (
            $request->input('lang')
            ?? $request->query('lang')
            ?? $request->header('lang')
            ?? ''
        )));

        $acceptLang = strtolower((string) $request->header('Accept-Language', ''));
        $preferredFromHeader = null;

        if ($acceptLang !== '') {
            foreach (explode(',', $acceptLang) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $code = trim(strtok($part, ';') ?: '');
                $primary = strtolower(trim(strtok($code, '-') ?: $code));

                if ($primary !== '' && in_array($primary, $supported, true)) {
                    $preferredFromHeader = $primary;
                    break;
                }
            }
        }

        $ipLocale = $country !== null ? ($countryLocales[$country]['lang'] ?? null) : null;

        $defaultLang = strtolower((string) \setting('site_language', 'en'));
        $finalLang =
            (in_array($requestedLang, $supported, true) ? $requestedLang : null)
            ?? $preferredFromHeader
            ?? (in_array(strtolower((string) $ipLocale), $supported, true) ? strtolower((string) $ipLocale) : null)
            ?? (in_array($defaultLang, $supported, true) ? $defaultLang : 'en');

        $rtlLangs = ['ar', 'fa', 'ur', 'he'];
        $finalDir = in_array($finalLang, $rtlLangs, true) ? 'rtl' : 'ltr';

        $allSettings = Setting::pluck('value', 'key')->toArray();

        $keys = [
            "site_name_{$finalLang}",
            "site_title_{$finalLang}",
            "site_description_{$finalLang}",
            "site_keywords_{$finalLang}",
        ];

        $translated = ImportantModel::whereIn('model', $keys)
            ->pluck('important_text', 'model')
            ->toArray();

        $translatedSettings = [
            'site_name' => $translated["site_name_{$finalLang}"] ?? '',
            'site_title' => $translated["site_title_{$finalLang}"] ?? '',
            'site_description' => $translated["site_description_{$finalLang}"] ?? '',
            'site_keywords' => $translated["site_keywords_{$finalLang}"] ?? '',
        ];

        return $this->success([
            'settings' => [
                'language' => $finalLang,
                'direction' => $finalDir,
                'all_settings' => $allSettings,
                'translated_settings' => $translatedSettings,
            ],
            'location' => $ipInfo,
            'detected' => [
                'requested_lang' => $requestedLang ?: null,
                'accept_language' => $acceptLang ?: null,
                'country' => $country,
            ],
        ]);
    }
}
