<?php

namespace App\Services\Risk;

use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use Illuminate\Support\Collection;

/**
 * Motor de risco básico (roadmap Fase 7) — heurísticas simples e
 * auditáveis sobre o domínio de vendas/pagamentos já existente. Roda
 * sempre que uma venda é confirmada (SalePaid): marca `risk_flagged`+
 * `risk_reason` (ver SaleResource/SaleListResource) quando alguma das
 * heurísticas abaixo bate. A primeira que bater vence — não empilha
 * motivos.
 *
 * Heurísticas implementadas:
 * 1. Compras repetidas: mesmo FinalCustomer (ou mesmo e-mail) com mais de
 *    PURCHASE_COUNT_THRESHOLD compras pagas pro MESMO evento em
 *    WINDOW_HOURS horas — sinal clássico de revenda/scalping automatizado.
 * 2. Velocity de pagamento recusado/falho: mesmo FinalCustomer (ou
 *    e-mail) com mais de FAILED_PAYMENT_THRESHOLD tentativas de
 *    pagamento (`payments.status` recusado/falho) em
 *    FAILED_PAYMENT_WINDOW_MINUTES minutos — sinal clássico de card
 *    testing (teste de cartão roubado contra o checkout).
 * 3. Múltiplos cartões pro mesmo cliente: mesmo FinalCustomer (ou
 *    e-mail) usando mais de CARD_REUSE_THRESHOLD cartões diferentes
 *    (fingerprint aproximado = BIN + últimos 4 dígitos, únicos dados de
 *    cartão que o PagBank devolve pós-tokenização — nunca PAN/CVV) em
 *    CARD_VELOCITY_WINDOW_MINUTES minutos.
 * 4. Mesmo cartão em múltiplos clientes: o cartão usado na venda
 *    avaliada aparece em mais de CARD_REUSE_THRESHOLD FinalCustomers/
 *    e-mails diferentes dentro do tenant em CARD_VELOCITY_WINDOW_MINUTES
 *    minutos — sinal de cartão roubado sendo testado em múltiplas contas.
 *
 * Lacuna de dado conhecida: o PagBank (ver PagBankPaymentProvider::
 * callPagBank) só devolve `payment_method.card` com brand/first_digits/
 * last_digits (BIN + últimos 4) — não há token/fingerprint real de
 * cartão. As heurísticas 3 e 4 usam BIN+últimos-4 como fingerprint
 * aproximado (padrão comum de mercado quando não há fingerprint
 * dedicado); duas máquinas físicas distintas do mesmo BIN e final
 * poderiam colidir em teoria, risco aceito dado que é só um alerta
 * informativo, nunca bloqueio.
 *
 * É só um FLAG informativo pro staff revisar; NUNCA bloqueia ou cancela a
 * venda automaticamente — bloqueio automático é decisão de negócio que
 * exige validação do usuário, fora de escopo desta rodada.
 *
 * Defaults técnicos abaixo NÃO foram validados com o usuário.
 */
class RiskEngineService
{
    public const PURCHASE_COUNT_THRESHOLD = 3;

    public const WINDOW_HOURS = 6;

    public const FAILED_PAYMENT_THRESHOLD = 3;

    public const FAILED_PAYMENT_WINDOW_MINUTES = 15;

    public const CARD_REUSE_THRESHOLD = 3;

    public const CARD_VELOCITY_WINDOW_MINUTES = 60;

    private const FAILED_PAYMENT_STATUSES = ['failed', 'declined'];

