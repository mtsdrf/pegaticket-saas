<?php

namespace App\Services\Portal;

use App\DTOs\Order\RequestOrderCancellationDTO;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Services\Order\OrderService;

/**
 * Solicitação de cancelamento pelo cliente final (roadmap A4). Reaproveita
 * PortalCustomerService::findOwnedOrder() para a checagem de posse (mesma
 * lógica de OrderRatingService/reorder), e delega a mutação de estado para
 * OrderService::requestCancellation() — a regra de negócio (elegibilidade,
 * transição de status) mora no Service de Pedido, não aqui.
 */
class PortalOrderCancellationService
{
    public function __construct(
        private PortalCustomerService $portalCustomerService,
        private OrderService $orderService,
    ) {
    }

    public function request(FinalCustomer $customer, string $orderUuid, RequestOrderCancellationDTO $dto): Order
    {
        $order = $this->portalCustomerService->findOwnedOrder($customer->id, $orderUuid);

        return $this->orderService->requestCancellation($order, $dto->reason, $customer->uuid);
    }
}
