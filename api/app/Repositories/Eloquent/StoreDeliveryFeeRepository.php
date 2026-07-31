<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\StoreDeliveryFee;
use App\Repositories\Contracts\StoreDeliveryFeeRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class StoreDeliveryFeeRepository extends BaseRepository implements StoreDeliveryFeeRepositoryInterface
{
    public function __construct(StoreDeliveryFee $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->with('bairro')
            ->orderBy('id')
            ->get();
    }

    public function upsertForTenant(int $tenantId, int $bairroId, float $fee): StoreDeliveryFee
    {
        // withTrashed(): evita colidir com a unique (tenant_id, bairro_id)
        // quando a taxa deste bairro já foi removida (soft delete) e está
        // sendo recadastrada — restaura a linha em vez de tentar inserir
        // uma segunda.
        $existing = $this->model->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('bairro_id', $bairroId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fee = $fee;
            $existing->save();

            return $existing->fresh('bairro');
        }

        try {
            $created = $this->model->create([
                'tenant_id' => $tenantId,
                'bairro_id' => $bairroId,
                'fee' => $fee,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            // Achado de code review: 2 POSTs concorrentes cadastrando taxa
            // pro mesmo bairro pela primeira vez podiam colidir na unique
            // (tenant_id, bairro_id) — mesmo tratamento de corrida já
            // aplicado em StorefrontCheckoutService::resolveClientUuid() e
            // StoreBusinessHourRepository::upsertForTenant(). A requisição
            // perdedora só atualiza o valor que a vencedora acabou de
            // criar, em vez de estourar 500.
            $winner = $this->model->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('bairro_id', $bairroId)
                ->firstOrFail();

            if ($winner->trashed()) {
                $winner->restore();
            }

            $winner->fee = $fee;
            $winner->save();

            return $winner->fresh('bairro');
        }

        return $created->fresh('bairro');
    }

    public function findFeeForTenantAndBairro(int $tenantId, string $bairroUuid): ?float
    {
        $fee = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereHas('bairro', fn($q) => $q->where('uuid', $bairroUuid)->whereNull('deleted_at'))
            ->value('fee');

        return $fee !== null ? (float) $fee : null;
    }
}
