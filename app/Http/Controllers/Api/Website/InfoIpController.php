<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class InfoIpController extends Controller
{
    public function getCountryAndCurrency(Request $request)
    {
        $ip = $request->ip();

        $client = new Client();

        try {

            $ipApiUrl = "http://ip-api.com/json/{$ip}";
            $ipResponse = $client->get($ipApiUrl);
            $ipData = json_decode($ipResponse->getBody(), true);

            if (($ipData['status'] ?? null) !== 'success') {
                return response()->json(['error' => 'Unable to detect country from IP'], 400);
            }

            $countryName = $ipData['country'] ?? null;
            if (!$countryName) {
                return response()->json(['error' => 'Country not found in IP response'], 404);
            }

            $countryResponse = $client->get(
                'https://api.restcountries.com/countries/v5',
                [
                    'query' => [
                        'q' => $countryName,
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer rc_live_e7b7c3f11c3d4240b10e160c3af95ca3',
                        'Accept' => 'application/json',
                    ],
                ]
            );
            $countryData = json_decode($countryResponse->getBody(), true);

            $country = $countryData['data']['objects'][0] ?? null;
            if (!is_array($country)) {
                return response()->json(['error' => 'Country not found'], 404);
            }

            $currencyData = $country['currencies'][0] ?? [];
            if (!is_array($currencyData)) {
                $currencyData = [];
            }

            $currencyCode = $currencyData['code'] ?? null;
            $currencyName = $currencyData['name'] ?? 'Unknown';
            $currencySymbol = $currencyData['symbol'] ?? '';

            $response = [

                'country' => $countryName,
                'currency' => [
                    'code' => $currencyCode,
                    'name' => $currencyName,
                    'symbol' => $currencySymbol,
                ],
            ];

            return response()->json($response, 200);
        } catch (RequestException $e) {
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }
}
