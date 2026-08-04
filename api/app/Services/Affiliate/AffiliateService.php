<?php

namespace App\Services\Affiliate;

use App\DTOs\Affiliate\CreateAffiliateDTO;
use App\DTOs\Affiliate\UpdateAffiliateDTO;
use App\Events\Affiliate\AffiliateCreated;
use App\Events\Affiliate\AffiliateUpdated;
use App\Models\Affiliate\Affiliate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 (roadmap 2026-08-02), primeira fatia — CRUD de afiliado/promotor
 * tenant-scoped. Comissão em si (cálculo/registro) fica em
 * AffiliateCommissionService, disparado por SalePaid — este service só
 * cuida do cadastro.
 */
class AffiliateService
{
    public function create(int $tenantId, CreateAffiliateDTO $dto): Affiliate
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $affiliate = Affiliate::create([
                'tenant_id' => $tenantId,
                'name' => $dto->name,
                'email' => $dto->email,
                'tracking_code' => $dto->trackingCode,
                'commission_percentage' => $dto->commissionPercentage,
                'status' => $dto->status,
            ]);

            event(new AffiliateCreated(
                affiliateUuid: $affiliate->uuid,
                actorId: Auth::id(),
            ));

            return $affiliate;
        });
    }

    public function update(int $tenantId, string $uuid, UpdateAffiliateDTO $dto): Affiliate
    {
        return DB::transaction(function () use ($tenantId, $uuid, $dto) {
            $affiliate = $this->find($tenantId, $uuid);

            $affiliate->fill([
                'name' => $dto->name,
                'email' => $dto->email,
                'tracking_code' => $dto->trackingCode,
                'commission_percentage' => $dto->commissionPercentage,
                'status' => $dto->status,
            ])->save();

            event(new AffiliateUpdated(
                affiliateUuid: $affiliate->uuid,
                actorId: Auth::id(),
            ));

            return $affiliate;
        });
    }

    public function paginate(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Affiliate::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->withSum('commissions as commissions_total_amount', 'amount')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(int $tenantId, string $uuid): Affiliate
    {
        return Affiliate::where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function findActiveByTrackingCode(int $tenantId, string $trackingCode): ?Affiliate
    {
        return Affiliate::where('tenant_id', $tenantId)
            ->where('tracking_code', $trackingCode)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();
    }

    public function paginateCommissions(int $tenantId, string $uuid, int $perPage = 15): LengthAwarePaginator
    {
        $affiliate = $this->find($tenantId, $uuid);

        return $affiliate->commissions()
            ->whereNull('deleted_at')
            ->with('sale')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
