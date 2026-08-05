<?php

namespace App\Mail;

use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortalOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes,
    ) {}

    public function build(): self
    {
        $placeholders = [
            'codigo' => $this->code,
            'minutos' => (string) $this->expiresInMinutes,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $subject = $resolver->resolveSubject('portal_otp', null, __('messages.portal.otp_mail_subject'), $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('portal_otp', null, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html($bodyHtml)
            : $mail->view('emails.portal-otp')->with([
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
