<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleOutForDelivery;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Mesmo padrão de SendPushOnSaleApproved — só age para pedido
 * origin='storefront', silencioso sem vínculo confirmado.
 */
class SendPushOnSaleOutForDelivery
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(SaleOutForDelivery $event): void
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
            __('messages.push.order_out_for_delivery_title'),
            __('messages.push.order_out_for_delivery_body'),
            "/rastreio/{$order->uuid}"
        );
    }
}
