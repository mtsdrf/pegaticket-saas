<?php

namespace App\Services\Ticket;

use App\DTOs\Ticket\CheckinResultDTO;
use App\DTOs\Ticket\CheckinTicketDTO;
use App\Events\Ticket\TicketCheckedIn;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use Carbon\CarbonInterface;
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
    private const ACCESS_TYPE_ENTRY = 'entrada';
    private const ACCESS_TYPE_REENTRY = 'reentrada';
    private const ACCESS_TYPE_ATTEMPT = 'tentativa';

    /**
     * Resultados fora do enum principal (ex.: status futuro fora dos
     * valores hoje emitidos por TicketIssuanceService) caem em `bloqueado`
     * como fallback seguro — nunca libera entrada por status desconhecido.
     */
    private const STATUS_TO_RESULT = [
        'cancelado' => 'cancelado',
        'estornado' => 'estornado',
        'bloqueado' => 'bloqueado',
    ];

    private const SUCCESSFUL_ACCESS_RESULTS = ['valido', 'reentrada_autorizada'];

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
                return $this->recordAttempt(
                    $ticket,
                    'evento_incorreto',
                    $dto,
                    $operatorId,
                    self::ACCESS_TYPE_ATTEMPT,
                    'evento_incorreto'
                );
            }

            if ($dto->eventSessionUuid !== null && optional($ticket->ticketType->session)->uuid !== $dto->eventSessionUuid) {
                return $this->recordAttempt(
                    $ticket,
                    'sessao_incorreta',
                    $dto,
                    $operatorId,
                    self::ACCESS_TYPE_ATTEMPT,
                    'sessao_incorreta'
                );
            }

            if ($ticket->status === 'utilizado') {
                if ($dto->allowReentry) {
                    $policy = $this->resolveReentryPolicy($ticket);

                    if (!$policy['enabled']) {
                        return $this->recordAttempt(
                            $ticket,
                            'reentrada_nao_permitida',
                            $dto,
                            $operatorId,
                            self::ACCESS_TYPE_ATTEMPT,
                            'reentry_disabled'
                        );
                    }

                    $currentReentries = $this->countAuthorizedReentries($ticket);

                    if ($policy['max_reentries'] !== null && $currentReentries >= $policy['max_reentries']) {
                        return $this->recordAttempt(
                            $ticket,
                            'reentrada_limite_excedido',
                            $dto,
                            $operatorId,
                            self::ACCESS_TYPE_ATTEMPT,
                            'reentry_limit_exceeded'
                        );
                    }

                    if ($policy['reentry_cooldown_minutes'] !== null && $policy['reentry_cooldown_minutes'] > 0) {
                        $lastAccessAt = $this->lastSuccessfulAccessAt($ticket);

                        if (
                            $lastAccessAt instanceof CarbonInterface
                            && now()->lt($lastAccessAt->copy()->addMinutes($policy['reentry_cooldown_minutes']))
                        ) {
                            return $this->recordAttempt(
                                $ticket,
                                'reentrada_intervalo_nao_atingido',
                                $dto,
                                $operatorId,
                                self::ACCESS_TYPE_ATTEMPT,
                                'reentry_cooldown_active'
                            );
                        }
                    }

                    return $this->recordAttempt(
                        $ticket,
                        'reentrada_autorizada',
                        $dto,
                        $operatorId,
                        self::ACCESS_TYPE_REENTRY,
                        $dto->reason
                    );
                }

                return $this->recordAttempt(
                    $ticket,
                    'ja_utilizado',
                    $dto,
                    $operatorId,
                    self::ACCESS_TYPE_ATTEMPT,
                    'ticket_ja_utilizado'
                );
            }

            $blockedResult = self::STATUS_TO_RESULT[$ticket->status] ?? null;

            if ($blockedResult !== null) {
                return $this->recordAttempt(
                    $ticket,
                    $blockedResult,
                    $dto,
                    $operatorId,
                    self::ACCESS_TYPE_ATTEMPT,
                    'status_' . $ticket->status
                );
            }

            if ($ticket->status !== 'ativo') {
                // pendente ou qualquer status futuro fora do enum conhecido
                // — recusa por segurança, nunca libera entrada.
                return $this->recordAttempt(
                    $ticket,
                    'bloqueado',
                    $dto,
                    $operatorId,
                    self::ACCESS_TYPE_ATTEMPT,
                    'status_' . $ticket->status
                );
            }

            $ticket->status = 'utilizado';
            $ticket->save();

            return $this->recordAttempt($ticket, 'valido', $dto, $operatorId, self::ACCESS_TYPE_ENTRY, $dto->reason);
        });
    }

    private function recordAttempt(
        Ticket $ticket,
        string $result,
        CheckinTicketDTO $dto,
        int $operatorId,
        string $accessType,
        ?string $reason
    ): CheckinResultDTO
    {
        $checkin = TicketCheckin::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'gate_name' => $dto->gateName,
            'operator_id' => $operatorId,
            'checked_in_at' => now(),
            'result' => $result,
            'access_type' => $accessType,
            'reason' => $reason,
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
            $query->whereHas('saleItem.sale', fn($q) => $q->where('uuid', $dto->saleUuid));
        }

        if ($dto->attendeeName !== null) {
            $query->where('attendee_name', 'like', '%' . $dto->attendeeName . '%');
        }

        if ($dto->attendeeDocument !== null) {
            $query->where('attendee_document', $dto->attendeeDocument);
        }

        return $query->orderByDesc('id')->first();
    }

    private function resolveReentryPolicy(Ticket $ticket): array
    {
        $ticketType = $ticket->ticketType;
        $event = $ticketType?->event;

        $enabled = $ticketType?->reentry_enabled;

        if ($enabled === null) {
            $enabled = (bool) ($event?->reentry_enabled ?? false);
        }

        return [
            'enabled' => (bool) $enabled,
            'max_reentries' => $ticketType?->max_reentries ?? $event?->max_reentries,
            'reentry_cooldown_minutes' => $ticketType?->reentry_cooldown_minutes ?? $event?->reentry_cooldown_minutes,
        ];
    }

    private function countAuthorizedReentries(Ticket $ticket): int
    {
        return (int) $ticket->checkins()
            ->where('result', 'reentrada_autorizada')
            ->count();
    }

    private function lastSuccessfulAccessAt(Ticket $ticket): ?CarbonInterface
    {
        $lastCheckin = $ticket->checkins()
            ->whereIn('result', self::SUCCESSFUL_ACCESS_RESULTS)
            ->orderByDesc('checked_in_at')
            ->first();

        return $lastCheckin?->checked_in_at;
    }
}
