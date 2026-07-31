<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StorePushSubscriptionRequest;
use App\Services\APIResponse;
use App\Services\Storefront\PushSubscriptionService;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private PushSubscriptionService $service
    ) {
    }

    public function store(StorePushSubscriptionRequest $request)
    {
        $data = $request->validated();

        $this->service->subscribe(
            portal_customer()->id,
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth']
        );

        return APIResponse::success(
            null,
            __('messages.push.subscribed'),
            201
        );
    }
}
