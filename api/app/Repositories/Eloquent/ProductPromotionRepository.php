<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\ProductPromotion;
use App\Repositories\Contracts\ProductPromotionRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class ProductPromotionRepository extends BaseRepository implements ProductPromotionRepositoryInterface
{
    public function __construct(ProductPromotion $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->with('ticketType')
            ->orderBy('id')
            ->get();
    }

    public function upsertForTenant(
        int $tenantId,
        int $ticketTypeId,
        ?float $promoPrice,
        ?string $startsAt,
        ?string $expiresAt,
        string $discountType = 'fixed_price',
        ?float $discountPercentage = null
    ): ProductPromotion {
        // withTrashed(): evita colidir com a unique (tenant_id, ticket_type_id)
        // quando a promoção deste produto já foi removida (soft delete) e
        // está sendo recadastrada — restaura a linha em vez de tentar
        // inserir uma segunda. Mesmo cuidado de
        // StoreDeliveryFeeRepository::upsertForTenant().
        $existing = $this->model->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketTypeId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->promo_price = $promoPrice;
            $existing->discount_type = $discountType;
            $existing->discount_percentage = $discountPercentage;
            $existing->starts_at = $startsAt;
            $existing->expires_at = $expiresAt;
            $existing->is_active = true;
            $existing->save();

            return $existing->fresh('ticketType');
        }

        try {
            $created = $this->model->create([
                'tenant_id' => $tenantId,
                'ticket_type_id' => $ticketTypeId,
                'promo_price' => $promoPrice,
                'discount_type' => $discountType,
                'discount_percentage' => $discountPercentage,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            // Achado de code review da Fase 2 (StoreDeliveryFeeRepository),
            // aplicado aqui desde o início: 2 POSTs concorrentes cadastrando
            // promoção pro mesmo produto pela primeira vez podiam colidir na
            // unique (tenant_id, ticket_type_id). A requisição perdedora só
            // atualiza o valor que a vencedora acabou de criar, em vez de
            // estourar 500.
            $winner = $this->model->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('ticket_type_id', $ticketTypeId)
                ->firstOrFail();

            if ($winner->trashed()) {
                $winner->restore();
            }

            $winner->promo_price = $promoPrice;
            $winner->discount_type = $discountType;
            $winner->discount_percentage = $discountPercentage;
            $winner->starts_at = $startsAt;
            $winner->expires_at = $expiresAt;
            $winner->is_active = true;
            $winner->save();

            return $winner->fresh('ticketType');
        }

        return $created->fresh('ticketType');
    }

    public function activePromotionsForProducts(int $tenantId, array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $now = now();

        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('ticket_type_id', $productIds)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->get()
            ->keyBy('ticket_type_id');
    }

    public function findActivePromotion(int $tenantId, int $ticketTypeId): ?ProductPromotion
    {
        $now = now();

        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->first();
    }
}
