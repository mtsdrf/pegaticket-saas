<?php

namespace App\Mail;

use App\Services\Communication\BrandedEmailLayoutRenderer;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notificação de mudança de status de recebimentos do tenant (roadmap
 * R2.5, seção 9.5.6) — disparada por
 * App\Listeners\Payment\NotifyTenantPagBankConnectionStatusChanged só
 * quando o novo status entra no grupo "habilitado"
 * (enabled/verified) ou "pendência" (restricted/rejected); nenhum outro
 * status gera e-mail. Textos oficiais da seção 9.6 preservados
 * literalmente. Dois `type` distintos ('pagbank_receiving_enabled' /
 * 'pagbank_receiving_pending') para permitir customização independente
 * via EmailTemplate, mesmo padrão de TicketTypeWaitlistAvailableMail.
 */
class PagBankReceivingStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public const VARIANT_ENABLED = 'enabled';

    public const VARIANT_PENDING = 'pending';

    public function __construct(
        public string $variant,
        public int $tenantId,
        public string $tenantName,
    ) {}

    public function build(): self
    {
        $isEnabled = $this->variant === self::VARIANT_ENABLED;
        $type = $isEnabled ? 'pagbank_receiving_enabled' : 'pagbank_receiving_pending';
        $settingsUrl = rtrim((string) config('app.frontend_url'), '/').'/configuracoes/pagbank-connect';

        $placeholders = [
            'empresa' => $this->tenantName,
            'link' => $settingsUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $defaultSubject = $isEnabled
            ? __('messages.pagbank_receiving.enabled_mail_subject')
            : __('messages.pagbank_receiving.pending_mail_subject');
        $subject = $resolver->resolveSubject($type, $this->tenantId, $defaultSubject, $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml($type, $this->tenantId, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html(app(BrandedEmailLayoutRenderer::class)->wrap($bodyHtml, [
                'preheader' => $defaultSubject,
                'headline' => $defaultSubject,
            ]))
            : $mail->view('emails.pagbank-receiving-status-changed')->with([
                'isEnabled' => $isEnabled,
                'tenantName' => $this->tenantName,
                'settingsUrl' => $settingsUrl,
            ]);
    }
}
