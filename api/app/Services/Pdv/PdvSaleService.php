<?php

namespace App\Services\Pdv;

use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Pdv\CreatePdvSaleDTO;
use App\Events\Pdv\PdvSaleCompleted;
use App\Exceptions\Pdv\CashSessionException;
use App\Exceptions\Pdv\PaymentAmountMismatchException;
use App\Models\Client\Client;
use App\Models\Order\Order;
use App\Models\Pdv\CashSession;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Services\Order\OrderPaymentService;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Venda rápida do balcão (roadmap PDV, Fase PDV-1). NÃO duplica a lógica de
 * pedido: monta um CreateOrderDTO (origin='pdv', markAsDelivered=true,
 * markAsPaid=false) e reaproveita OrderService::create() inteiro (reserva de
 * estoque, preço, código sequencial, baixa real de estoque via
 * convertReservationsToExit). O pagamento é tratado à parte, em múltiplas
 * linhas `payments` declaradas pelo operador (dinheiro/Pix/cartão, todas
 * `paid` na hora no PDV-1). A soma das formas precisa bater EXATAMENTE com o
 * total do pedido calculado no backend — senão a venda inteira é rejeitada e
 * o pedido nunca fica pago.
 */
class PdvSaleService
{
    private const WALK_IN_CLIENT_NAME = 'Consumidor Final';

    public function __construct(
        private OrderService $orderService,
        private OrderPaymentService $paymentService,
        private CashSessionRepositoryInterface $cashSessionRepository,
    ) {
    }

    public function create(int $tenantId, CreatePdvSaleDTO $dto, ?string $cashSessionUuid = null): Order
    {
        return DB::transaction(function () use ($tenantId, $dto, $cashSessionUuid) {
            $existingOrder = $this->findExistingClientSale($tenantId, $dto->clientSaleUuid);
            if ($existingOrder !== null) {
                return $existingOrder;
            }

            $session = $this->resolveOpenSession($tenantId, $cashSessionUuid);

            $clientUuid = $this->resolveClientUuid($tenantId, $dto->clientUuid);

            $orderDto = new CreateOrderDTO(
                tenantId: $tenantId,
                clientUuid: $clientUuid,
                stockLocationUuid: $dto->stockLocationUuid,
                isInstallment: false,
                installmentsCount: null,
                notes: $dto->notes,
                expectedDeliveryDate: null,
                markAsDelivered: true,
                markAsPaid: false,
                items: $dto->items,
                origin: 'pdv',
                status: 'confirmed',
                reserveStock: true,
            );

            $order = $this->orderService->create($orderDto);

            $order->cash_session_id = $session->id;

            // Operador identificado por PIN (roadmap A4, item 15) — já
            // validado como staff ativo do tenant no Request. Distinto de
            // created_by (usuário do JWT da sessão) e de
            // cash_sessions.opened_by (quem abriu o caixa).
            if ($dto->operatorUuid !== null) {
                $operatorId = \App\Models\User\User::where('uuid', $dto->operatorUuid)->value('id');
                $order->operated_by = $operatorId;
            }

            if ($dto->clientSaleUuid !== null) {
                $order->client_sale_uuid = $dto->clientSaleUuid;
            }

            $order->save();

            // Total autoritativo (calculado no backend), em centavos, contra a
            // soma das formas de pagamento — nunca confia no total do request.
            $totalCents = (int) round((float) $order->total_amount * 100);
            $paidCents = 0;

            foreach ($dto->payments as $payment) {
                $paidCents += (int) round(((float) $payment['amount']) * 100);
            }

            if ($paidCents !== $totalCents) {
                throw new PaymentAmountMismatchException(__('messages.pdv.payment_mismatch'));
            }

            foreach ($dto->payments as $payment) {
                $this->paymentService->registerManualPayment(
                    $order,
                    (string) $payment['method'],
                    (float) $payment['amount']
                );
            }

            // Soma bateu: marca o pedido como pago reaproveitando o fluxo
            // existente (OrderService::pay dispara OrderPaid, mesma trilha
            // de um pedido staff pago).
            $order = $this->orderService->pay($order);

            event(new PdvSaleCompleted(
                orderUuid: $order->uuid,
                cashSessionUuid: $session->uuid,
                payments: array_map(
                    fn ($p) => ['method' => (string) $p['method'], 'amount' => (float) $p['amount']],
                    $dto->payments
                ),
                actorId: (int) Auth::id()
            ));

            return $order;
        });
    }

    private function findExistingClientSale(int $tenantId, ?string $clientSaleUuid): ?Order
    {
        if ($clientSaleUuid === null) {
            return null;
        }

        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where('client_sale_uuid', $clientSaleUuid)
            ->first();
    }

    private function resolveOpenSession(int $tenantId, ?string $cashSessionUuid): CashSession
    {
        if ($cashSessionUuid !== null) {
            $session = $this->cashSessionRepository->findByUuidForTenant($cashSessionUuid, $tenantId);

            if ($session === null) {
                abort(404);
            }

            if (!$session->isOpen()) {
                throw new CashSessionException(__('messages.pdv.session_already_closed'));
            }

            return $session;
        }

        $session = $this->cashSessionRepository->openSessionForTenant($tenantId);

        if ($session === null) {
            throw new CashSessionException(__('messages.pdv.no_open_session'));
        }

        return $session;
    }

    /**
     * Cliente é opcional na venda de balcão (consumidor final sem cadastro):
     * quando o operador não informa um cliente, a venda é atribuída a um
     * cliente "Consumidor Final" resolvido-ou-criado por tenant. Isso evita
     * tornar orders.client_id nullable (FK obrigatória hoje, usada por
     * relatórios/resources) e mantém OrderService::create() intocado — ver
     * architecture-decisions.md.
     */
    private function resolveClientUuid(int $tenantId, ?string $clientUuid): string
    {
        if ($clientUuid !== null) {
            return $clientUuid;
        }

        $walkIn = Client::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => self::WALK_IN_CLIENT_NAME,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]
        );

        return $walkIn->uuid;
    }
}
