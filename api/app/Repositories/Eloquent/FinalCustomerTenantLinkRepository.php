<?php

namespace App\Repositories\Eloquent;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FinalCustomerTenantLinkRepository implements FinalCustomerTenantLinkRepositoryInterface
{
    public function __construct(private FinalCustomerTenantLink $model)
    {
    }

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

        if (!empty($search)) {
            $term = $search;
            $query->where(function ($sub) use ($term) {
                $sub->where('final_customer_tenant_links.cpf_cnpj', 'like', '%' . $term . '%')
                    ->orWhere('final_customer_tenant_links.phone_primary', 'like', '%' . $term . '%')
                    ->orWhereHas('finalCustomer', function ($fc) use ($term) {
                        $fc->where('name', 'like', '%' . $term . '%')
                            ->orWhere('last_name', 'like', '%' . $term . '%')
                            ->orWhere('email', 'like', '%' . $term . '%');
                    });
            });
        }

        return $query
            ->orderByDesc('final_customer_tenant_links.id')
            ->paginate($perPage);
    }
}
