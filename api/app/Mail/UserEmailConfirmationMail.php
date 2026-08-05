<?php

namespace App\Mail;

use App\Models\User\User;
use App\Services\Communication\EmailTemplateResolverService;
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
    ) {}

    public function build(): self
    {
        $placeholders = [
            'nome' => $this->user->name,
            'novo_email' => $this->newEmail,
            'link' => $this->confirmUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $subject = $resolver->resolveSubject('email_confirmation', null, __('messages.profile.mail_subject'), $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('email_confirmation', null, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html($bodyHtml)
            : $mail->view('emails.user-email-confirmation')->with([
                'user' => $this->user,
                'newEmail' => $this->newEmail,
                'confirmUrl' => $this->confirmUrl,
            ]);
    }
}
