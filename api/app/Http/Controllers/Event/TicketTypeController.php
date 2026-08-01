<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateTicketTypeDTO;
use App\DTOs\Event\UpdateTicketTypeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListTicketTypeRequest;
use App\Http\Requests\Event\StoreTicketTypeRequest;
use App\Http\Requests\Event\ToggleTicketTypeStatusRequest;
use App\Http\Requests\Event\UpdateTicketTypeRequest;
use App\Http\Resources\Event\TicketTypeResource;
use App\Models\Event\TicketType;
use App\Services\APIResponse;
use App\Services\Event\TicketTypeService;

class TicketTypeController extends Controller
{
    private const DETAIL_RELATIONS = TicketTypeService::DETAIL_RELATIONS;

    public function __construct(
        private TicketTypeService $service
    ) {
    }

    public function index(ListTicketTypeRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'event_uuid',
            'status',
            'price_min',
            'price_max',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            TicketTypeResource::collection($list),
            __('messages.ticket_type.list'),
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

    public function show(TicketType $ticketType)
    {
        $ticketType = $this->service->find($ticketType);
        $ticketType->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new TicketTypeResource($ticketType),
            __('messages.ticket_type.show')
        );
    }

    public function store(StoreTicketTypeRequest $request)
    {
        $dto = CreateTicketTypeDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        $ticketType = $this->service->create($dto);
        $ticketType->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new TicketTypeResource($ticketType),
            __('messages.ticket_type.created'),
            201
        );
    }

    public function update(UpdateTicketTypeRequest $request, TicketType $ticketType)
    {
        $dto = UpdateTicketTypeDTO::fromArray($request->validated());

        $ticketType = $this->service->update($ticketType, $dto);
        $ticketType->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new TicketTypeResource($ticketType),
            __('messages.ticket_type.updated')
        );
    }

    public function toggleStatus(ToggleTicketTypeStatusRequest $request, TicketType $ticketType)
    {
        $ticketType = $this->service->toggleStatus($ticketType, $request->input('status'));
        $ticketType->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new TicketTypeResource($ticketType),
            __('messages.ticket_type.status_updated')
        );
    }

    public function destroy(TicketType $ticketType)
    {
        $this->service->delete($ticketType);

        return APIResponse::success(
            null,
            __('messages.ticket_type.deleted'),
            204
        );
    }
}
