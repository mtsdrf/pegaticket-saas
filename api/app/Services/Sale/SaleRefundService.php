<?php

namespace App\Services\Sale;

use App\DTOs\Sale\CreateSaleRefundDTO;
use App\Events\Sale\SaleRefundCreated;
use App\Exceptions\InvalidSaleStateException;
use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleRefund;
use App\Models\Sale\SaleRefundTicket;
use App\Models\Ticket\Ticket;
use App\Repositories\Contracts\SaleRefundRepositoryInterface;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Estorno externo (spec 5.14/11.3): o clube já fez o estorno no PagBank
 * fora do sistema — este serviço só REGISTRA o que aconteceu e aplica os
 * efeitos internos: invalidar os tickets afetados (status='estornado',
 * já bloqueado para check-in em CheckinService::STATUS_TO_RESULT) e,
 * se o operador escolher, liberar o(s) lugar(es).
 *
 * Decisão técnica sobre "liberar lugar" (spec item 7, investigada antes
 * de implementar): a disponibilidade de assento hoje é calculada em
 * StorefrontHoldService::buildSeatAvailability() somando
 * `sale_items.quantity` (via SaleItem) para pedidos não cancelados —
 * ela NUNCA olha para Ticket.status. Seat.status é estrutural/admin
 * (compartilhado por todas as vendas daquele assento) e não pode virar
 * um "liberado por venda" sem virar uma mudança perigosa e global.
 * Como `sale_items.seat_id`/`tickets.seat_id` são nullable e a query de
 * disponibilidade já filtra `whereNotNull('sale_items.seat_id')`, a
 * forma correta e mínima de "devolver o lugar ao mapa" dentro da
 * arquitetura existente é nular o `seat_id` do Ticket estornado E do
 * SaleItem (order_item) de origem — isso remove a linha da soma de
 * "vendido" sem apagar o item do pedido (total_amount/relatórios
 * financeiros do pedido continuam intactos, só o vínculo com o assento
 * é desfeito). Sem essa mudança, marcar o ticket como estornado sozinho
 * NÃO libera o lugar para nova venda.
 */
class SaleRefundService
{
    public function __construct(
        private SaleRefundRepositoryInterface $repository,
        private MediaStorageService $mediaStorage,
    ) {
    }

    public function create(Sale $order, CreateSaleRefundDTO $dto, ?UploadedFile $receipt): SaleRefund
    {
        $this->assertBelongsToCurrentTenant($order);

        if ($dto->type === SaleRefund::TYPE_PARTIAL && empty($dto->ticketUuids)) {
            throw new InvalidSaleStateException(__('messages.sale.refund_partial_requires_tickets'));
        }

        $newReceipt = null;
        if ($receipt) {
            $newReceipt = $this->mediaStorage->storePublicImage(
                $receipt,
                $this->resolveMediaDirectory($order->tenant_id),
                'sale_refund'
            );
        }

        return DB::transaction(function () use ($order, $dto, $newReceipt) {
            $order = $this->lockOrder($order);

            $tickets = $this->resolveAffectedTickets($order, $dto);

            $this->assertAmountWithinPaidAmount($order, $dto->amount);

            $refund = SaleRefund::create([
                'tenant_id' => $order->tenant_id,
                'sale_id' => $order->id,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'reason' => $dto->reason,
                'refunded_at' => $dto->refundedAt,
                'external_reference' => $dto->externalReference,
                'receipt_path' => $newReceipt['path'] ?? null,
                'receipt_mime' => $newReceipt['mime'] ?? null,
                'notes' => $dto->notes,
                'release_seats' => $dto->releaseSeats,
                'status' => SaleRefund::STATUS_REGISTERED,
            ]);

            foreach ($tickets as $ticket) {
                SaleRefundTicket::create([
                    'tenant_id' => $order->tenant_id,
                    'sale_refund_id' => $refund->id,
                    'ticket_id' => $ticket->id,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $ticket->status = 'estornado';

                if ($dto->releaseSeats && $ticket->seat_id !== null) {
                    $ticket->seat_id = null;

                    // order_item de origem também perde o vínculo com o
                    // assento — é essa linha que StorefrontHoldService
                    // soma para calcular "vendido" (ver docblock da classe).
                    $ticket->saleItem()->update(['seat_id' => null]);
                }

                $ticket->save();
            }

            event(new SaleRefundCreated(
                saleUuid: $order->uuid,
                saleRefundUuid: $refund->uuid,
                type: $refund->type,
                amount: (float) $refund->amount,
                releaseSeats: $dto->releaseSeats,
                ticketUuids: $tickets->pluck('uuid')->all(),
                actorId: Auth::id()
            ));

            return $refund->fresh(['tickets']);
        });
    }

    public function listForOrder(Sale $order): Collection
    {
        $this->assertBelongsToCurrentTenant($order);

        return $this->repository->listForOrder($order);
    }

    /**
     * Regras: total -> todos os tickets ainda não estornados do pedido;
     * parcial -> exatamente os tickets informados, todos precisam
     * pertencer ao pedido/tenant e não estar estornados ainda.
     */
    private function resolveAffectedTickets(Sale $order, CreateSaleRefundDTO $dto): Collection
    {
        $query = Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $order->id))
            ->where('tenant_id', $order->tenant_id)
            ->where('status', '!=', 'estornado')
            ->lockForUpdate();

        if ($dto->type === SaleRefund::TYPE_PARTIAL) {
            $query->whereIn('uuid', $dto->ticketUuids);
        }

        $tickets = $query->get();

        if ($dto->type === SaleRefund::TYPE_PARTIAL && $tickets->count() !== count($dto->ticketUuids)) {
            throw new InvalidSaleStateException(__('messages.sale.refund_ticket_not_found_in_order'));
        }

        if ($tickets->isEmpty()) {
            throw new InvalidSaleStateException(__('messages.sale.refund_no_eligible_tickets'));
        }

        return $tickets;
    }

    private function assertAmountWithinPaidAmount(Sale $order, float $amount): void
    {
        $paidAmountCents = (int) round(((float) $order->paid_amount) * 100);
        $alreadyRefundedCents = (int) round($this->repository->sumAmountForOrder($order) * 100);
        $amountCents = (int) round($amount * 100);

        if ($amountCents + $alreadyRefundedCents > $paidAmountCents) {
            throw new InvalidSaleStateException(__('messages.sale.refund_amount_exceeds_paid', [
                'available' => $this->centsToDecimal(max(0, $paidAmountCents - $alreadyRefundedCents)),
            ]));
        }
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function resolveMediaDirectory(int $tenantId): string
    {
        return (string) \App\Models\Tenant\Tenant::query()
            ->whereKey($tenantId)
            ->value('uuid');
    }

    /**
     * Mesmo motivo/documentação de SaleService::lockOrder().
     */
    private function lockOrder(Sale $order): Sale
    {
        return Sale::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Mesmo motivo/documentação de SaleService::assertBelongsToCurrentTenant().
     */
    private function assertBelongsToCurrentTenant(BaseModel $model): void
    {
        if ((int) $model->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