    public function evaluateSalePaid(string $saleUuid): void
    {
        $sale = Sale::query()
            ->where('uuid', $saleUuid)
            ->with(['items.ticketType', 'items.eventProduct', 'finalCustomer'])
            ->first();

        if (! $sale || ! $sale->final_customer_id) {
            return;
        }

        $tenantId = (int) $sale->tenant_id;
        $finalCustomerId = (int) $sale->final_customer_id;
        $email = $sale->finalCustomer?->email;

        if ($this->evaluateRepeatedPurchases($sale, $tenantId, $finalCustomerId, $email)) {
            return;
        }

        if ($this->evaluateFailedPaymentVelocity($sale, $tenantId, $finalCustomerId, $email)) {
            return;
        }

        if ($this->evaluateMultipleCardsForCustomer($sale, $tenantId, $finalCustomerId, $email)) {
            return;
        }

        $this->evaluateCardSharedAcrossCustomers($sale, $tenantId, $finalCustomerId, $email);
    }

    private function evaluateRepeatedPurchases(Sale $sale, int $tenantId, int $finalCustomerId, ?string $email): bool
    {
        if ((float) $sale->total_amount === 0.0) {
            return false;
        }

        $eventIds = $sale->items
            ->map(fn ($item) => $item->ticketType?->event_id ?? $item->eventProduct?->event_id)
            ->filter()
            ->unique()
            ->values();

        foreach ($eventIds as $eventId) {
            $count = $this->countRecentPaidSalesForEvent($tenantId, (int) $eventId, $finalCustomerId, $email);

            if ($count >= self::PURCHASE_COUNT_THRESHOLD) {
                return $this->flag($sale, __('messages.sale.risk_multiple_purchases_reason', [
                    'count' => $count,
                    'hours' => self::WINDOW_HOURS,
                ]));
            }
        }

        return false;
    }

    private function evaluateFailedPaymentVelocity(Sale $sale, int $tenantId, int $finalCustomerId, ?string $email): bool
    {
        $count = $this->countRecentFailedPayments($tenantId, $finalCustomerId, $email);

        if ($count < self::FAILED_PAYMENT_THRESHOLD) {
            return false;
        }

        return $this->flag($sale, __('messages.sale.risk_failed_payment_velocity_reason', [
            'count' => $count,
            'minutes' => self::FAILED_PAYMENT_WINDOW_MINUTES,
        ]));
    }

    private function evaluateMultipleCardsForCustomer(Sale $sale, int $tenantId, int $finalCustomerId, ?string $email): bool
    {
        $distinctCards = $this->recentCardFingerprintsForCustomer($tenantId, $finalCustomerId, $email)->count();

        if ($distinctCards < self::CARD_REUSE_THRESHOLD) {
            return false;
        }

        return $this->flag($sale, __('messages.sale.risk_multiple_cards_reason', [
            'count' => $distinctCards,
            'minutes' => self::CARD_VELOCITY_WINDOW_MINUTES,
        ]));
    }

    private function evaluateCardSharedAcrossCustomers(Sale $sale, int $tenantId, int $finalCustomerId, ?string $email): bool
    {
        $fingerprint = $this->cardFingerprintForSale($sale);

        if ($fingerprint === null) {
            return false;
        }

        $distinctCustomers = $this->recentDistinctCustomersForCard($tenantId, $fingerprint, $finalCustomerId, $email);

        if ($distinctCustomers < self::CARD_REUSE_THRESHOLD) {
            return false;
        }

        return $this->flag($sale, __('messages.sale.risk_card_shared_across_customers_reason', [
            'count' => $distinctCustomers,
            'minutes' => self::CARD_VELOCITY_WINDOW_MINUTES,
        ]));
    }

    private function flag(Sale $sale, string $reason): bool
    {
        $sale->forceFill([
            'risk_flagged' => true,
            'risk_reason' => $reason,
        ])->save();

        return true;
    }

