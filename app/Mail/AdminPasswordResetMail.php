<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $resetUrl) {}

    public function build()
    {
        return $this->subject('إعادة تعيين كلمة مرور لوحة التحكم')
            ->view('emails.admin-password-reset');
    }
}
