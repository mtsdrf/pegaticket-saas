<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderDelivered;
use App\Models\Order\Order;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Ver SendPushOnOrderApproved para o padrão completo (origin/link
 * confirmado/silencioso). Mesma regra aplicada ao evento de entrega.
 */
class SendPushOnOrderDelivered
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(OrderDelivered $event): void
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
            __('messages.push.order_delivered_title'),
            __('messages.push.order_delivered_body'),
            "/rastreio/{$order->uuid}"
        );
    }
}
