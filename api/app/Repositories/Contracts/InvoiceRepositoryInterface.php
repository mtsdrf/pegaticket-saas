<?php

namespace App\Repositories\Contracts;

use App\Models\Subscription\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InvoiceRepositoryInterface extends BaseRepositoryInterface
{
    public function listForSubscription(int $subscriptionId): Collection;

    /**
     * Histórico paginado de faturas do TENANT (todas as subscriptions dele,
     * não só a atual — invoices.tenant_id já é direto, sem precisar juntar
     * por subscription_id). Usado pela tela "Empresa" do proprietário.
     */
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Assinaturas cujo next_charge_at já venceu e que ainda geram fatura
     * (auto_renew=true, status cobrável). Retorna as próprias Subscriptions.
     *
     * @return Collection<int, Subscription>
     */
    public function subscriptionsDueForCharge(): Collection;
}
