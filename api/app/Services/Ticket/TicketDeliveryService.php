<?php

namespace App\Services\Ticket;

use App\Mail\TicketDeliveryMail;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Services\Communication\CommunicationDispatcherService;
use Illuminate\Database\Eloquent\Collection;

class TicketDeliveryService
{
    public function __construct(
        private CommunicationDispatcherService $communicationDispatcher,
        private TicketPdfService $ticketPdfService,
    ) {}

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function sendForSale(Sale $sale, Collection $tickets, string $mode = 'issued'): void
    {
        $sale->loadMissing('finalCustomer');

        $email = trim((string) ($sale->finalCustomer?->email ?? ''));

        if ($email === '' || $tickets->isEmpty()) {
            return;
        }

        $tickets->loadMissing('ticketType.event', 'ticketType.session', 'seat', 'saleItem.sale');

        $trackingUrl = rtrim((string) config('app.frontend_url'), '/').'/compra/'.$sale->uuid;
        $ticketPdfUrl = $this->ticketPdfService->publicDownloadUrl($sale);

        $this->communicationDispatcher->send(
            $mode === 'reminder' ? 'event_reminder' : 'ticket_delivery',
            new TicketDeliveryMail(
                sale: $sale,
                tickets: $tickets,
                trackingUrl: $trackingUrl,
                ticketPdfUrl: $ticketPdfUrl,
                mode: $mode,
            ),
            $email,
            $sale->tenant_id
        );
    }
}
