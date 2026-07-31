<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateEventProductDTO;
use App\DTOs\Event\UpdateEventProductDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventProductRequest;
use App\Http\Requests\Event\StoreEventProductRequest;
use App\Http\Requests\Event\UpdateEventProductRequest;
use App\Http\Resources\Event\EventProductResource;
use App\Models\Event\EventProduct;
use App\Services\APIResponse;
use App\Services\Event\EventProductService;

class EventProductController extends Controller
{
    public function __construct(
        private EventProductService $service
    ) {
    }

    public function index(ListEventProductRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'event_uuid',
            'kind',
            'status',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EventProductResource::collection($list),
            __('messages.event_product.list'),
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

    public function show(EventProduct $eventProduct)
    {
        $eventProduct = $this->service->find($eventProduct);
        $eventProduct->load(EventProductService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventProductResource($eventProduct),
            __('messages.event_product.show')
        );
    }

    public function store(StoreEventProductRequest $request)
    {
        $dto = CreateEventProductDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        $eventProduct = $this->service->create($dto);
        $eventProduct->load(EventProductService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventProductResource($eventProduct),
            __('messages.event_product.created'),
            201
        );
    }

    public function update(UpdateEventProductRequest $request, EventProduct $eventProduct)
    {
        $dto = UpdateEventProductDTO::fromArray($request->validated());

        $eventProduct = $this->service->update($eventProduct, $dto);
        $eventProduct->load(EventProductService::EAGER_RELATIONS);

        return APIResponse::success(
            new EventProductResource($eventProduct),
            __('messages.event_product.updated')
        );
    }

    public function destroy(EventProduct $eventProduct)
    {
        $this->service->delete($eventProduct);

        return APIResponse::success(
            null,
            __('messages.event_product.deleted'),
            204
        );
    }
}
