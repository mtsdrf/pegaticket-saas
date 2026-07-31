<?php

namespace App\Services\Storefront;

use App\Events\Storefront\StoreDeliveryFeeDeleted;
use App\Events\Storefront\StoreDeliveryFeeUpserted;
use App\Models\Location\Bairro;
use App\Models\Storefront\StoreDeliveryFee;
use App\Repositories\Contracts\StoreDeliveryFeeRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Taxa de entrega por bairro (roadmap Delivery, Fase 2) — CRUD normal,
 * diferente de StoreBusinessHoursService (lista de tamanho variável, não 7
 * linhas fixas). Bairro sem taxa cadastrada bloqueia a entrega no checkout
 * (findFee() retorna null, nunca um valor padrão/frete grátis implícito).
 */
class StoreDeliveryFeeService
{
    public function __construct(
        private StoreDeliveryFeeRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function upsert(int $tenantId, string $bairroUuid, float $fee): StoreDeliveryFee
    {
        return DB::transaction(function () use ($tenantId, $bairroUuid, $fee) {
            $bairro = Bairro::where('uuid', $bairroUuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $deliveryFee = $this->repository->upsertForTenant($tenantId, $bairro->id, $fee);

            event(new StoreDeliveryFeeUpserted(
                storeDeliveryFeeUuid: $deliveryFee->uuid,
                actorId: Auth::id()
            ));

            return $deliveryFee;
        });
    }

    public function delete(int $tenantId, string $uuid): void
    {
        DB::transaction(function () use ($tenantId, $uuid) {
            $fee = StoreDeliveryFee::where('uuid', $uuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $this->repository->delete($fee);

            event(new StoreDeliveryFeeDeleted(
                storeDeliveryFeeUuid: $uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Usado pelo guard 3 do checkout público — null quando o bairro não
     * tem taxa configurada pro tenant (bloqueia a entrega, nunca assume
     * frete grátis/padrão por omissão).
     */
    public function findFee(int $tenantId, string $bairroUuid): ?float
    {
        return $this->repository->findFeeForTenantAndBairro($tenantId, $bairroUuid);
    }
}
