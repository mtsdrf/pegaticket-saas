<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleApproved;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Storefront\PushNotificationService;

/**
 * Web Push real (roadmap Delivery, Fase 4 — última fatia). Só age para
 * venda origin='storefront' (venda de staff não tem cliente final para
 * notificar). Resolve o FinalCustomer via vínculo CONFIRMADO
 * (tenant_id+final_customer_id da venda) — sem vínculo confirmado, silencioso
 * (não é erro). Envio de push em si nunca lança exceção (ver
 * PushNotificationService::notifyFinalCustomer).
 */
class SendPushOnSaleApproved
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function handle(SaleApproved $event): void
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
            __('messages.push.order_approved_title'),
            __('messages.push.order_approved_body'),
            "/compra/{$order->uuid}"
        );
    }
}
