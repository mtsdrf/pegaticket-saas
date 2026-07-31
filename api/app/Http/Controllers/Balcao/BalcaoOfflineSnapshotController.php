<?php

namespace App\Http\Controllers\Balcao;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Balcao\BalcaoOfflineSnapshotService;

class BalcaoOfflineSnapshotController extends Controller
{
    public function __construct(
        private BalcaoOfflineSnapshotService $service
    ) {
    }

    public function show()
    {
        return APIResponse::success(
            $this->service->build(app('tenant_id')),
            __('messages.comanda.offline_snapshot_ready')
        );
    }
}
