<?php

namespace App\Services\Ticket;

use App\DTOs\Ticket\CheckinResultDTO;
use App\DTOs\Ticket\CheckinTicketDTO;
use App\Events\Ticket\TicketCheckedIn;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use Illuminate\Support\Facades\DB;

/**
 * Controle de acesso/check-in (spec 5.16). `nao_encontrado` NÃO persiste
 * TicketCheckin (não há ticket_id pra vincular) — só dispara o evento de
 * auditoria com ticket_uuid=null, decisão mais simples que criar uma
 * tabela de tentativas órfãs. Qualquer outro resultado (ticket localizado,
 * válido ou recusado) persiste uma linha em ticket_checkins, cobrindo
 * "consultar histórico" da spec inclusive para tentativas recusadas.
 */
class CheckinService
{
    /**
     * Resultados fora do enum principal (ex.: status futuro fora dos
     * valores hoje emitidos por TicketIssuanceService) caem em `bloqueado`
     * como fallback seguro — nunca libera entrada por status desconhecido.
     */
    private const STATUS_TO_RESULT = [
        'cancelado' => 'cancelado',
        'estornado' => 'estornado',
        'bloqueado' => 'bloqueado',
        'utilizado' => 'ja_utilizado',
    ];

    public function checkin(int $tenantId, CheckinTicketDTO $dto, int $operatorId): CheckinResultDTO
    {
        $ticket = $this->locateTicket($tenantId, $dto);

        if (!$ticket) {
            event(new TicketCheckedIn(ticketUuid: null, result: 'nao_encontrado', actorId: $operatorId));

            return new CheckinResultDTO('nao_encontrado', null, null);
        }

        return DB::transaction(function () use ($ticket, $dto, $operatorId) {
            /** @var Ticket $ticket */
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('ticketType.event', 'ticketType.session');

            if ($dto->eventUuid !== null && optional($ticket->ticketType->event)->uuid !== $dto->eventUuid) {
                return $this->recordAttempt($ticket, 'evento_incorreto', $dto, $operatorId);
            }

            if ($dto->eventSessionUuid !== null && optional($ticket->ticketType->session)->uuid !== $dto->eventSessionUuid) {
                return $this->recordAttempt($ticket, 'sessao_incorreta', $dto, $operatorId);
            }

            $blockedResult = self::STATUS_TO_RESULT[$ticket->status] ?? null;

            if ($blockedResult !== null) {
                return $this->recordAttempt($ticket, $blockedResult, $dto, $operatorId);
            }

            if ($ticket->status !== 'ativo') {
                // pendente ou qualquer status futuro fora do enum conhecido
                // — recusa por segurança, nunca libera entrada.
                return $this->recordAttempt($ticket, 'bloqueado', $dto, $operatorId);
            }

            $ticket->status = 'utilizado';
            $ticket->save();

            return $this->recordAttempt($ticket, 'valido', $dto, $operatorId);
        });
    }

    private function recordAttempt(Ticket $ticket, string $result, CheckinTicketDTO $dto, int $operatorId): CheckinResultDTO
    {
        $checkin = TicketCheckin::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'gate_name' => $dto->gateName,
            'operator_id' => $operatorId,
            'checked_in_at' => now(),
            'result' => $result,
            'device_info' => $dto->deviceInfo,
        ]);

        event(new TicketCheckedIn(ticketUuid: $ticket->uuid, result: $result, actorId: $operatorId));

        return new CheckinResultDTO($result, $ticket, $checkin);
    }

    private function locateTicket(int $tenantId, CheckinTicketDTO $dto): ?Ticket
    {
        $query = Ticket::where('tenant_id', $tenantId);

        if ($dto->qrToken !== null) {
            $query->where('qr_token', $dto->qrToken);
        }

        if ($dto->code !== null) {
            $query->where('code', $dto->code);
        }

        if ($dto->saleUuid !== null) {
            $query->whereHas('orderItem.order', fn($q) => $q->where('uuid', $dto->saleUuid));
        }

        if ($dto->attendeeName !== null) {
            $query->where('attendee_name', 'like', '%' . $dto->attendeeName . '%');
        }

        if ($dto->attendeeDocument !== null) {
            $query->where('attendee_document', $dto->attendeeDocument);
        }

        return $query->orderByDesc('id')->first();
    }
}
