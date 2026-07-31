<?php

namespace App\Http\Controllers\Support;

use App\DTOs\Support\CreateSupportTicketDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateSupportTicketRequest;
use App\Http\Resources\Support\SupportTicketResource;
use App\Services\APIResponse;
use App\Services\Support\SupportTicketService;

class SupportTicketController extends Controller
{
    public function __construct(
        private SupportTicketService $service
    ) {
    }

    public function index()
    {
        $tickets = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            SupportTicketResource::collection($tickets),
            __('messages.support_ticket.list')
        );
    }

    public function store(CreateSupportTicketRequest $request)
    {
        $dto = CreateSupportTicketDTO::fromArray($request->validated());

        $ticket = $this->service->create(
            app('tenant_id'),
            $dto,
            $request->file('attachment')
        );

        return APIResponse::success(
            new SupportTicketResource($ticket),
            __('messages.support_ticket.created'),
            201
        );
    }
}
