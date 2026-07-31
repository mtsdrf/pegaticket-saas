<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantDataExportService;

class TenantDataExportController extends Controller
{
    public function __construct(
        private TenantDataExportService $service
    ) {
    }

    /**
     * ZIP com 1 CSV por entidade principal do tenant (roadmap A1.2).
     * Download binário — mesmo padrão de streamDownload já usado pelos
     * exports PDF de ReportController, não o envelope APIResponse.
     */
    public function store()
    {
        $export = $this->service->export(app('tenant_id'));

        return response()->streamDownload(
            fn () => print($export['content']),
            $export['filename'],
            ['Content-Type' => 'application/zip']
        );
    }
}
