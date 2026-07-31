<?php

namespace App\Services\Pdv;

use App\DTOs\Pdv\CloseCashSessionDTO;
use App\DTOs\Pdv\OpenCashSessionDTO;
use App\DTOs\Pdv\RegisterCashMovementDTO;
use App\Events\Pdv\CashMovementRegistered;
use App\Events\Pdv\CashSessionClosed;
use App\Events\Pdv\CashSessionOpened;
use App\Exceptions\Pdv\CashSessionException;
use App\Models\Pdv\CashMovement;
use App\Models\Pdv\CashRegister;
use App\Models\Pdv\CashSession;
use App\Models\Stock\StockLocation;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Caixa do PDV (roadmap PDV, Fase PDV-1). Abertura/fechamento/sangria/
 * suprimento de sessão de caixa, tenant-scoped. Fase PDV-1 não tem CRUD de
 * cash_register: open() resolve-ou-cria um "Caixa Principal" default por
 * tenant (mesma ideia do local de estoque default). O fechamento calcula o
 * esperado a partir do dinheiro efetivamente pago em `payments` nas vendas da
 * sessão + movimentos, e NUNCA bloqueia por divergência (só registra/audita).
 */
class CashSessionService
{
    public function __construct(
        private CashSessionRepositoryInterface $repository,
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function current(int $tenantId): ?CashSession
    {
        return $this->repository->openSessionForTenant($tenantId);
    }

    /**
     * Abre uma sessão de caixa. Rejeita se já existe uma sessão `open` pro
     * mesmo cash_register (guard duro do PDV). Quando o DTO não informa
     * cash_register_uuid, resolve-ou-cria o "Caixa Principal" default do
     * tenant.
     */
    public function open(int $tenantId, OpenCashSessionDTO $dto): CashSession
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $register = $this->resolveCashRegister($tenantId, $dto->cashRegisterUuid);

            if ($this->repository->openSessionForRegister($register->id) !== null) {
                throw new CashSessionException(__('messages.pdv.session_already_open'));
            }

            $session = $this->repository->create([
                'tenant_id' => $tenantId,
                'cash_register_id' => $register->id,
                'opened_by' => Auth::id(),
                'opened_at' => now(),
                'opening_amount' => number_format($dto->openingAmount, 2, '.', ''),
                'status' => CashSession::STATUS_OPEN,
            ]);

            event(new CashSessionOpened(
                cashSessionUuid: $session->uuid,
                actorId: (int) Auth::id()
            ));

            return $session->load(['cashRegister', 'movements']);
        });
    }

    public function registerMovement(int $tenantId, string $sessionUuid, RegisterCashMovementDTO $dto): CashMovement
    {
        return DB::transaction(function () use ($tenantId, $sessionUuid, $dto) {
            $session = $this->resolveOwnedOpenSession($tenantId, $sessionUuid);

            $movement = CashMovement::create([
                'tenant_id' => $tenantId,
                'cash_session_id' => $session->id,
                'type' => $dto->type,
                'amount' => number_format($dto->amount, 2, '.', ''),
                'reason' => $dto->reason,
            ]);

            event(new CashMovementRegistered(
                cashSessionUuid: $session->uuid,
                cashMovementUuid: $movement->uuid,
                type: $movement->type,
                amount: (float) $movement->amount,
                actorId: (int) Auth::id()
            ));

            return $movement;
        });
    }

    /**
     * Fecha a sessão. Calcula em centavos (mesma precisão do OrderService):
     *   esperado = abertura + dinheiro pago nesta sessão + suprimentos - sangrias.
     * "Dinheiro pago" = linhas `payments` method='cash' status='paid' dos
     * pedidos vinculados à sessão. Grava a diferença (declarado - esperado) e
     * fecha. NUNCA bloqueia pela diferença — divergência de caixa é
     * operacional, não impeditiva.
     */
    public function close(int $tenantId, string $sessionUuid, CloseCashSessionDTO $dto): CashSession
    {
        return DB::transaction(function () use ($tenantId, $sessionUuid, $dto) {
            $session = $this->resolveOwnedOpenSession($tenantId, $sessionUuid);

            $expectedCents = $this->computeExpectedCents($session);
            $declaredCents = (int) round($dto->closingAmountDeclared * 100);
            $differenceCents = $declaredCents - $expectedCents;

            $session->closed_by = Auth::id();
            $session->closed_at = now();
            $session->closing_amount_declared = $this->centsToDecimal($declaredCents);
            $session->closing_amount_expected = $this->centsToDecimal($expectedCents);
            $session->difference = $this->centsToDecimal($differenceCents);
            $session->status = CashSession::STATUS_CLOSED;
            $session->save();

            event(new CashSessionClosed(
                cashSessionUuid: $session->uuid,
                difference: (float) $session->difference,
                actorId: (int) Auth::id()
            ));

            return $session->load(['cashRegister', 'movements']);
        });
    }

    /**
     * abertura + dinheiro (method='cash') pago nas vendas da sessão +
     * suprimentos - sangrias, tudo em centavos inteiros.
     */
    private function computeExpectedCents(CashSession $session): int
    {
        $openingCents = (int) round((float) $session->opening_amount * 100);

        $cashPaidCents = (int) round(
            (float) DB::table('payments')
                ->join('orders', function ($join) {
                    $join->on('orders.id', '=', 'payments.payable_id')
                        ->where('payments.payable_type', '=', (new \App\Models\Order\Order())->getMorphClass());
                })
                ->where('orders.cash_session_id', $session->id)
                ->where('payments.method', 'cash')
                ->where('payments.status', 'paid')
                ->whereNull('payments.deleted_at')
                ->sum('payments.amount') * 100
        );

        $supplyCents = (int) round(
            (float) CashMovement::where('cash_session_id', $session->id)
                ->where('type', CashMovement::TYPE_SUPPLY)
                ->whereNull('deleted_at')
                ->sum('amount') * 100
        );

        $withdrawalCents = (int) round(
            (float) CashMovement::where('cash_session_id', $session->id)
                ->where('type', CashMovement::TYPE_WITHDRAWAL)
                ->whereNull('deleted_at')
                ->sum('amount') * 100
        );

        return $openingCents + $cashPaidCents + $supplyCents - $withdrawalCents;
    }

    /**
     * Resolve a sessão por uuid GARANTINDO posse do tenant (route-model-binding
     * não é usado aqui, o uuid vem cru da URL — mesmo cuidado de IDOR do
     * OrderService) e que ela ainda está aberta.
     */
    private function resolveOwnedOpenSession(int $tenantId, string $sessionUuid): CashSession
    {
        $session = $this->repository->findByUuidForTenant($sessionUuid, $tenantId);

        if ($session === null) {
            abort(404);
        }

        if (!$session->isOpen()) {
            throw new CashSessionException(__('messages.pdv.session_already_closed'));
        }

        return $session;
    }

    private function resolveCashRegister(int $tenantId, ?string $cashRegisterUuid): CashRegister
    {
        if ($cashRegisterUuid !== null) {
            $register = CashRegister::where('uuid', $cashRegisterUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->first();

            if ($register === null) {
                abort(404);
            }

            return $register;
        }

        $register = CashRegister::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if ($register !== null) {
            return $register;
        }

        $defaultLocation = StockLocation::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        return CashRegister::create([
            'tenant_id' => $tenantId,
            'stock_location_id' => $defaultLocation?->id,
            'name' => 'Caixa Principal',
            'is_active' => true,
        ]);
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
