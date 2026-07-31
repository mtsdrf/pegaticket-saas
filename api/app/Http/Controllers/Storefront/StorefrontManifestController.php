<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontCatalogService;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

/**
 * PWA instalável por tenant (roadmap Delivery, Fase 1). Simplificação
 * assumida: sem ícone em múltiplos tamanhos/maskable, sem cor de tema por
 * tenant (reaproveita a paleta PegaTicket) — logos não quadrados podem ficar
 * com aparência ruim ao instalar, aceitável para esta fase. JSON cru
 * (NÃO envelopado em APIResponse::success()) — isso é um manifest, não uma
 * resposta de API.
 */
class StorefrontManifestController extends Controller
{
    public function __construct(
        private StorefrontCatalogService $service
    ) {
    }

    public function show(string $slug): JsonResponse
    {
        $tenant = $this->service->findTenantBySlug($slug);

        $iconUrl = MediaUrl::resolvePublic(
            $tenant->logo_path,
            $tenant->logo_data ?? null,
            '/api/v1/tenants/' . $tenant->uuid . '/logo',
            $tenant->logo_updated_at,
            'tenant'
        ) ?? '/android-chrome-512x512.png';

        return response()->json([
            'name' => $tenant->name,
            'short_name' => mb_substr((string) $tenant->name, 0, 12),
            'start_url' => '/loja/' . $tenant->slug,
            'scope' => '/loja/' . $tenant->slug,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0F3D5E',
            'icons' => [
                [
                    'src' => $iconUrl,
                    'sizes' => 'any',
                    'type' => $tenant->logo_mime ?? 'image/png',
                ],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
