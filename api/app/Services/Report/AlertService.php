<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

/**
 * Alertas básicos do Home (roadmap Fase A1, seção 88 do documento-base) —
 * SÓ os dois tipos aprovados nesta fase: estoque baixo e pagamento. NÃO é
 * o catálogo completo de alertas configuráveis da spec (seção 79, fase
 * futura) — sem model, sem regra por tenant, tudo calculado on-the-fly a
 * partir de dado já existente, no mesmo espírito de
 * `OperationSnapshotService`.
 */
class AlertService
{
    /**
     * Ticket type com estoque restante igual ou abaixo deste percentual da
     * capacidade cadastrada dispara alerta de estoque baixo.
     */
    private const LOW_STOCK_THRESHOLD_PERCENTAGE = 15.0;

    private const LOW_STOCK_MAX_ITEMS = 10;

    /**
     * Janela de observação da taxa de recusa de pagamento.
     */
    private const PAYMENT_REJECTION_WINDOW_DAYS = 7;

    private const PAYMENT_REJECTION_THRESHOLD_PERCENTAGE = 20.0;

    /**
     * Amostra mínima de vendas decididas (confirmed + rejected) na janela
     * para não disparar alerta com 1 recusa em 1 venda (100% de ruído).
     */
    private const PAYMENT_REJECTION_MIN_SAMPLE = 5;

    /**
     * Fila de aprovação pendente acima deste total dispara alerta.
     */
    private const PENDING_APPROVAL_THRESHOLD = 15;

    /**
     * @return list<array{type: string, severity: string, title: string, message: string, meta: array<string, mixed>}>
     */
    public function activeAlerts(int $tenantId): array
    {
        return [
            ...$this->lowStockAlerts($tenantId),
            ...$this->paymentRejectionAlert($tenantId),
            ...$this->pendingApprovalAlert($tenantId),
        ];
    }

    /**
     * Um alerta por `ticket_type` ativo com capacidade cadastrada
     * (`quantity_available` não nulo) cujo estoque restante caiu abaixo do
     * limiar. Ticket type sem capacidade cadastrada (venda ilimitada) nunca
     * gera alerta — não há "esgotar" pra medir.
     */
    private function lowStockAlerts(int $tenantId): array
    {
        $rows = DB::table('ticket_types')
            ->leftJoin('tickets', function ($join) {
                $join->on('tickets.ticket_type_id', '=', 'ticket_types.id')
                    ->where('tickets.status', 'ativo')
                    ->whereNull('tickets.deleted_at');
            })
            ->leftJoin('events', 'events.id', '=', 'ticket_types.event_id')
            ->where('ticket_types.tenant_id', $tenantId)
            ->where('ticket_types.status', 'ativo')
            ->whereNull('ticket_types.deleted_at')
            ->whereNotNull('ticket_types.quantity_available')
            ->where('ticket_types.quantity_available', '>', 0)
            ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name', 'ticket_types.quantity_available', 'events.name')
            ->selectRaw('ticket_types.uuid as ticket_type_uuid, ticket_types.name as ticket_type_name, events.name as event_name, ticket_types.quantity_available as capacity, COUNT(tickets.id) as issued')
            ->get()
            ->map(function ($row) {
                $capacity = (int) $row->capacity;
                $issued = (int) $row->issued;
                $remaining = max(0, $capacity - $issued);
                $remainingPercentage = $capacity > 0 ? round(($remaining / $capacity) * 100, 2) : 0.0;

                return [
                    'ticket_type_uuid' => $row->ticket_type_uuid,
                    'ticket_type_name' => $row->ticket_type_name,
                    'event_name' => $row->event_name,
                    'capacity' => $capacity,
                    'remaining' => $remaining,
                    'remaining_percentage' => $remainingPercentage,
                ];
            })
            ->filter(fn (array $item) => $item['remaining_percentage'] <= self::LOW_STOCK_THRESHOLD_PERCENTAGE)
            ->sortBy('remaining_percentage')
            ->take(self::LOW_STOCK_MAX_ITEMS)
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn (array $item) => [
            'type' => 'low_stock',
            'severity' => $item['remaining'] === 0 ? 'critical' : 'warning',
            'title' => 'Estoque baixo',
            'message' => $item['remaining'] === 0
                ? "\"{$item['ticket_type_name']}\" esgotou."
                : "\"{$item['ticket_type_name']}\" com apenas {$item['remaining']} ingresso(s) restante(s) ({$item['remaining_percentage']}% da capacidade).",
            'meta' => $item,
        ])->all();
    }

    /**
     * Taxa de recusa de pagamento acima do limiar nos últimos N dias
     * (mesma definição de `AnalyticsService::paymentsSummary()`).
     */
    private function paymentRejectionAlert(int $tenantId): array
    {
        $from = now()->subDays(self::PAYMENT_REJECTION_WINDOW_DAYS)->startOfDay();

        $rows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $from)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $confirmed = (int) ($rows['confirmed'] ?? 0);
        $rejected = (int) ($rows['rejected'] ?? 0);
        $decided = $confirmed + $rejected;

        if ($decided < self::PAYMENT_REJECTION_MIN_SAMPLE) {
            return [];
        }

        $rejectionRate = round(($rejected / $decided) * 100, 2);

        if ($rejectionRate < self::PAYMENT_REJECTION_THRESHOLD_PERCENTAGE) {
            return [];
        }

        return [[
            'type' => 'payment_rejection_rate',
            'severity' => $rejectionRate >= 40.0 ? 'critical' : 'warning',
            'title' => 'Taxa de recusa de pagamento alta',
            'message' => "{$rejectionRate}% das vendas decididas nos últimos ".self::PAYMENT_REJECTION_WINDOW_DAYS.' dias foram recusadas.',
            'meta' => [
                'window_days' => self::PAYMENT_REJECTION_WINDOW_DAYS,
                'confirmed_count' => $confirmed,
                'rejected_count' => $rejected,
                'rejection_rate_percentage' => $rejectionRate,
            ],
        ]];
    }

    /**
     * Fila de vendas aguardando aprovação acima do limiar — proxy simples
     * de "fila crescendo" (contagem absoluta atual, sem histórico de
     * tendência nesta fase).
     */
    private function pendingApprovalAlert(int $tenantId): array
    {
        $count = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'pending_approval')
            ->count();

        if ($count < self::PENDING_APPROVAL_THRESHOLD) {
            return [];
        }

        return [[
            'type' => 'payment_pending_queue',
            'severity' => $count >= self::PENDING_APPROVAL_THRESHOLD * 2 ? 'critical' : 'warning',
            'title' => 'Fila de aprovação de pagamento crescendo',
            'message' => "{$count} vendas aguardando aprovação manual.",
            'meta' => ['pending_approval_count' => $count],
        ]];
    }
}
