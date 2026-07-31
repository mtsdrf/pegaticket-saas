<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\Order\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios financeiros do contador (roadmap 2C) a partir de dados JÁ
 * existentes (orders/order_items/products) — só leitura. TODA leitura é
 * auditada explicitamente (AuditLog::record), mais rigoroso que o padrão do
 * projeto (que só audita mutação): o ator é o AccountingOffice, que NÃO é um
 * User — por isso user_id do audit fica null e a identidade do escritório vai
 * no `meta` (accounting_office_id/uuid), ver AuditLog::record.
 *
 * Agregações usam apenas SUM/COUNT + filtros de data simples (whereBetween),
 * sem funções de data específicas de driver — seguro em MySQL (produção) e
 * SQLite (testes) sem expressões por dialeto.
 */
class AccountingReportService
{
    /**
     * @return array{from: string, to: string, total_orders: int, total_revenue: string, items: array<int, array<string, mixed>>}
     */
    public function sales(int $tenantId, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolvePeriod($from, $to);

        $orders = Order::query()
            ->with('client:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get(['id', 'uuid', 'client_id', 'total_amount', 'is_paid', 'is_delivered', 'created_at']);

        $items = $orders->map(fn (Order $order) => [
            'order_uuid' => $order->uuid,
            'client_name' => $order->client?->name,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'total_amount' => $this->money($order->total_amount),
            'is_paid' => (bool) $order->is_paid,
            'is_delivered' => (bool) $order->is_delivered,
        ])->all();

        $this->audit($tenantId, 'sales', $start, $end);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'total_orders' => $orders->count(),
            'total_revenue' => $this->money((float) $orders->sum('total_amount')),
            'items' => $items,
        ];
    }

    /**
     * Livro-caixa simples: entradas = pedidos PAGOS no período (por paid_at).
     *
     * @return array{from: string, to: string, total_in: string, entries: array<int, array<string, mixed>>}
     */
    public function cashFlow(int $tenantId, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolvePeriod($from, $to);

        $orders = Order::query()
            ->with('client:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->where('is_paid', true)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get(['id', 'uuid', 'client_id', 'total_amount', 'paid_at']);

        $entries = $orders->map(fn (Order $order) => [
            'date' => optional($order->paid_at)->toDateString(),
            'order_uuid' => $order->uuid,
            'client_name' => $order->client?->name,
            'amount' => $this->money($order->total_amount),
        ])->all();

        $this->audit($tenantId, 'cash_flow', $start, $end);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'total_in' => $this->money((float) $orders->sum('total_amount')),
            'entries' => $entries,
        ];
    }

    /**
     * DRE simplificado: receita de vendas − custo de produto (quando
     * last_purchase_cost cadastrado), no período.
     *
     * @return array{from: string, to: string, revenue: string, product_cost: string, gross_profit: string}
     */
    public function dre(int $tenantId, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolvePeriod($from, $to);

        $revenue = (float) Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        // Custo = soma de quantity * products.last_purchase_cost (só itens com
        // custo cadastrado), dos pedidos não cancelados do período.
        $productCost = (float) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_items.deleted_at')
            ->whereNotNull('products.last_purchase_cost')
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum(DB::raw('order_items.quantity * products.last_purchase_cost'));

        $this->audit($tenantId, 'dre', $start, $end);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'revenue' => $this->money($revenue),
            'product_cost' => $this->money($productCost),
            'gross_profit' => $this->money($revenue - $productCost),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        $end = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : (clone $end)->subMonths(12)->startOfDay();

        return [$start, $end];
    }

    private function money(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function audit(int $tenantId, string $report, Carbon $start, Carbon $end): void
    {
        $office = accounting_office();

        AuditLog::recordForNonUser('accounting_office.viewed_report', [
            'accounting_office_id' => $office?->id,
            'accounting_office_uuid' => $office?->uuid,
            'tenant_id' => $tenantId,
            'report' => $report,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ]);
    }
}
