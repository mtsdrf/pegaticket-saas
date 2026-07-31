<?php

namespace App\Http\Controllers\Support;

use App\DTOs\Support\CreateHelpRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateHelpRequestRequest;
use App\Http\Resources\Support\HelpRequestResource;
use App\Services\APIResponse;
use App\Services\Support\HelpRequestService;

class HelpRequestController extends Controller
{
    public function __construct(
        private HelpRequestService $service
    ) {
    }

    public function index()
    {
        $tickets = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            HelpRequestResource::collection($tickets),
            __('messages.help_request.list')
        );
    }

    public function store(CreateHelpRequestRequest $request)
    {
        $dto = CreateHelpRequestDTO::fromArray($request->validated());

        $ticket = $this->service->create(
            app('tenant_id'),
            $dto,
            $request->file('attachment')
        );

        return APIResponse::success(
            new HelpRequestResource($ticket),
            __('messages.help_request.created'),
            201
        );
    }
}
