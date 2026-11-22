<?php

namespace App\Services\Notifications;

use App\Mail\TransactionalMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public function user(User $user, string $title, string $message, string $type='general', array $data=[], bool $email=false): void
    {
        try {
            if (Schema::hasTable('notifications')) {
                Notification::create([
                    'notifiable_id'=>$user->id,
                    'notifiable_type'=>User::class,
                    'title'=>$title,'message'=>$message,'type'=>$type,'data'=>$data,'is_read'=>false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to persist user notification',['user_id'=>$user->id,'type'=>$type,'error'=>$e->getMessage()]);
        }
        if ($email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            try { Mail::to($user->email)->queue(new TransactionalMail($title,$message)); }
            catch (\Throwable $e) { Log::warning('Failed to queue notification email',['user_id'=>$user->id,'type'=>$type,'error'=>$e->getMessage()]); }
        }
    }
}
