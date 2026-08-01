<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;

/**
 * Ver App\Http\Controllers\User\UserAvatarController — mesmo padrão.
 */
class TenantLogoController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(Tenant $tenant): Response|RedirectResponse
    {
        return $this->mediaStorage->publicMediaResponse(
            $tenant->logo_path,
            $tenant->logo_data,
            $tenant->logo_mime,
            'tenant'
        );
    }
}
