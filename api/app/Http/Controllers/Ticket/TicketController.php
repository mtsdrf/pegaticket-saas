<?php

namespace App\Http\Controllers\Ticket;

use App\DTOs\Ticket\CheckinTicketDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\CheckinTicketRequest;
use App\Http\Requests\Ticket\ListTicketRequest;
use App\Http\Resources\Ticket\CheckinResultResource;
use App\Http\Resources\Ticket\TicketResource;
use App\Models\Ticket\Ticket;
use App\Services\APIResponse;
use App\Services\Ticket\CheckinService;
use App\Services\Ticket\TicketService;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $service,
        private CheckinService $checkinService,
    ) {
    }

    public function index(ListTicketRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)
            ->only(['status', 'ticket_type_uuid', 'event_uuid', 'event_session_uuid', 'sale_uuid', 'search'])
            ->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'desc'
        );

        return APIResponse::success(
            TicketResource::collection($list),
            __('messages.ticket.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function show(Ticket $ticket)
    {
        $ticket = $this->service->find($ticket);
        $ticket->load(TicketService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketResource($ticket),
            __('messages.ticket.show')
        );
    }

    public function resend(Ticket $ticket)
    {
        $ticket = $this->service->resend($ticket);
        $ticket->load(TicketService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketResource($ticket),
            __('messages.ticket.resent')
        );
    }

    public function checkin(CheckinTicketRequest $request)
    {
        $dto = CheckinTicketDTO::fromArray($request->validated());

        $result = $this->checkinService->checkin(
            app('tenant_id'),
            $dto,
            (int) Auth::id()
        );

        if ($result->ticket) {
            $result->ticket->load(TicketService::EAGER_RELATIONS);
        }

        return APIResponse::success(
            new CheckinResultResource($result),
            __('messages.ticket_checkin.' . $result->result)
        );
    }
}
