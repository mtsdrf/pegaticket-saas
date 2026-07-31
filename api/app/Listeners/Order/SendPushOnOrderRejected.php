<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderRejected;
use App\Models\Order\Order;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Ver SendPushOnOrderApproved para o padrão completo (origin/link
 * confirmado/silencioso). Mesma regra aplicada ao evento de recusa.
 */
class SendPushOnOrderRejected
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(OrderRejected $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

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
            "/rastreio/{$order->uuid}"
        );
    }
}
