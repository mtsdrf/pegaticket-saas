<?php

namespace App\Http\Controllers\Event;

use App\DTOs\Event\CreateTicketTypeChannelQuotaDTO;
use App\DTOs\Event\UpdateTicketTypeChannelQuotaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListTicketTypeChannelQuotaRequest;
use App\Http\Requests\Event\StoreTicketTypeChannelQuotaRequest;
use App\Http\Requests\Event\UpdateTicketTypeChannelQuotaRequest;
use App\Http\Resources\Event\TicketTypeChannelQuotaResource;
use App\Models\Event\TicketType;
use App\Models\Event\TicketTypeChannelQuota;
use App\Services\APIResponse;
use App\Services\Event\TicketTypeChannelQuotaService;

class TicketTypeChannelQuotaController extends Controller
{
    public function __construct(
        private TicketTypeChannelQuotaService $service
    ) {}

    public function index(ListTicketTypeChannelQuotaRequest $request, TicketType $ticketType)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $list = $this->service->paginate(
            $tenantId,
            $ticketType->uuid,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            TicketTypeChannelQuotaResource::collection($list),
            __('messages.ticket_type_channel_quota.list'),
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

    public function show(TicketType $ticketType, TicketTypeChannelQuota $quota)
    {
        $quota = $this->service->find($quota);
        $this->assertBelongsToTicketType($quota, $ticketType);
        $quota->load(TicketTypeChannelQuotaService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketTypeChannelQuotaResource($quota),
            __('messages.ticket_type_channel_quota.show')
        );
    }

    public function store(StoreTicketTypeChannelQuotaRequest $request, TicketType $ticketType)
    {
        $dto = CreateTicketTypeChannelQuotaDTO::fromArray(
            $request->validated(),
            app('tenant_id'),
            $ticketType->uuid
        );

        $quota = $this->service->create($dto);
        $quota->load(TicketTypeChannelQuotaService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketTypeChannelQuotaResource($quota),
            __('messages.ticket_type_channel_quota.created'),
            201
        );
    }

    public function update(UpdateTicketTypeChannelQuotaRequest $request, TicketType $ticketType, TicketTypeChannelQuota $quota)
    {
        $this->assertBelongsToTicketType($quota, $ticketType);

        $dto = UpdateTicketTypeChannelQuotaDTO::fromArray($request->validated());

        $quota = $this->service->update($quota, $dto);
        $quota->load(TicketTypeChannelQuotaService::EAGER_RELATIONS);

        return APIResponse::success(
            new TicketTypeChannelQuotaResource($quota),
            __('messages.ticket_type_channel_quota.updated')
        );
    }

    public function destroy(TicketType $ticketType, TicketTypeChannelQuota $quota)
    {
        $this->assertBelongsToTicketType($quota, $ticketType);

        $this->service->delete($quota);

        return APIResponse::success(
            null,
            __('messages.ticket_type_channel_quota.deleted'),
            204
        );
    }

    /**
     * {quota} da rota nunca é comparado ao {ticketType} pelo route model
     * binding — sem isso, dentro do mesmo tenant seria possível
     * editar/remover a cota de outro tipo de ingresso acertando a URL.
     */
    private function assertBelongsToTicketType(TicketTypeChannelQuota $quota, TicketType $ticketType): void
    {
        if ((int) $quota->ticket_type_id !== (int) $ticketType->id) {
            abort(404);
        }
    }
}
