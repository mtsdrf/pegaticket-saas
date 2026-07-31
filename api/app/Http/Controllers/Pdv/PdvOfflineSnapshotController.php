<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Pdv\PdvOfflineSnapshotService;

class PdvOfflineSnapshotController extends Controller
{
    public function __construct(
        private PdvOfflineSnapshotService $service
    ) {
    }

    public function show()
    {
        return APIResponse::success(
            $this->service->build(app('tenant_id')),
            __('messages.pdv.offline_snapshot_ready')
        );
    }
}
