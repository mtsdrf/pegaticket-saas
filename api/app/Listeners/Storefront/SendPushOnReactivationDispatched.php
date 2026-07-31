<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\ReactivationDispatched;
use App\Services\Storefront\PushNotificationService;

class SendPushOnReactivationDispatched
{
    public function __construct(
        private PushNotificationService $pushNotificationService,
    ) {
    }

    public function handle(ReactivationDispatched $event): void
    {
        if ($event->finalCustomerId === null) {
            return;
        }

        $this->pushNotificationService->notifyFinalCustomer(
            $event->finalCustomerId,
            __('messages.push.reactivation_title'),
            __('messages.push.reactivation_body', ['code' => $event->couponCode]),
            '/portal'
        );
    }
}
