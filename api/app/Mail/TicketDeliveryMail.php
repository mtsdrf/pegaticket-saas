<?php

namespace App\Mail;

use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, Ticket> $tickets
     */
    public function __construct(
        public Sale $sale,
        public Collection $tickets,
        public string $trackingUrl,
        public string $mode = 'issued',
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('messages.ticket.mail_subject_' . $this->mode, ['code' => $this->sale->codigo]))
            ->view('emails.ticket-delivery')
            ->with([
                'sale' => $this->sale,
                'tickets' => $this->tickets,
                'trackingUrl' => $this->trackingUrl,
                'mode' => $this->mode,
            ]);
    }
}
