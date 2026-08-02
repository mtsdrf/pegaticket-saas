<?php

namespace App\Services\Ticket;

use App\Events\Ticket\TicketsCancelled;
use App\Events\Ticket\TicketsIssued;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Ticket\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Emissão/cancelamento automático de Ticket a partir do ciclo de vida da
 * Sale (spec 5.15/roadmap 2.8) — nunca chamado por CRUD manual. Acionado
 * pelos listeners App\Listeners\Sale\IssueTicketsOnSalePaid (evento
 * SalePaid) e App\Listeners\Sale\CancelTicketsOnSaleCancelled (evento
 * SaleCancelled), os dois únicos pontos que mudam is_paid/cancelled_at no
 * sistema (ver SaleService::performPayment()/SalePaymentService::markOrderPaid()
 * e SaleService::cancel()).
 */
class TicketIssuanceService
{
    /**
     * Idempotente: para cada SaleItem com ticket_type_id preenchido, emite
     * só a DIFERENÇA entre a quantidade do item e os tickets já existentes
     * pra aquele item — cobre reprocessamento de webhook sem duplicar.
     * EventProduct (adicional/estacionamento) não gera Ticket nesta rodada
     * (sem controle de acesso individual, spec 5.15 é só ingresso).
     */
    public function issueForSale(Sale $order, ?int $actorId): void
    {
        DB::transaction(function () use ($order, $actorId) {
            $items = SaleItem::where('sale_id', $order->id)
                ->whereNotNull('ticket_type_id')
                ->whereNull('deleted_at')
                ->get();

            $issuedUuids = [];

            foreach ($items as $item) {
                $existing = Ticket::where('sale_item_id', $item->id)->count();
                $needed = (int) round((float) $item->quantity) - $existing;

                if ($needed <= 0) {
                    continue;
                }

                // Participantes informados no checkout (spec 5.10 Etapa 2),
                // consumidos na ordem informada — a partir do índice de
                // tickets já existentes (idempotente em reprocessamento).
                // SIMPLIFICAÇÃO DOCUMENTADA: quando ausentes/insuficientes,
                // o Ticket é emitido com attendee_name/attendee_document
                // nulos (equivalente a "participante = comprador",
                // preenchimento posterior fora de escopo desta rodada).
                $attendees = $item->attendee_data ?? [];

                for ($i = 0; $i < $needed; $i++) {
                    $attendee = $attendees[$existing + $i] ?? null;

                    $ticket = Ticket::create([
                        'tenant_id' => $order->tenant_id,
                        'sale_item_id' => $item->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'seat_id' => $item->seat_id,
                        'attendee_name' => $attendee['name'] ?? null,
                        'attendee_document' => $attendee['document'] ?? null,
                        'status' => 'ativo',
                        'issued_at' => now(),
                    ]);

                    $issuedUuids[] = $ticket->uuid;
                }
            }

            if (!empty($issuedUuids)) {
                event(new TicketsIssued(
                    saleUuid: $order->uuid,
                    ticketUuids: $issuedUuids,
                    actorId: $actorId
                ));
            }
        });
    }

    /**
     * Espelha o cancelamento/estorno da Sale nos Tickets já emitidos.
     * `estornado` quando a venda já estava paga no momento do cancelamento
     * (dinheiro recebido, mesma condição de SaleService::cancel() que gera
     * Refund), `cancelado` caso contrário. Ignora tickets que já estão em
     * cancelado/estornado (idempotente); sobrescreve `utilizado` também —
     * ingresso já utilizado numa venda estornada precisa ficar inválido
     * pra qualquer novo check-in (regra crítica #10 da spec).
     */
    public function cancelForSale(Sale $order, ?int $actorId): void
    {
        $status = $order->is_paid ? 'estornado' : 'cancelado';

        DB::transaction(function () use ($order, $status, $actorId) {
            $itemIds = SaleItem::where('sale_id', $order->id)->pluck('id');

            $tickets = Ticket::whereIn('sale_item_id', $itemIds)
                ->whereNotIn('status', ['cancelado', 'estornado'])
                ->get();

            if ($tickets->isEmpty()) {
                return;
            }

            $uuids = $tickets->pluck('uuid')->all();

            Ticket::whereIn('id', $tickets->pluck('id'))->update(['status' => $status]);

            event(new TicketsCancelled(
                saleUuid: $order->uuid,
                ticketUuids: $uuids,
                status: $status,
                actorId: $actorId
            ));
        });
    }
}
