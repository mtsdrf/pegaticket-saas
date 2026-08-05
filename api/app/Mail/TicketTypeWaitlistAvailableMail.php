<?php

namespace App\Mail;

use App\Models\Event\TicketType;
use App\Models\Event\TicketTypeWaitlistEntry;
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

        return $this
            ->subject(__('messages.ticket_type_waitlist.mail_subject', ['ticket_type' => $this->ticketType->name]))
            ->view('emails.ticket-type-waitlist-available')
            ->with([
                'ticketType' => $this->ticketType,
                'waitlistEntry' => $this->waitlistEntry,
                'storefrontUrl' => $storefrontUrl,
            ]);
    }
}
