<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\EventFinancialCloseoutResource;
use App\Models\Event\Event;
use App\Services\APIResponse;
use App\Services\Finance\EventFinancialCloseoutService;

class EventFinancialCloseoutController extends Controller
{
    public function __construct(
        private EventFinancialCloseoutService $service,
    ) {}

    public function show(Event $event)
    {
        return APIResponse::success(
            new EventFinancialCloseoutResource($this->service->build(app('tenant_id'), $event)),
            __('messages.finance.event_closeout_loaded')
        );
    }

    public function bordereau(Event $event)
    {
        $export = $this->service->exportCsv(app('tenant_id'), $event);

        return response()->streamDownload(
            fn () => print ($export['content']),
            $export['filename'],
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
