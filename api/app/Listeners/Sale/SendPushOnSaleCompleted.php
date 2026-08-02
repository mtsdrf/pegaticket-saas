<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCompleted;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Ver SendPushOnSaleApproved para o padrão completo (origin/link
 * confirmado/silencioso). Mesma regra aplicada ao evento de entrega.
 */
class SendPushOnSaleCompleted
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(SaleCompleted $event): void
    {
        $order = Sale::where('uuid', $event->orderUuid)->first();

        if (!$order || $order->origin !== 'storefront') {
            return;
        }

        $link = $this->linkRepository->findConfirmedByTenantAndFinalCustomer(
            (int) $order->tenant_id,
            (int) $order->final_customer_id
        );

        if (!$link) {
            return;
        }

        $this->pushNotificationService->notifyFinalCustomer(
            $link->final_customer_id,
            __('messages.push.order_completed_title'),
            __('messages.push.order_completed_body'),
            "/rastreio/{$order->uuid}"
        );
    }
}
