<?php

namespace App\Services\Storefront;

use App\DTOs\Storefront\CreateCartEventDTO;
use App\Models\Storefront\CartEvent;
use App\Repositories\Contracts\CartEventRepositoryInterface;

/**
 * Telemetria de carrinho (roadmap A3.14). Só registra — sem Event/Listener
 * de auditoria: `Auditable`/`WriteAuditLog` documentam mutação de domínio
 * feita por um ator identificável (staff), e aqui o ator é um visitante
 * anônimo da loja pública sem sessão staff. Auditoria de negócio não se
 * aplica a dado de telemetria/analytics, então a decisão é deliberada, não
 * uma omissão.
 */
class CartEventService
{
    public function __construct(
        private CartEventRepositoryInterface $repository
    ) {
    }

    public function record(CreateCartEventDTO $dto): CartEvent
    {
        return $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'session_id' => $dto->sessionId,
            'event_type' => $dto->eventType,
            'payload' => $dto->payload,
        ]);
    }
}
