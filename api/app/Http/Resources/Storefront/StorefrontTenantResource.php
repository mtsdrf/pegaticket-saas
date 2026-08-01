<?php

namespace App\Http\Resources\Storefront;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rota pública GET /loja/{slug} — allow-list deliberada, mesmo espírito de
 * SalePublicTrackingResource: NUNCA adicionar campo novo (uuid interno,
 * plan_id, next_order_code, etc.) sem confirmação explícita — é rota
 * pública, todo campo extra é superfície de vazamento.
 *
 * estimated_preparation_minutes (roadmap Delivery, Fase 2) não é atributo
 * do Model Tenant — vem de TenantSettings e é passado explicitamente pelo
 * Controller via construtor, para o catálogo já exibir o tempo estimado
 * sem round-trip extra.
 */
class StorefrontTenantResource extends JsonResource
{
    public function __construct(
        $resource,
        private ?int $estimatedPreparationMinutes = null,
        private ?float $averageRating = null,
        private int $ratingsCount = 0,
        private ?array $acceptedPaymentMethods = null,
        private bool $storefrontEnabled = true,
        private string $catalogLayout = 'list',
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
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
            'estimated_preparation_minutes' => $this->estimatedPreparationMinutes,
            // Agregado de SaleRatingService::tenantSummary() (Delivery Fase
            // 4) — average_rating null quando o tenant ainda não tem
            // nenhuma avaliação (nunca 0.0).
            'average_rating' => $this->averageRating,
            'ratings_count' => $this->ratingsCount,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'mobile_phone' => $this->resource->mobile_phone,
            'whatsapp' => $this->resource->whatsapp,
            'instagram' => $this->resource->instagram,
            'facebook' => $this->resource->facebook,
            'address' => null,
            'address_lat' => null,
            'address_lng' => null,
            'accepted_payment_methods' => $this->acceptedPaymentMethods ?? [],
            'storefront_enabled' => $this->storefrontEnabled,
            'catalog_layout' => $this->catalogLayout,
        ];
    }
}
