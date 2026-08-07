<?php

namespace App\Mail;

use App\Models\User\User;
use App\Services\Communication\BrandedEmailLayoutRenderer;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl
    ) {}

    public function build(): self
    {
        $placeholders = [
            'nome' => $this->user->name,
            'link' => $this->resetUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $subject = $resolver->resolveSubject('password_reset', null, __('messages.auth.password_reset_mail_subject'), $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('password_reset', null, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html(app(BrandedEmailLayoutRenderer::class)->wrap($bodyHtml, [
                'preheader' => 'Solicitação de redefinição de senha no PegaTicket.',
                'headline' => 'Redefina sua senha com segurança',
            ]))
            : $mail->view('emails.password-reset')->with([
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
            ]);
    }
}
