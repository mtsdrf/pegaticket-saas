<?php

namespace App\Http\Controllers\Portal;

use App\DTOs\Ticket\CreateTicketResaleListingDTO;
use App\Exceptions\InvalidResaleListingStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CreateTicketResaleListingRequest;
use App\Http\Requests\Portal\ListTicketResaleListingRequest;
use App\Http\Requests\Portal\PurchaseTicketResaleListingRequest;
use App\Http\Resources\Portal\PortalTicketResource;
use App\Http\Resources\Portal\TicketResaleListingResource;
use App\Models\Event\Event;
use App\Models\Ticket\TicketResaleListing;
use App\Services\APIResponse;
use App\Services\Ticket\TicketResaleService;

/**
 * Revenda oficial verificada (roadmap Fase 4) — reaproveita
 * TicketService::transfer() no fechamento, ver TicketResaleService. Todas
 * as rotas autenticadas como FinalCustomer (`customer.jwt`, sem
 * tenant/perm — mesmo padrão do resto do Portal).
 */
class TicketResaleController extends Controller
{
    public function __construct(
        private TicketResaleService $service
    ) {}

    public function eligibleTickets()
    {
        $tickets = $this->service->eligibleTickets(portal_customer()->id);

        return APIResponse::success(
            PortalTicketResource::collection($tickets),
            __('messages.ticket_resale.eligible_tickets_shown')
        );
    }

    public function myListings()
    {
        $listings = $this->service->myListings(portal_customer()->id);

        return APIResponse::success(
            TicketResaleListingResource::collection($listings),
            __('messages.ticket_resale.my_listings_shown')
        );
    }

    public function store(CreateTicketResaleListingRequest $request, string $ticketUuid)
    {
        $dto = CreateTicketResaleListingDTO::fromArray($request->validated());

        try {
            $listing = $this->service->create(portal_customer()->id, $ticketUuid, $dto);
        } catch (InvalidResaleListingStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_RESALE_LISTING_STATE');
        }

        $listing->load(TicketResaleService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketResaleListingResource($listing),
            __('messages.ticket_resale.created'),
            201
        );
    }

    public function cancel(string $uuid)
    {
        try {
            $listing = $this->service->cancel(portal_customer()->id, $uuid);
        } catch (InvalidResaleListingStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_RESALE_LISTING_STATE');
        }

        return APIResponse::success(
            new TicketResaleListingResource($listing),
            __('messages.ticket_resale.cancelled')
        );
    }

    /**
     * Rota do portal não passa pelo middleware `tenant` — tenant_id
     * resolvido a partir do event_uuid (obrigatório), único jeito seguro
     * de escopar a busca sem vazar anúncios de outro tenant.
     */
    public function browse(ListTicketResaleListingRequest $request)
    {
        $validated = $request->validated();

        $event = Event::where('uuid', $validated['event_uuid'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        $listings = $this->service->browseActive(
            (int) $event->tenant_id,
            $validated['event_uuid'],
            $validated['event_session_uuid'] ?? null,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            TicketResaleListingResource::collection($listings),
            __('messages.ticket_resale.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $listings->currentPage(),
                    'per_page' => $listings->perPage(),
                    'total' => $listings->total(),
                    'last_page' => $listings->lastPage(),
                ],
            ]
        );
    }

    public function purchase(PurchaseTicketResaleListingRequest $request, string $uuid)
    {
        $listing = TicketResaleListing::where('uuid', $uuid)->whereNull('deleted_at')->firstOrFail();

        app()->instance('tenant_id', $listing->tenant_id);

        try {
            $listing = $this->service->purchase(
                portal_customer()->id,
                $uuid,
                $request->validated()['attendee_document'] ?? null
            );
        } catch (InvalidResaleListingStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_RESALE_LISTING_STATE');
        }

        $listing->load(TicketResaleService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketResaleListingResource($listing),
            __('messages.ticket_resale.purchased')
        );
    }
}
