<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeChannelQuotaUpdated;
use App\Models\AuditLog;

class AuditTicketTypeChannelQuotaUpdated
{
    public function handle(TicketTypeChannelQuotaUpdated $event): void
    {
        AuditLog::record(
            event: 'ticket_type_channel_quota_updated',
            model: null,
            meta: [
                'ticket_type_channel_quota_uuid' => $event->ticketTypeChannelQuotaUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
