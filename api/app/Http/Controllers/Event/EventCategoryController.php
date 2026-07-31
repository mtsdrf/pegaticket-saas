<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateEventCategoryDTO;
use App\DTOs\Event\UpdateEventCategoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventCategoryRequest;
use App\Http\Requests\Event\StoreEventCategoryRequest;
use App\Http\Requests\Event\UpdateEventCategoryRequest;
use App\Http\Resources\Event\EventCategoryResource;
use App\Models\Event\EventCategory;
use App\Services\APIResponse;
use App\Services\Event\EventCategoryService;

class EventCategoryController extends Controller
{
    public function __construct(
        private EventCategoryService $service
    ) {
    }

    public function index(ListEventCategoryRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'priority_min',
            'priority_max',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EventCategoryResource::collection($list),
            __('messages.event_category.list'),
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

    public function store(StoreEventCategoryRequest $request)
    {
        $dto = CreateEventCategoryDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $category = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new EventCategoryResource($category),
            __('messages.event_category.created'),
            201
        );
    }

    public function update(UpdateEventCategoryRequest $request, EventCategory $eventCategory)
    {
        $dto = UpdateEventCategoryDTO::fromArray($request->validated());

        try {
            $category = $this->service->update($eventCategory, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new EventCategoryResource($category),
            __('messages.event_category.updated')
        );
    }

    public function destroy(EventCategory $eventCategory)
    {
        $this->service->delete($eventCategory);

        return APIResponse::success(
            null,
            __('messages.event_category.deleted'),
            204
        );
    }
}
