<?php

namespace App\Services\Storefront;

use App\DTOs\Storefront\CreateFunnelEventDTO;
use App\Models\Storefront\StorefrontFunnelEvent;
use App\Repositories\Contracts\FunnelEventRepositoryInterface;

/**
 * Telemetria de funil de conversão (roadmap A2) — só registra, sem
 * Event/Listener de auditoria (mesma decisão deliberada de CartEventService:
 * ator é visitante anônimo, não staff).
 */
class FunnelEventService
{
    public function __construct(
        private FunnelEventRepositoryInterface $repository
    ) {}

    public function record(CreateFunnelEventDTO $dto): StorefrontFunnelEvent
    {
        return $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'event_id' => $dto->eventId,
            'session_id' => $dto->sessionId,
            'step' => $dto->step,
        ]);
    }
}
