<?php

namespace App\Repositories\Eloquent;

use App\Models\Subscription\Invoice;
use App\Models\Subscription\Subscription;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    public function listForSubscription(int $subscriptionId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('subscription_id', $subscriptionId)
            ->orderByDesc('id')
            ->get();
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function subscriptionsDueForCharge(): Collection
    {
        // Assinaturas com preapproval_id (cobrança real via Mercado Pago)
        // são excluídas: o avanço de período e a emissão de fatura delas é
        // inteiramente dirigido pelo webhook de Preapproval
        // (SubscriptionService::confirmCyclePayment), não por este cron —
        // evita fatura duplicada/dessincronizada da cobrança real do PSP.
        return Subscription::query()
            ->whereNull('deleted_at')
            ->where('auto_renew', true)
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->whereIn('status', ['active', 'past_due'])
            ->whereNull('preapproval_id')
            ->get();
    }
}
