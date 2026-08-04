<?php

namespace App\Services\Report;

use App\Models\Inventory\InventoryHold;
use App\Models\Sale\Sale;
use App\Models\Storefront\VirtualQueueEntry;
use App\Models\Ticket\TicketCheckin;
use App\Services\CashSession\CashSessionService;
use App\Services\Ticket\TicketService;
use App\Support\Money;

/**
 * Dashboard operacional em tempo quase real (roadmap Fase 2 — "dashboards
 * operacionais em tempo quase real"). Agrega, num único round-trip, o que
 * já existe em serviços especializados (caixa, check-in) + contadores
 * simples de vendas do dia — sem duplicar a regra de negócio de cada
 * domínio, só monta o retorno. Pensado pra ser chamado em polling curto
 * (ver `reports-operation-snapshot`, throttle mais generoso que os outros
 * endpoints de relatório).
 */
class OperationSnapshotService
{
    /**
     * Janela de observação da taxa de erro de checkout (roadmap Fase 7) —
     * default técnico, não validado com o usuário. Reaproveita o funil já
     * existente de InventoryHold (reservado = checkout iniciado,
     * convertido_em_venda = concluído, expirado/abandonado = erro/desistência)
     * em vez de criar um novo sistema de telemetria do zero.
     */
    private const CHECKOUT_WINDOW_HOURS = 6;

    public function __construct(
        private CashSessionService $cashSessionService,
        private TicketService $ticketService,
    ) {}

    public function snapshot(int $tenantId): array
    {
        $cashSession = $this->cashSessionService->current();

        $pendingApprovalCount = Sale::where('tenant_id', $tenantId)
            ->where('status', 'pending_approval')
            ->whereNull('deleted_at')
            ->count();

        $today = now()->startOfDay();

        $salesToday = Sale::where('tenant_id', $tenantId)
            ->where('is_paid', true)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->where('paid_at', '>=', $today);

        $checkinsToday = TicketCheckin::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('checked_in_at', '>=', $today);

        $granted = (clone $checkinsToday)->whereIn('result', TicketService::CHECKIN_GRANTED_RESULTS)->count();
        $warning = (clone $checkinsToday)->whereIn('result', TicketService::CHECKIN_WARNING_RESULTS)->count();
        $checkinsTotal = (clone $checkinsToday)->count();

        $checkoutWindowStart = now()->subHours(self::CHECKOUT_WINDOW_HOURS);
        $holdsInWindow = InventoryHold::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $checkoutWindowStart);

        $checkoutStarted = (clone $holdsInWindow)->count();
        $checkoutCompleted = (clone $holdsInWindow)->where('status', InventoryHold::STATUS_CONVERTED)->count();
        $checkoutTerminal = (clone $holdsInWindow)
            ->whereIn('status', [InventoryHold::STATUS_CONVERTED, InventoryHold::STATUS_EXPIRED, InventoryHold::STATUS_ABANDONED])
            ->count();
        $checkoutErrorRate = $checkoutTerminal > 0
            ? round((($checkoutTerminal - $checkoutCompleted) / $checkoutTerminal) * 100, 1)
            : 0.0;

        $virtualQueueWaiting = VirtualQueueEntry::where('tenant_id', $tenantId)
            ->where('status', VirtualQueueEntry::STATUS_WAITING)
            ->count();
        $virtualQueueAdmitted = VirtualQueueEntry::where('tenant_id', $tenantId)
            ->where('status', VirtualQueueEntry::STATUS_ADMITTED)
            ->count();

        return [
            'cash_session' => $cashSession ? [
                'status' => $cashSession->status,
                'opening_amount' => (string) $cashSession->opening_amount,
                'expected_cash_amount' => $cashSession->expected_cash_amount !== null
                    ? (string) $cashSession->expected_cash_amount
                    : null,
            ] : null,
            'sales_pending_approval_count' => $pendingApprovalCount,
            'sales_today' => [
                'count' => (clone $salesToday)->count(),
                'total_amount' => Money::normalize((clone $salesToday)->sum('total_amount')),
            ],
            'checkins_today' => [
                'total' => $checkinsTotal,
                'granted' => $granted,
                'warning' => $warning,
                'blocked' => max(0, $checkinsTotal - $granted - $warning),
            ],
            'checkout' => [
                'window_hours' => self::CHECKOUT_WINDOW_HOURS,
                'started' => $checkoutStarted,
                'completed' => $checkoutCompleted,
                'error_rate_percent' => $checkoutErrorRate,
            ],
            'virtual_queue' => [
                'waiting' => $virtualQueueWaiting,
                'admitted' => $virtualQueueAdmitted,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
