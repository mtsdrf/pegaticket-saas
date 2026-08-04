<?php

namespace App\Repositories\Eloquent;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinalCustomerTenantLinkRepository implements FinalCustomerTenantLinkRepositoryInterface
{
    public function __construct(private FinalCustomerTenantLink $model) {}

    public function findByCustomerAndTenant(int $finalCustomerId, int $tenantId): ?FinalCustomerTenantLink
    {
        return $this->model
            ->where('final_customer_id', $finalCustomerId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function findConfirmedByTenantAndFinalCustomer(int $tenantId, int $finalCustomerId): ?FinalCustomerTenantLink
    {
        return $this->model
            ->where('tenant_id', $tenantId)
            ->where('final_customer_id', $finalCustomerId)
            ->whereNotNull('confirmed_at')
            ->first();
    }

    public function create(array $data): FinalCustomerTenantLink
    {
        return $this->model->create($data);
    }

    public function updateOrCreateLink(int $finalCustomerId, int $tenantId, array $data): FinalCustomerTenantLink
    {
        return $this->model->updateOrCreate(
            ['final_customer_id' => $finalCustomerId, 'tenant_id' => $tenantId],
            $data
        );
    }

    public function confirmedLinksFor(int $finalCustomerId): Collection
    {
        return $this->model
            ->where('final_customer_id', $finalCustomerId)
            ->whereNotNull('confirmed_at')
            ->with(['tenant'])
            ->get();
    }

    public function searchActiveForTenant(int $tenantId, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->model
            ->where('final_customer_tenant_links.tenant_id', $tenantId)
            ->where('final_customer_tenant_links.is_active', true)
            ->with('finalCustomer');

        if (! empty($search)) {
            $term = $search;
            $query->where(function ($sub) use ($term) {
                $sub->where('final_customer_tenant_links.cpf_cnpj', 'like', '%'.$term.'%')
                    ->orWhere('final_customer_tenant_links.phone_primary', 'like', '%'.$term.'%')
                    ->orWhereHas('finalCustomer', function ($fc) use ($term) {
                        $fc->where('name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%');
                    });
            });
        }

        return $query
            ->orderByDesc('final_customer_tenant_links.id')
            ->paginate($perPage);
    }

    public function crmSummaryForTenant(
        int $tenantId,
        ?string $search,
        ?float $minSpent,
        ?int $minPurchases,
        ?int $inactiveDays,
        int $perPage
    ): LengthAwarePaginator {
        $query = DB::table('final_customer_tenant_links as fctl')
            ->join('final_customers as fc', 'fc.id', '=', 'fctl.final_customer_id')
            ->leftJoin('sales as s', function ($join) {
                $join->on('s.final_customer_id', '=', 'fctl.final_customer_id')
                    ->on('s.tenant_id', '=', 'fctl.tenant_id')
                    ->where('s.is_paid', true)
                    ->whereNull('s.cancelled_at')
                    ->whereNull('s.deleted_at');
            })
            ->where('fctl.tenant_id', $tenantId)
            ->where('fctl.is_active', true)
            ->select([
                'fctl.uuid as link_uuid',
                'fc.uuid as final_customer_uuid',
                'fc.name',
                'fc.last_name',
                'fc.email',
                'fctl.phone_primary',
                DB::raw('COALESCE(SUM(s.total_amount), 0) as total_spent'),
                DB::raw('COUNT(s.id) as purchase_count'),
                DB::raw('MAX(s.paid_at) as last_purchase_at'),
            ])
            ->groupBy('fctl.id', 'fctl.uuid', 'fc.uuid', 'fc.name', 'fc.last_name', 'fc.email', 'fctl.phone_primary');

        if (! empty($search)) {
            $term = '%'.$search.'%';
            $query->where(function ($sub) use ($term) {
                $sub->where('fctl.cpf_cnpj', 'like', $term)
                    ->orWhere('fctl.phone_primary', 'like', $term)
                    ->orWhere('fc.name', 'like', $term)
                    ->orWhere('fc.last_name', 'like', $term)
                    ->orWhere('fc.email', 'like', $term);
            });
        }

        // CAST(... AS REAL)/AS TEXT nos dois lados — achado real (2026-08):
        // no driver SQLite (usado pelos testes), o PDO liga um `float` do
        // PHP como parâmetro com storage class TEXT; comparado direto
        // contra o alias agregado (storage class NUMERIC), SQLite classifica
        // TEXT > NUMERIC e a HAVING nunca casa (0 linhas, silencioso, sem
        // erro). CAST explícito nos dois lados neutraliza a affinity
        // mismatch tanto no SQLite quanto no MySQL de produção.
        if ($minSpent !== null) {
            $query->havingRaw('CAST(total_spent AS REAL) >= CAST(? AS REAL)', [$minSpent]);
        }

        if ($minPurchases !== null) {
            $query->havingRaw('CAST(purchase_count AS INTEGER) >= CAST(? AS INTEGER)', [$minPurchases]);
        }

        if ($inactiveDays !== null) {
            $query->whereNotNull('s.paid_at')
                ->havingRaw('CAST(last_purchase_at AS TEXT) <= CAST(? AS TEXT)', [now()->subDays($inactiveDays)]);
        }

        return $query
            ->orderByDesc('last_purchase_at')
            ->paginate($perPage);
    }
}
