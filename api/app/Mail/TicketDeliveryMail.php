<?php

namespace App\Mail;

use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function __construct(
        public Sale $sale,
        public Collection $tickets,
        public string $trackingUrl,
        public string $mode = 'issued',
    ) {}

    public function build(): self
    {
        $type = $this->mode === 'reminder' ? 'event_reminder' : 'ticket_delivery';

        $placeholders = [
            'nome_comprador' => (string) ($this->sale->finalCustomer?->name ?? ''),
            'codigo_venda' => (string) $this->sale->codigo,
            'quantidade_ingressos' => (string) $this->tickets->count(),
            'link' => $this->trackingUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $defaultSubject = __('messages.ticket.mail_subject_'.$this->mode, ['code' => $this->sale->codigo]);
        $subject = $resolver->resolveSubject($type, $this->sale->tenant_id, $defaultSubject, $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml($type, $this->sale->tenant_id, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html($bodyHtml)
            : $mail->view('emails.ticket-delivery')->with([
                'sale' => $this->sale,
                'tickets' => $this->tickets,
                'trackingUrl' => $this->trackingUrl,
                'mode' => $this->mode,
            ]);
    }
}
