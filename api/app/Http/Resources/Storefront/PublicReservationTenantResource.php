<?php

namespace App\Http\Resources\Storefront;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rota pública GET /reservas/{slug} — allow-list mínima para a página
 * pública de reservas, sem expor dados de catálogo/checkout quando a
 * empresa não possui o módulo de loja online no plano.
 */
class PublicReservationTenantResource extends JsonResource
{
    public function __construct(
        $resource,
        private bool $allowTableReservations = false,
        private bool $storefrontEnabled = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_url' => MediaUrl::resolvePublic(
                $this->logo_path,
                $this->logo_data ?? $this->logo_mime,
                '/api/v1/tenants/' . $this->uuid . '/logo',
                $this->logo_updated_at,
                'tenant'
            ),
            'allow_table_reservations' => $this->allowTableReservations,
            'storefront_enabled' => $this->storefrontEnabled,
        ];
    }
}
