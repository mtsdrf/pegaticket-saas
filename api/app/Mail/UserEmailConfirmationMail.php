<?php

namespace App\Mail;

use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserEmailConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newEmail,
        public string $confirmUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('messages.profile.mail_subject'))
            ->view('emails.user-email-confirmation')
            ->with([
                'user' => $this->user,
                'newEmail' => $this->newEmail,
                'confirmUrl' => $this->confirmUrl,
            ]);
    }
}
