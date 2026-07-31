<?php

namespace App\Services\Storefront;

use App\Events\Storefront\CashbackCredited;
use App\Models\Order\Order;
use App\Models\Storefront\CashbackEarning;
use App\Models\Storefront\CashbackRedemption;
use App\Models\Tenant\TenantSettings;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Permission\PermissionService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Support\Facades\Auth;

/**
 * Cashback (roadmap Delivery, Fase 5) — crédito só ao PAGAR o pedido
 * (OrderService::pay(), sempre ação manual do staff, nunca webhook — ver
 * CreditCashbackOnOrderPaid), carência configurável por tenant antes do
 * saldo virar utilizável (cashback:process promove pending→available),
 * resgate drena os lotes que expiram primeiro (FIFO por expires_at).
 * Escopado por (tenant_id, final_customer_id) — nunca global entre lojas.
 */
class CashbackService
{
    public function __construct(
        private TenantSettingsService $tenantSettingsService,
        private PermissionService $permissionService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    /**
     * Saldo sempre DERIVADO do ledger (sem tabela de cache) — disponível
     * (utilizável agora), pendente (em carência) e o próximo lote a
     * expirar. Também devolve a config de ganho/resgate do tenant, pra o
     * checkout montar o preview de "você vai ganhar R$Y" sem precisar de um
     * endpoint separado.
     */
    public function getBalance(int $tenantId, int $finalCustomerId): array
    {
        $settings = $this->tenantSettingsService->getForTenant($tenantId);

        $availableAmount = (float) CashbackEarning::where('tenant_id', $tenantId)
            ->where('final_customer_id', $finalCustomerId)
            ->where('status', 'available')
            ->sum('remaining_amount');

        $pendingAmount = (float) CashbackEarning::where('tenant_id', $tenantId)
            ->where('final_customer_id', $finalCustomerId)
            ->where('status', 'pending')
            ->sum('remaining_amount');

        $nextExpiring = CashbackEarning::where('tenant_id', $tenantId)
            ->where('final_customer_id', $finalCustomerId)
            ->where('status', 'available')
            ->where('remaining_amount', '>', 0)
            ->orderBy('expires_at')
            ->first();

        return [
            'enabled' => (bool) $settings->cashback_enabled,
            // Nome customizado do programa (ex.: "eCash") ou "Cashback"
            // padrão — fallback resolvido SEMPRE no backend, o frontend
            // nunca decide.
            'program_name' => $settings->cashback_name ?: 'Cashback',
            'available_amount' => $availableAmount,
            'pending_amount' => $pendingAmount,
            'next_expiration' => $nextExpiring ? [
                'amount' => (float) $nextExpiring->remaining_amount,
                'expires_at' => $nextExpiring->expires_at,
            ] : null,
            'earn_percentage' => $settings->cashback_percentage,
            'earn_max_per_order' => $settings->cashback_max_per_order,
            'redeem_max_percentage' => $settings->cashback_redeem_max_percentage,
        ];
    }

    /**
     * Reserva até $requestedCents do saldo DISPONÍVEL do cliente, dentro da
     * MESMA transação do checkout — drena lotes `available` do que expira
     * primeiro pro que expira por último (FIFO por expires_at), 1
     * CashbackRedemption por lote tocado. Roda ANTES do Order existir (o
     * valor efetivamente reservado precisa ser conhecido pra calcular
     * orders.total_amount corretamente) — as linhas nascem com order_id
     * nulo, preenchido logo em seguida por attachRedemptionToOrder(). Nunca
     * confia em valor vindo do request: quem chama já deve ter capado
     * $requestedCents pelo teto de % do pedido, mas esta função por si só
     * nunca resgata mais do que o saldo real em banco permite (lockForUpdate
     * serializa contra um resgate concorrente do mesmo cliente).
     *
     * @return array{reserved_cents: int, redemption_ids: array<int, int>}
     */
    public function reserveRedemption(int $tenantId, int $finalCustomerId, int $requestedCents): array
    {
        if ($requestedCents <= 0) {
            return ['reserved_cents' => 0, 'redemption_ids' => []];
        }

        $remainingToReserve = $requestedCents;
        $totalReserved = 0;
        $redemptionIds = [];

        $earnings = CashbackEarning::where('tenant_id', $tenantId)
            ->where('final_customer_id', $finalCustomerId)
            ->where('status', 'available')
            ->where('remaining_amount', '>', 0)
            ->orderBy('expires_at')
            ->lockForUpdate()
            ->get();

        foreach ($earnings as $earning) {
            if ($remainingToReserve <= 0) {
                break;
            }

            $availableCents = (int) round((float) $earning->remaining_amount * 100);
            $takeCents = min($availableCents, $remainingToReserve);

            if ($takeCents <= 0) {
                continue;
            }

            $earning->remaining_amount = $this->centsToDecimal($availableCents - $takeCents);
            $earning->save();

            $redemption = CashbackRedemption::create([
                'tenant_id' => $tenantId,
                'final_customer_id' => $finalCustomerId,
                'order_id' => null,
                'cashback_earning_id' => $earning->id,
                'amount' => $this->centsToDecimal($takeCents),
                'redeemed_at' => now(),
            ]);
            $redemptionIds[] = $redemption->id;

            $remainingToReserve -= $takeCents;
            $totalReserved += $takeCents;
        }

        return ['reserved_cents' => $totalReserved, 'redemption_ids' => $redemptionIds];
    }

    /**
     * Preenche order_id nas linhas criadas por reserveRedemption() — chamado
     * logo depois de OrderService::create() suceder, mesma transação.
     *
     * @param array<int, int> $redemptionIds
     */
    public function attachRedemptionToOrder(array $redemptionIds, int $orderId): void
    {
        if (empty($redemptionIds)) {
            return;
        }

        CashbackRedemption::whereIn('id', $redemptionIds)->update(['order_id' => $orderId]);
    }

    /**
     * Chamado pelo listener em OrderPaid (CreditCashbackOnOrderPaid). Só
     * age para pedido origin='storefront', com cashback habilitado no
     * tenant e plano que permite a funcionalidade (defesa em profundidade —
     * StorefrontCheckoutService já checa o plano no resgate, aqui checa de
     * novo pro lado do crédito).
     *
     * Base de cálculo: total_amount - delivery_fee. Não subtrai
     * cashback_redeemed_amount de novo — total_amount JÁ é líquido dele
     * (OrderService::create() subtrai uma única vez), então total_amount -
     * delivery_fee já exclui corretamente tanto o frete quanto a parte paga
     * com cashback preexistente. Subtrair cashback_redeemed_amount aqui
     * seria contar esse desconto duas vezes.
     */
    public function creditEarning(Order $order): void
    {
        if ($order->origin !== 'storefront') {
            return;
        }

        $settings = $this->tenantSettingsService->getForTenant((int) $order->tenant_id);

        if (!$settings->cashback_enabled || $settings->cashback_percentage === null) {
            return;
        }

        if (!$this->permissionService->tenantPlanAllowsFunctionality((int) $order->tenant_id, 'cashback')) {
            return;
        }

        $link = $this->linkRepository->findConfirmedByTenantAndClient((int) $order->tenant_id, (int) $order->client_id);

        if (!$link) {
            return;
        }

        $totalCents = (int) round((float) $order->total_amount * 100);
        $deliveryFeeCents = (int) round((float) $order->delivery_fee * 100);
        $netBaseCents = max(0, $totalCents - $deliveryFeeCents);

        $earnedCents = (int) round($netBaseCents * ((float) $settings->cashback_percentage) / 100);

        if ($settings->cashback_max_per_order !== null) {
            $earnedCents = min($earnedCents, (int) round((float) $settings->cashback_max_per_order * 100));
        }

        if ($earnedCents <= 0) {
            return;
        }

        $now = now();
        $holdDays = $settings->cashback_hold_days ?? 0;
        $expirationDays = $settings->cashback_expiration_days ?? 90;

        // Sem DB::transaction() próprio: creditEarning() já roda dentro da
        // transação de OrderService::pay() (event() dispara os listeners de
        // OrderPaid de forma síncrona, ainda dentro dela).
        $earning = CashbackEarning::create([
            'tenant_id' => (int) $order->tenant_id,
            'final_customer_id' => $link->final_customer_id,
            'order_id' => $order->id,
            'amount' => $this->centsToDecimal($earnedCents),
            'remaining_amount' => $this->centsToDecimal($earnedCents),
            'status' => $holdDays > 0 ? 'pending' : 'available',
            'available_at' => $now->copy()->addDays($holdDays),
            'expires_at' => $now->copy()->addDays($expirationDays),
        ]);

        event(new CashbackCredited(
            cashbackEarningUuid: $earning->uuid,
            tenantId: (int) $order->tenant_id,
            finalCustomerId: (int) $link->final_customer_id,
            amountCents: $earnedCents,
            actorId: Auth::id(),
        ));
    }

    /**
     * Chamado pelo listener em OrderUnpaid (ReverseCashbackOnOrderUnpaid).
     * Localiza o(s) lote(s) criados por este pedido e os invalida
     * (status='reversed', remaining_amount=0). Limitação documentada: se um
     * lote já foi parcialmente gasto em OUTRO pedido antes do unpay(), esse
     * valor já usado não é cobrado de volta do cliente — não existe
     * reembolso/estorno de pagamento no sistema hoje, então esse cenário é
     * raro e fica fora do MVP.
     */
    public function reverseEarning(Order $order): void
    {
        CashbackEarning::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'available'])
            ->update(['status' => 'reversed', 'remaining_amount' => 0]);
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
