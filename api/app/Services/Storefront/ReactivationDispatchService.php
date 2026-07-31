<?php

namespace App\Services\Storefront;

use App\Events\Storefront\ReactivationDispatched;
use App\Models\Storefront\ReactivationDispatch;
use App\Models\Storefront\ReactivationRule;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\ReactivationRuleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Processamento da régua de reativação (roadmap A5, item 18), usado pelo
 * comando agendado reactivation:process. Para cada tenant com regra ativa:
 * 1) acha clientes com último pedido (não cancelado) mais antigo que
 *    days_without_order; 2) filtra quem já tem um cupom de reativação
 *    ainda não expirado (cooldown, evita spam); 3) resolve o
 *    FinalCustomer vinculado (confirmado) ao cliente — sem vínculo não há
 *    como notificar, o cliente é pulado (não gera cupom órfão, fica
 *    elegível de novo na próxima execução); 4) gera o cupom (via
 *    CouponRepositoryInterface direto, não CouponService::create() —
 *    CouponService::create() dispara CouponCreated, que exige actorId
 *    int não-nulo porque é sempre chamado a partir de um Controller
 *    autenticado; aqui o ator é o sistema, sem usuário logado, mesmo
 *    desvio já usado por CashbackService/eventos automáticos do projeto)
 *    + dispara ReactivationDispatched (audita e envia o push, via
 *    PushNotificationService, reaproveitado sem mudança).
 */
class ReactivationDispatchService
{
    public function __construct(
        private ReactivationRuleRepositoryInterface $ruleRepository,
        private CouponRepositoryInterface $couponRepository,
    ) {
    }

    public function processAll(): int
    {
        $dispatched = 0;

        foreach ($this->ruleRepository->listActive() as $rule) {
            $dispatched += $this->processTenant($rule);
        }

        return $dispatched;
    }

    private function processTenant(ReactivationRule $rule): int
    {
        $tenantId = (int) $rule->tenant_id;
        $threshold = now()->subDays($rule->days_without_order);

        $candidateClientIds = DB::table('clients')
            ->join('orders', 'orders.client_id', '=', 'clients.id')
            ->where('clients.tenant_id', $tenantId)
            ->whereNull('clients.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->groupBy('clients.id')
            ->havingRaw('MAX(orders.created_at) <= ?', [$threshold->toDateTimeString()])
            ->pluck('clients.id');

        if ($candidateClientIds->isEmpty()) {
            return 0;
        }

        // Cooldown: cliente com cupom de reativação ainda não expirado não
        // recebe outro (evita spam de vários cupons acumulados).
        $cooldownClientIds = DB::table('reactivation_dispatches as rd')
            ->join('coupons as c', 'c.id', '=', 'rd.coupon_id')
            ->where('rd.tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('c.expires_at')->orWhere('c.expires_at', '>', now());
            })
            ->pluck('rd.client_id');

        $eligibleClientIds = $candidateClientIds->diff($cooldownClientIds);

        $dispatched = 0;

        foreach ($eligibleClientIds as $clientId) {
            if ($this->dispatchForClient($rule, (int) $clientId)) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    private function dispatchForClient(ReactivationRule $rule, int $clientId): bool
    {
        $finalCustomerId = DB::table('final_customer_tenant_links')
            ->where('tenant_id', $rule->tenant_id)
            ->where('client_id', $clientId)
            ->whereNotNull('confirmed_at')
            ->value('final_customer_id');

        if (!$finalCustomerId) {
            return false;
        }

        DB::transaction(function () use ($rule, $clientId, $finalCustomerId) {
            $code = 'REATIV-' . Str::upper(Str::random(8));

            $coupon = $this->couponRepository->create([
                'tenant_id' => $rule->tenant_id,
                'code' => $code,
                'type' => $rule->coupon_type,
                'value' => $rule->coupon_value,
                'minimum_order_value' => null,
                'max_uses_total' => 1,
                'max_uses_per_customer' => 1,
                'starts_at' => null,
                'expires_at' => now()->addDays($rule->coupon_validity_days),
                'is_active' => true,
            ]);

            $dispatch = ReactivationDispatch::create([
                'tenant_id' => $rule->tenant_id,
                'client_id' => $clientId,
                'coupon_id' => $coupon->id,
                'dispatched_at' => now(),
            ]);

            event(new ReactivationDispatched(
                reactivationDispatchUuid: $dispatch->uuid,
                tenantId: (int) $rule->tenant_id,
                clientId: $clientId,
                finalCustomerId: (int) $finalCustomerId,
                couponCode: $code,
            ));
        });

        return true;
    }
}
