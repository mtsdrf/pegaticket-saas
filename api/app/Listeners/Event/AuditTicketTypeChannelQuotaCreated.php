<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeChannelQuotaCreated;
use App\Models\AuditLog;

class AuditTicketTypeChannelQuotaCreated
{
    public function handle(TicketTypeChannelQuotaCreated $event): void
    {
        AuditLog::record(
            event: 'ticket_type_channel_quota_created',
            model: null,
            meta: ['ticket_type_channel_quota_uuid' => $event->ticketTypeChannelQuotaUuid],
            actorId: $event->actorId
        );
    }
}
