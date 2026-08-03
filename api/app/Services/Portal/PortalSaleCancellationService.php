<?php

namespace App\Services\Portal;

use App\DTOs\Sale\RequestSaleCancellationDTO;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Services\Sale\SaleService;

/**
 * Solicitação de cancelamento pelo cliente final (roadmap A4). Reaproveita
 * PortalCustomerService::findOwnedOrder() para a checagem de posse (mesma
 * lógica de SaleRatingService/reorder), e delega a mutação de estado para
 * SaleService::requestCancellation() — a regra de negócio (elegibilidade,
 * transição de status) mora no Service de Venda, não aqui.
 */
class PortalSaleCancellationService
{
    public function __construct(
        private PortalCustomerService $portalCustomerService,
        private SaleService $orderService,
    ) {
    }

    public function request(FinalCustomer $customer, string $saleUuid, RequestSaleCancellationDTO $dto): Sale
    {
        $order = $this->portalCustomerService->findOwnedOrder($customer->id, $saleUuid);

        return $this->orderService->requestCancellation($order, $dto->reason, $customer->uuid);
    }
}
