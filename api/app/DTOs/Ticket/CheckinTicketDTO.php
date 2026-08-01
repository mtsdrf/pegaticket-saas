<?php

namespace App\DTOs\Ticket;

/**
 * Shape de POST /tickets/checkin: identifica o ingresso por `qr_token`
 * (leitura de câmera) OU por busca manual (`code`, `sale_uuid`,
 * `attendee_name`, `attendee_document` — combináveis, todos aplicados em
 * AND; ver CheckinService::locateTicket()). `event_uuid`/`event_session_uuid`
 * são opcionais — quando informados (operador selecionou evento/sessão na
 * portaria, spec 5.16), validam que o ingresso pertence àquele
 * evento/sessão antes de liberar.
 */
class CheckinTicketDTO
{
    public function __construct(
        public readonly ?string $qrToken,
        public readonly ?string $code,
        public readonly ?string $saleUuid,
        public readonly ?string $attendeeName,
        public readonly ?string $attendeeDocument,
        public readonly ?string $eventUuid,
        public readonly ?string $eventSessionUuid,
        public readonly ?string $gateName,
        public readonly ?string $deviceInfo,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            qrToken: $data['qr_token'] ?? null,
            code: isset($data['code']) ? strtoupper($data['code']) : null,
            saleUuid: $data['sale_uuid'] ?? null,
            attendeeName: $data['attendee_name'] ?? null,
            attendeeDocument: $data['attendee_document'] ?? null,
            eventUuid: $data['event_uuid'] ?? null,
            eventSessionUuid: $data['event_session_uuid'] ?? null,
            gateName: $data['gate_name'] ?? null,
            deviceInfo: $data['device_info'] ?? null,
        );
    }
}
