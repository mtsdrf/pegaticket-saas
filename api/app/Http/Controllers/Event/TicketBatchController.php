<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateTicketBatchDTO;
use App\DTOs\Event\UpdateTicketBatchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListTicketBatchRequest;
use App\Http\Requests\Event\StoreTicketBatchRequest;
use App\Http\Requests\Event\UpdateTicketBatchRequest;
use App\Http\Resources\Event\TicketBatchResource;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Services\APIResponse;
use App\Services\Event\TicketBatchService;

class TicketBatchController extends Controller
{
    public function __construct(
        private TicketBatchService $service
    ) {
    }

    public function index(ListTicketBatchRequest $request, TicketType $ticketType)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only(['status'])->all();

        $list = $this->service->paginate(
            $tenantId,
            $ticketType->uuid,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            TicketBatchResource::collection($list),
            __('messages.ticket_batch.list'),
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

    public function show(TicketType $ticketType, TicketBatch $batch)
    {
        $batch = $this->service->find($batch);
        $batch->load(TicketBatchService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketBatchResource($batch),
            __('messages.ticket_batch.show')
        );
    }

    public function store(StoreTicketBatchRequest $request, TicketType $ticketType)
    {
        $dto = CreateTicketBatchDTO::fromArray(
            $request->validated(),
            app('tenant_id'),
            $ticketType->uuid
        );

        $batch = $this->service->create($dto);
        $batch->load(TicketBatchService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketBatchResource($batch),
            __('messages.ticket_batch.created'),
            201
        );
    }

    public function update(UpdateTicketBatchRequest $request, TicketType $ticketType, TicketBatch $batch)
    {
        $dto = UpdateTicketBatchDTO::fromArray($request->validated());

        $batch = $this->service->update($batch, $dto);
        $batch->load(TicketBatchService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketBatchResource($batch),
            __('messages.ticket_batch.updated')
        );
    }

    public function destroy(TicketType $ticketType, TicketBatch $batch)
    {
        $this->service->delete($batch);

        return APIResponse::success(
            null,
            __('messages.ticket_batch.deleted'),
            204
        );
    }
}
