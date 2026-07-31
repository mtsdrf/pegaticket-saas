<?php

namespace App\Repositories\Eloquent;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Support\Collection;

class FinalCustomerTenantLinkRepository implements FinalCustomerTenantLinkRepositoryInterface
{
    public function __construct(private FinalCustomerTenantLink $model)
    {
    }

    public function findByCustomerAndClient(int $finalCustomerId, int $clientId): ?FinalCustomerTenantLink
    {
        return $this->model
            ->where('final_customer_id', $finalCustomerId)
            ->where('client_id', $clientId)
            ->first();
    }

    public function findConfirmedByTenantAndClient(int $tenantId, int $clientId): ?FinalCustomerTenantLink
    {
        return $this->model
            ->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)
            ->whereNotNull('confirmed_at')
            ->first();
    }

    public function create(array $data): FinalCustomerTenantLink
    {
        return $this->model->create($data);
    }

    public function confirmedLinksFor(int $finalCustomerId): Collection
    {
        return $this->model
            ->where('final_customer_id', $finalCustomerId)
            ->whereNotNull('confirmed_at')
            ->with(['tenant', 'client'])
            ->get();
    }
}
