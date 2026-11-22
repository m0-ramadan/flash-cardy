<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransactionalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public function __construct(public string $mailSubject, public string $bodyText) {}
    public function build(){ return $this->subject($this->mailSubject)->text('emails.transactional-text'); }
}
