<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PinResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(public User $user, string $token)
    {
        $this->resetUrl = url('/login/restablecer-pin/' . $token . '?email=' . urlencode($user->email));
    }

    public function build()
    {
        return $this->subject('Restablece tu PIN — posPapisV1')
            ->view('emails.pin-reset');
    }
}