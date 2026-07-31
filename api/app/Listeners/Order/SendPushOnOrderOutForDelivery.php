<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderOutForDelivery;
use App\Models\Order\Order;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Mesmo padrão de SendPushOnOrderApproved — só age para pedido
 * origin='storefront', silencioso sem vínculo confirmado.
 */
class SendPushOnOrderOutForDelivery
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(OrderOutForDelivery $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order || $order->origin !== 'storefront') {
            return;
        }

        $link = $this->linkRepository->findConfirmedByTenantAndClient(
            (int) $order->tenant_id,
            (int) $order->client_id
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
