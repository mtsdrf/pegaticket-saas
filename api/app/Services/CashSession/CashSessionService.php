<?php

namespace App\Services\CashSession;

use App\DTOs\CashSession\CloseCashSessionDTO;
use App\DTOs\CashSession\OpenCashSessionDTO;
use App\Events\CashSession\CashSessionClosed;
use App\Events\CashSession\CashSessionOpened;
use App\Exceptions\InvalidCashSessionStateException;
use App\Models\CashSession\CashSession;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Caixa (roadmap Fase 2 — "caixa e estações de venda"). Um caixa aberto
 * por vez por tenant. O valor esperado no fechamento soma as vendas
 * manuais em dinheiro (`sales.payment_method='cash'`, `origin='staff'`,
 * pagas) criadas dentro da janela aberta/fechamento — sem FK extra em
 * `sales`, calculado por tenant + intervalo de tempo, então cobre
 * qualquer venda manual em dinheiro feita enquanto o caixa está aberto,
 * independente de qual operador a lançou.
 */
class CashSessionService
{
    public function __construct(
        private CashSessionRepositoryInterface $repository,
    ) {}

    public function open(OpenCashSessionDTO $dto): CashSession
    {
        $tenantId = (int) app('tenant_id');

        return DB::transaction(function () use ($dto, $tenantId) {
            $existing = $this->repository->findOpenForTenant($tenantId, lock: true);

            if ($existing !== null) {
                throw new InvalidCashSessionStateException(__('messages.cash_session.already_open'));
            }

            $session = CashSession::create([
                'tenant_id' => $tenantId,
                'opened_by' => Auth::id(),
                'opening_amount' => $dto->openingAmount,
                'opening_notes' => $dto->openingNotes,
                'status' => CashSession::STATUS_OPEN,
                'opened_at' => now(),
            ]);

            event(new CashSessionOpened(
                cashSessionUuid: $session->uuid,
                openingAmount: $dto->openingAmount,
                actorId: Auth::id()
            ));

            return $session;
        });
    }

    public function current(): ?CashSession
    {
        $session = $this->repository->findOpenForTenant((int) app('tenant_id'));

        if ($session === null) {
            return null;
        }

        $session->expected_cash_amount = $this->calculateExpectedCashAmount($session, now());

        return $session;
    }

    public function close(CloseCashSessionDTO $dto): CashSession
    {
        $tenantId = (int) app('tenant_id');

        return DB::transaction(function () use ($dto, $tenantId) {
            $session = $this->repository->findOpenForTenant($tenantId, lock: true);

            if ($session === null) {
                throw new InvalidCashSessionStateException(__('messages.cash_session.no_open_session'));
            }

            $closedAt = now();
            $expected = $this->calculateExpectedCashAmount($session, $closedAt);
            $difference = round($dto->closingAmount - $expected, 2);

            $session->forceFill([
                'closing_amount' => $dto->closingAmount,
                'closing_notes' => $dto->closingNotes,
                'expected_cash_amount' => $expected,
                'difference_amount' => $difference,
                'status' => CashSession::STATUS_CLOSED,
                'closed_by' => Auth::id(),
                'closed_at' => $closedAt,
            ])->save();

            event(new CashSessionClosed(
                cashSessionUuid: $session->uuid,
                closingAmount: $dto->closingAmount,
                expectedCashAmount: $expected,
                differenceAmount: $difference,
                actorId: Auth::id()
            ));

            return $session;
        });
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant((int) app('tenant_id'), $perPage);
    }

    private function calculateExpectedCashAmount(CashSession $session, \DateTimeInterface $until): float
    {
        $cashSalesTotal = Sale::where('tenant_id', $session->tenant_id)
            ->where('origin', 'staff')
            ->where('payment_method', 'cash')
            ->where('is_paid', true)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->whereBetween('paid_at', [$session->opened_at, $until])
            ->sum('total_amount');

        return round((float) $session->opening_amount + (float) $cashSalesTotal, 2);
    }
}
