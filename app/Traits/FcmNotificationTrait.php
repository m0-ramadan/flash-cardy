<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait FcmNotificationTrait
{
    public function send_notification_user($token, $message, $type)
    {
        return $this->sendLegacyFcm([
            'to' => $token,
            'notification' => [
                'title' => $message['title'] ?? '',
                'body' => $message['body'] ?? '',
                'sound' => $message['sound'] ?? 'default',
                'vibrate' => $message['vibrate'] ?? true,
                'image' => $message['largeIcon'] ?? null,
                'date' => date('Y-m-d'),
            ],
            'data' => [
                'message' => [
                    'title' => $message['title'] ?? '',
                    'body' => $message['body'] ?? '',
                ],
                'type' => $type,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ]);
    }

    public function sendNotifications($body, $token, $title)
    {
        return $this->sendLegacyFcm([
            'registration_ids' => [$token],
            'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
        ]);
    }

    private function sendLegacyFcm(array $payload)
    {
        $serverKey = (string) config('services.fcm.server_key');
        if ($serverKey === '') {
            Log::warning('FCM notification skipped: server key not configured');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);

            if (!$response->successful()) {
                Log::warning('FCM notification failed', ['status' => $response->status()]);
            }
            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('FCM notification exception', ['error_class' => $e::class]);
            return false;
        }
    }
}
