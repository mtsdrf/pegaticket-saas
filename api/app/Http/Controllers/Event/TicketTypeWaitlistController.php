<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypeWaitlist\ListTicketTypeWaitlistEntryRequest;
use App\Http\Resources\TicketTypeWaitlist\TicketTypeWaitlistEntryResource;
use App\Services\APIResponse;
use App\Services\TicketTypeWaitlist\TicketTypeWaitlistService;

class TicketTypeWaitlistController extends Controller
{
    public function __construct(
        private TicketTypeWaitlistService $service
    ) {}

    public function index(ListTicketTypeWaitlistEntryRequest $request, string $ticketType)
    {
        $entries = $this->service->paginate(
            app('tenant_id'),
            $ticketType,
            (int) ($request->validated('per_page') ?? 15)
        );

        return APIResponse::success(
            TicketTypeWaitlistEntryResource::collection($entries),
            __('messages.ticket_type_waitlist.list'),
            200,
            ['pagination' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ]]
        );
    }
}
