<?php

namespace App\Services\Risk;

use App\Models\Sale\Sale;

/**
 * Motor de risco básico (roadmap Fase 7) — heurística simples e
 * auditável sobre o domínio de vendas já existente. Roda sempre que uma
 * venda é confirmada (SalePaid): marca `risk_flagged`+`risk_reason`
 * quando o mesmo FinalCustomer (ou mesmo e-mail) fez mais de
 * PURCHASE_COUNT_THRESHOLD compras pagas pro MESMO evento nas últimas
 * WINDOW_HOURS horas — sinal clássico de revenda/scalping automatizado.
 *
 * É só um FLAG informativo pro staff revisar (ver SaleResource/
 * SaleListResource); NUNCA bloqueia ou cancela a venda automaticamente —
 * bloqueio automático é decisão de negócio que exige validação do
 * usuário, fora de escopo desta rodada.
 *
 * Defaults técnicos abaixo NÃO foram validados com o usuário.
 */
class RiskEngineService
{
    public const PURCHASE_COUNT_THRESHOLD = 3;

    public const WINDOW_HOURS = 6;

    public function evaluateSalePaid(string $saleUuid): void
    {
        $sale = Sale::query()
            ->where('uuid', $saleUuid)
            ->with(['items.ticketType', 'items.eventProduct', 'finalCustomer'])
            ->first();

        if (! $sale || ! $sale->final_customer_id) {
            return;
        }

        $eventIds = $sale->items
            ->map(fn ($item) => $item->ticketType?->event_id ?? $item->eventProduct?->event_id)
            ->filter()
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return;
        }

        $email = $sale->finalCustomer?->email;

        foreach ($eventIds as $eventId) {
            $count = $this->countRecentPaidSalesForEvent(
                (int) $sale->tenant_id,
                (int) $eventId,
                (int) $sale->final_customer_id,
                $email
            );

            if ($count >= self::PURCHASE_COUNT_THRESHOLD) {
                $sale->forceFill([
                    'risk_flagged' => true,
                    'risk_reason' => __('messages.sale.risk_multiple_purchases_reason', [
                        'count' => $count,
                        'hours' => self::WINDOW_HOURS,
                    ]),
                ])->save();

                return;
            }
        }
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
}
