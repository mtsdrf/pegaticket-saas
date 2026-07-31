<?php

namespace App\Http\Resources\Storefront;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rota pública GET /loja/{slug}/produtos — allow-list deliberada. NUNCA
 * reaproveitar ProductResource (expõe custo/estoque condicionalmente à
 * permissão de staff, não faz sentido aqui) nem adicionar campo novo
 * (stock_quantity, last_purchase_cost, sku, barcode, min_stock etc.) sem
 * confirmação explícita — é rota pública, todo campo extra é superfície de
 * vazamento.
 */
class StorefrontProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            // Atributo dinâmico atribuído por
            // StorefrontCatalogService::paginateProducts() (Delivery Fase
            // 3) — null quando o produto não tem promoção ativa no momento.
            'promo_price' => $this->promo_price !== null ? (float) $this->promo_price : null,
            // Preço de atacado por quantidade (roadmap Loja) — config de
            // tabela pública, ok expor sem autenticação. Os dois nulos =
            // sem atacado configurado.
            'wholesale_min_quantity' => $this->wholesale_min_quantity !== null ? (float) $this->wholesale_min_quantity : null,
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'image_url' => MediaUrl::resolvePublic(
                $this->image_path,
                $this->image_data ?? $this->image_mime,
                '/api/v1/products/' . $this->uuid . '/image',
                $this->image_updated_at,
                'product'
            ),
            'is_available' => $this->is_available,
            // Atributo dinâmico atribuído por
            // StorefrontCatalogService::paginateProducts() (gap-analysis de
            // catálogo, item 3) — array de 0 a N selos entre
            // 'new'|'best_selling'|'low_stock'. [] quando o produto vem de
            // um endpoint que não passa por paginateProducts() (ex: detalhe
            // avulso), nunca null.
            'badges' => $this->badges ?? [],
            // Atributo dinâmico atribuído por
            // StorefrontCatalogService::paginateProducts()/
            // ProductFavoriteService::list() (Delivery Fase 4) — só
            // calculado quando portal_customer() está populado
            // (customer.jwt.optional); default false para visitante
            // anônimo ou produto não favoritado.
            'is_favorited' => (bool) ($this->is_favorited ?? false),
            'option_groups' => $this->whenLoaded('optionGroups', fn() => $this->optionGroups
                ->filter(fn($group) => $group->is_active)
                ->map(fn($group) => [
                    'uuid' => $group->uuid,
                    'name' => $group->name,
                    'description' => $group->description,
                    'kind' => $group->kind,
                    'min_select' => $group->min_select,
                    'max_select' => $group->max_select,
                    'sort_order' => $group->sort_order,
                    'options' => $group->options
                        ->filter(fn($option) => $option->is_available)
                        ->map(fn($option) => [
                            'uuid' => $option->uuid,
                            'name' => $option->name,
                            'description' => $option->description,
                            'price' => (float) $option->price,
                            'sort_order' => $option->sort_order,
                        ])
                        ->values(),
                ])
                ->values()),
            'product_type' => $this->whenLoaded('productType', fn() => [
                'name' => $this->productType->name,
                'product_category' => $this->productType->relationLoaded('productCategory') && $this->productType->productCategory ? [
                    'name' => $this->productType->productCategory->name,
                ] : null,
            ]),
        ];
    }
}
