<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateEventGateDTO;
use App\DTOs\Event\UpdateEventGateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventGateRequest;
use App\Http\Requests\Event\StoreEventGateRequest;
use App\Http\Requests\Event\UpdateEventGateRequest;
use App\Http\Resources\Event\EventGateResource;
use App\Models\Event\Event;
use App\Models\Event\EventGate;
use App\Services\APIResponse;
use App\Services\Event\EventGateService;

class EventGateController extends Controller
{
    public function __construct(
        private EventGateService $service
    ) {}

    public function index(ListEventGateRequest $request, Event $event)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only(['is_active'])->all();

        $list = $this->service->paginate(
            $tenantId,
            $event->uuid,
            $filters,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            EventGateResource::collection($list),
            __('messages.event_gate.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    public function show(Event $event, EventGate $gate)
    {
        $gate = $this->service->find($gate);
        $gate->load(EventGateService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventGateResource($gate),
            __('messages.event_gate.show')
        );
    }

    public function store(StoreEventGateRequest $request, Event $event)
    {
        $dto = CreateEventGateDTO::fromArray(
            $request->validated(),
            app('tenant_id'),
            $event->uuid
        );

        $gate = $this->service->create($dto);
        $gate->load(EventGateService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventGateResource($gate),
            __('messages.event_gate.created'),
            201
        );
    }

    public function update(UpdateEventGateRequest $request, Event $event, EventGate $gate)
    {
        $dto = UpdateEventGateDTO::fromArray($request->validated());

        $gate = $this->service->update($gate, $dto);
        $gate->load(EventGateService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventGateResource($gate),
            __('messages.event_gate.updated')
        );
    }

    public function destroy(Event $event, EventGate $gate)
    {
        $this->service->delete($gate);

        return APIResponse::success(
            null,
            __('messages.event_gate.deleted'),
            204
        );
    }
}
