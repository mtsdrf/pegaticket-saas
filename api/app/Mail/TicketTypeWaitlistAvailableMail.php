<?php

namespace App\Mail;

use App\Models\Event\TicketType;
use App\Models\Event\TicketTypeWaitlistEntry;
use App\Services\Communication\BrandedEmailLayoutRenderer;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Voltou a ter vaga" (lista de espera de TicketType esgotado) — ver
 * App\Services\TicketTypeWaitlist\TicketTypeWaitlistService::notifyAvailableTicketTypes()
 * e NotifyTicketTypeWaitlistCommand. Mailable simples, sem template
 * visual elaborado (não pedido).
 */
class TicketTypeWaitlistAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TicketType $ticketType,
        public TicketTypeWaitlistEntry $waitlistEntry,
    ) {}

    public function build(): self
    {
        $storefrontUrl = rtrim((string) config('app.frontend_url'), '/')
            .'/eventos/'.$this->ticketType->tenant->slug
            .'/'.$this->ticketType->event?->slug;

        $placeholders = [
            'tipo_ingresso' => $this->ticketType->name,
            'link' => $storefrontUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $defaultSubject = __('messages.ticket_type_waitlist.mail_subject', ['ticket_type' => $this->ticketType->name]);
        $subject = $resolver->resolveSubject('waitlist_available', $this->ticketType->tenant_id, $defaultSubject, $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('waitlist_available', $this->ticketType->tenant_id, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html(app(BrandedEmailLayoutRenderer::class)->wrap($bodyHtml, [
                'preheader' => "O ingresso {$this->ticketType->name} voltou a ficar disponível.",
                'headline' => 'Boa notícia: voltou a ter vaga',
            ]))
            : $mail->view('emails.ticket-type-waitlist-available')->with([
                'ticketType' => $this->ticketType,
                'waitlistEntry' => $this->waitlistEntry,
                'storefrontUrl' => $storefrontUrl,
            ]);
    }
}
