<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateEventSessionDTO;
use App\DTOs\Event\UpdateEventSessionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventSessionRequest;
use App\Http\Requests\Event\StoreEventSessionRequest;
use App\Http\Requests\Event\UpdateEventSessionRequest;
use App\Http\Resources\Event\EventSessionResource;
use App\Models\Event\Event;
use App\Models\Event\EventSession;
use App\Services\APIResponse;
use App\Services\Event\EventSessionService;

class EventSessionController extends Controller
{
    public function __construct(
        private EventSessionService $service
    ) {
    }

    public function index(ListEventSessionRequest $request, Event $event)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only(['status'])->all();

        $list = $this->service->paginate(
            $tenantId,
            $event->uuid,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EventSessionResource::collection($list),
            __('messages.event_session.list'),
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

    public function show(Event $event, EventSession $session)
    {
        $session = $this->service->find($session);
        $session->load(EventSessionService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventSessionResource($session),
            __('messages.event_session.show')
        );
    }

    public function store(StoreEventSessionRequest $request, Event $event)
    {
        $dto = CreateEventSessionDTO::fromArray(
            $request->validated(),
            app('tenant_id'),
            $event->uuid
        );

        $session = $this->service->create($dto);
        $session->load(EventSessionService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventSessionResource($session),
            __('messages.event_session.created'),
            201
        );
    }

    public function update(UpdateEventSessionRequest $request, Event $event, EventSession $session)
    {
        $dto = UpdateEventSessionDTO::fromArray($request->validated());

        $session = $this->service->update($session, $dto);
        $session->load(EventSessionService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventSessionResource($session),
            __('messages.event_session.updated')
        );
    }

    public function destroy(Event $event, EventSession $session)
    {
        $this->service->delete($session);

        return APIResponse::success(
            null,
            __('messages.event_session.deleted'),
            204
        );
    }
}
