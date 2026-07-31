<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\CashbackCredited;
use App\Services\Storefront\PushNotificationService;

/**
 * Mesmo padrão de SendPushOnOrderApproved — o evento já traz
 * final_customer_id resolvido (CashbackService::creditEarning() só chega a
 * criar o lote quando há vínculo confirmado), então aqui não precisa
 * resolver FinalCustomerTenantLink de novo.
 */
class SendPushOnCashbackCredited
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
    ) {
    }

    public function handle(CashbackCredited $event): void
    {
        $amount = number_format($event->amountCents / 100, 2, ',', '.');

        $this->pushNotificationService->notifyFinalCustomer(
            $event->finalCustomerId,
            __('messages.push.cashback_credited_title'),
            __('messages.push.cashback_credited_body', ['amount' => $amount]),
            '/portal/cashback'
        );
    }
}