    private function countRecentPaidSalesForEvent(int $tenantId, int $eventId, int $finalCustomerId, ?string $email): int
    {
        return Sale::query()
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.is_paid', true)
            ->whereNull('sales.cancelled_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.paid_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->where(function ($query) use ($finalCustomerId, $email) {
                $query->where('sales.final_customer_id', $finalCustomerId);

                if ($email) {
                    $query->orWhereHas('finalCustomer', fn ($fc) => $fc->where('email', $email));
                }
            })
            ->whereHas('items', function ($query) use ($eventId) {
                $query->where(function ($item) use ($eventId) {
                    $item->whereHas('ticketType', fn ($tt) => $tt->where('event_id', $eventId))
                        ->orWhereHas('eventProduct', fn ($ep) => $ep->where('event_id', $eventId));
                });
            })
            ->distinct('sales.id')
            ->count('sales.id');
    }

    private function countRecentFailedPayments(int $tenantId, int $finalCustomerId, ?string $email): int
    {
        return $this->tenantSalePaymentsQuery($tenantId, $finalCustomerId, $email)
            ->whereIn('payments.status', self::FAILED_PAYMENT_STATUSES)
            ->where('payments.created_at', '>=', now()->subMinutes(self::FAILED_PAYMENT_WINDOW_MINUTES))
            ->distinct('payments.id')
            ->count('payments.id');
    }

    /**
     * @return Collection<int, string>
     */
    private function recentCardFingerprintsForCustomer(int $tenantId, int $finalCustomerId, ?string $email): Collection
    {
        return $this->tenantSalePaymentsQuery($tenantId, $finalCustomerId, $email)
            ->where('payments.created_at', '>=', now()->subMinutes(self::CARD_VELOCITY_WINDOW_MINUTES))
            ->select('payments.metadata')
            ->get()
            ->map(fn ($payment) => $this->cardFingerprint($payment->metadata))
            ->filter()
            ->unique()
            ->values();
    }

    private function cardFingerprintForSale(Sale $sale): ?string
    {
        $payment = Payment::query()
            ->whereNull('deleted_at')
            ->where('payable_type', Sale::class)
            ->where('payable_id', $sale->id)
            ->where('status', 'paid')
            ->orderByDesc('id')
            ->first();

        return $payment ? $this->cardFingerprint($payment->metadata) : null;
    }

    private function recentDistinctCustomersForCard(int $tenantId, string $fingerprint, int $finalCustomerId, ?string $email): int
    {
        $rows = Payment::query()
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', '=', Sale::class);
            })
            ->whereNull('payments.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.tenant_id', $tenantId)
            ->whereNotNull('sales.final_customer_id')
            ->where('payments.created_at', '>=', now()->subMinutes(self::CARD_VELOCITY_WINDOW_MINUTES))
            ->select('payments.metadata', 'sales.final_customer_id')
            ->get();

        return $rows
            ->filter(fn ($row) => $this->cardFingerprint($row->metadata) === $fingerprint)
            ->pluck('final_customer_id')
            ->unique()
            ->count();
    }

    /**
     * Query base: pagamentos (qualquer status) de vendas do tenant
     * pertencentes ao mesmo FinalCustomer (por id OU e-mail).
     */
    private function tenantSalePaymentsQuery(int $tenantId, int $finalCustomerId, ?string $email)
    {
        return Payment::query()
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', '=', Sale::class);
            })
            ->leftJoin('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->whereNull('payments.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.tenant_id', $tenantId)
            ->where(function ($query) use ($finalCustomerId, $email) {
                $query->where('sales.final_customer_id', $finalCustomerId);

                if ($email) {
                    $query->orWhere('final_customers.email', $email);
                }
            });
    }

    /**
     * Fingerprint aproximado do cartão a partir do que o PagBank devolve
     * pós-tokenização (`payment_method.card.first_digits`/`last_digits`,
     * ver PagBankPaymentProvider::callPagBank) — nunca PAN/CVV completo.
     * Retorna null quando o pagamento não é de cartão ou o provider não
     * devolveu esses campos (ex.: Pix, stub sem credenciais, falha de rede).
     */
    private function cardFingerprint(?array $metadata): ?string
    {
        $card = $metadata['card'] ?? null;

        if (! is_array($card)) {
            return null;
        }

        $first = trim((string) ($card['first_digits'] ?? ''));
        $last = trim((string) ($card['last_digits'] ?? ''));

        if ($first === '' || $last === '') {
            return null;
        }

        return $first.'-'.$last;
    }
}
