<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleRejected;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Ver SendPushOnSaleApproved para o padrão completo (origin/link
 * confirmado/silencioso). Mesma regra aplicada ao evento de recusa.
 */
class SendPushOnSaleRejected
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(SaleRejected $event): void
    {
        $order = Sale::where('uuid', $event->saleUuid)->first();

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
            __('messages.push.order_rejected_title'),
            __('messages.push.order_rejected_body'),
            "/compra/{$order->uuid}"
        );
    }
}
