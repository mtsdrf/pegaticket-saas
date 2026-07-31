<?php

namespace App\Http\Controllers\Balcao;

use App\Exceptions\Balcao\TableWaitlistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Balcao\CancelTableWaitlistRequest;
use App\Http\Requests\Balcao\SeatTableWaitlistRequest;
use App\Http\Requests\Balcao\StoreTableWaitlistRequest;
use App\Http\Resources\Balcao\TableWaitlistResource;
use App\Services\APIResponse;
use App\Services\Balcao\TableWaitlistService;

class TableWaitlistController extends Controller
{
    public function __construct(
        private TableWaitlistService $service,
    ) {
    }

    public function index()
    {
        return APIResponse::success(
            TableWaitlistResource::collection($this->service->list(app('tenant_id'))),
            __('messages.table_waitlist.list')
        );
    }

    public function store(StoreTableWaitlistRequest $request)
    {
        return APIResponse::success(
            new TableWaitlistResource($this->service->create(app('tenant_id'), $request->validated())),
            __('messages.table_waitlist.created'),
            201
        );
    }

    public function call(string $uuid)
    {
        try {
            $entry = $this->service->call(app('tenant_id'), $uuid);
        } catch (TableWaitlistException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_WAITLIST_ERROR');
        }

        return APIResponse::success(
            new TableWaitlistResource($entry),
            __('messages.table_waitlist.called')
        );
    }

    public function seat(string $uuid, SeatTableWaitlistRequest $request)
    {
        try {
            $entry = $this->service->seat(app('tenant_id'), $uuid, $request->validated());
        } catch (TableWaitlistException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_WAITLIST_ERROR');
        }

        return APIResponse::success(
            new TableWaitlistResource($entry),
            __('messages.table_waitlist.seated')
        );
    }

    public function cancel(string $uuid, CancelTableWaitlistRequest $request)
    {
        try {
            $entry = $this->service->cancel(app('tenant_id'), $uuid, (string) $request->validated('reason'));
        } catch (TableWaitlistException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_WAITLIST_ERROR');
        }

        return APIResponse::success(
            new TableWaitlistResource($entry),
            __('messages.table_waitlist.cancelled')
        );
    }
}
