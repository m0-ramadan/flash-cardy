<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait FirebaseNotification
{
    /**
     * Send a push notification through the Firebase Cloud Messaging legacy API.
     *
     * NOTE: the legacy FCM endpoint (fcm/send) is retired by Google in favour of
     * the HTTP v1 API. This method is kept for backward compatibility only and
     * must not receive credentials from source code — the server key comes from
     * config/services.php (env FCM_SERVER_KEY).
     */
    public function sendFirebaseNotification($tokens, $title, $message, $file = null)
    {
        $serverKey = (string) config('services.fcm.server_key');

        if ($serverKey === '') {
            Log::warning('Firebase notification skipped: server key not configured');

            return false;
        }

        $data = [
            'registration_ids' => (array) $tokens,
            'notification' => array_filter([
                'title' => $title,
                'body' => $message,
                'image' => $file,
            ]),
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: key='.$serverKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 15,
        ]);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($result === false || $status >= 400) {
            Log::warning('Firebase notification failed', ['status' => $status, 'error' => $error]);
        }

        return $result;
    }
}
