<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderApproved;
use App\Models\Order\Order;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Web Push real (roadmap Delivery, Fase 4 — última fatia). Só age para
 * pedido origin='storefront' (pedido de staff não tem cliente final para
 * notificar). Resolve o FinalCustomer via vínculo CONFIRMADO
 * (tenant_id+client_id do pedido) — sem vínculo confirmado, silencioso
 * (não é erro). Envio de push em si nunca lança exceção (ver
 * PushNotificationService::notifyFinalCustomer).
 */
class SendPushOnOrderApproved
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(OrderApproved $event): void
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
            __('messages.push.order_approved_title'),
            __('messages.push.order_approved_body'),
            "/rastreio/{$order->uuid}"
        );
    }
}
