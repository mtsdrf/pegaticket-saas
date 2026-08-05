<?php

namespace App\Http\Controllers\Ticket;

use App\Exceptions\InvalidResaleListingStateException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\TicketResaleListingResource;
use App\Models\Ticket\TicketResaleListing;
use App\Services\APIResponse;
use App\Services\Ticket\TicketResaleService;
use Illuminate\Support\Facades\Auth;

/**
 * Liberação manual do repasse ao vendedor de uma revenda (roadmap Fase 4).
 * Sem repasse automático pra FinalCustomer — ver docblock de
 * TicketResaleService. Reaproveita a functionality `tickets` já existente
 * (ação `update`) em vez de criar uma nova permissão só pra isso.
 */
class TicketResalePayoutController extends Controller
{
    public function __construct(
        private TicketResaleService $service
    ) {}

    public function release(TicketResaleListing $listing)
    {
        try {
            $listing = $this->service->releasePayout($listing, (int) Auth::id());
        } catch (InvalidResaleListingStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_RESALE_LISTING_STATE');
        }

        return APIResponse::success(
            new TicketResaleListingResource($listing),
            __('messages.ticket_resale.payout_released')
        );
    }
}
