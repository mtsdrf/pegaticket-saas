<?php

namespace App\Services\Report;

use App\Models\Sale\Sale;
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
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
