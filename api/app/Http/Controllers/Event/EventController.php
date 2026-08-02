<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\Event\EventResource;
use App\Models\Event\Event;
use App\Services\APIResponse;
use App\Services\Event\EventService;

class EventController extends Controller
{
    private const DETAIL_RELATIONS = EventService::DETAIL_RELATIONS;

    public function __construct(
        private EventService $service
    ) {
    }

    public function index(ListEventRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'event_category_uuid',
            'type',
            'status',
            'visibility',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EventResource::collection($list),
            __('messages.event.list'),
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

    public function show(Event $event)
    {
        $event = $this->service->find($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new EventResource($event),
            __('messages.event.show')
        );
    }

    public function store(StoreEventRequest $request)
    {
        $dto = CreateEventDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        $event = $this->service->create($dto);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new EventResource($event),
            __('messages.event.created'),
            201
        );
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $dto = UpdateEventDTO::fromArray($request->validated());

        $event = $this->service->update($event, $dto);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(
            new EventResource($event),
            __('messages.event.updated')
        );
    }

    public function destroy(Event $event)
    {
        $this->service->delete($event);

        return APIResponse::success(
            null,
            __('messages.event.deleted'),
            204
        );
    }

    public function publish(Event $event)
    {
        $event = $this->service->publish($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.published'));
    }

    public function pauseSales(Event $event)
    {
        $event = $this->service->pauseSales($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.sales_paused'));
    }

    public function resumeSales(Event $event)
    {
        $event = $this->service->resumeSales($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.sales_resumed'));
    }

    public function closeSales(Event $event)
    {
        $event = $this->service->closeSales($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.sales_closed'));
    }

    public function cancel(Event $event)
    {
        $event = $this->service->cancel($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.canceled'));
    }

    public function archive(Event $event)
    {
        $event = $this->service->archive($event);
        $event->load(self::DETAIL_RELATIONS);

        return APIResponse::success(new EventResource($event), __('messages.event.archived'));
    }
}
