<?php

namespace App\Mail;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Tenant\Tenant;
use App\Services\Communication\BrandedEmailLayoutRenderer;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Automação de recompra (Fase 6, fatia final) — "sentimos sua falta" para
 * compradores que já compraram desta loja mas não compram há mais de N
 * dias (ver SendRecompraNudgeMailsCommand). Reaproveita a mesma
 * infraestrutura de Mail já usada por TicketDeliveryMail, mailable/view
 * próprios porque o conteúdo é bem diferente (convite, não comprovante).
 */
class RecompraNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public FinalCustomer $finalCustomer,
        public string $storefrontUrl,
    ) {}

    public function build(): self
    {
        $placeholders = [
            'cliente' => $this->finalCustomer->name,
            'empresa' => $this->tenant->name,
            'link' => $this->storefrontUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $defaultSubject = __('messages.customers.recompra_mail_subject', ['tenant' => $this->tenant->name]);
        $subject = $resolver->resolveSubject('recompra_nudge', $this->tenant->id, $defaultSubject, $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('recompra_nudge', $this->tenant->id, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html(app(BrandedEmailLayoutRenderer::class)->wrap($bodyHtml, [
                'preheader' => "Novos eventos disponíveis em {$this->tenant->name}.",
                'headline' => 'Sentimos sua falta por aqui',
            ]))
            : $mail->view('emails.recompra-nudge')->with([
                'tenant' => $this->tenant,
                'finalCustomer' => $this->finalCustomer,
                'storefrontUrl' => $this->storefrontUrl,
            ]);
    }
}
