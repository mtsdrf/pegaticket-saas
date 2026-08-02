<?php

namespace App\Services\Ticket;

use App\Mail\TicketDeliveryMail;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class TicketDeliveryService
{
    /**
     * @param Collection<int, Ticket> $tickets
     */
    public function sendForSale(Sale $sale, Collection $tickets, string $mode = 'issued'): void
    {
        $sale->loadMissing('finalCustomer');

        $email = trim((string) ($sale->finalCustomer?->email ?? ''));

        if ($email === '' || $tickets->isEmpty()) {
            return;
        }

        $tickets->loadMissing('ticketType.event', 'ticketType.session', 'seat', 'saleItem.sale');

        $trackingUrl = rtrim((string) config('app.frontend_url'), '/') . '/rastreio/' . $sale->uuid;

        Mail::to($email)->send(new TicketDeliveryMail(
            sale: $sale,
            tickets: $tickets,
            trackingUrl: $trackingUrl,
            mode: $mode,
        ));
    }
}
