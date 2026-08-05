<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeChannelQuotaDeleted;
use App\Models\AuditLog;

class AuditTicketTypeChannelQuotaDeleted
{
    public function handle(TicketTypeChannelQuotaDeleted $event): void
    {
        AuditLog::record(
            event: 'ticket_type_channel_quota_deleted',
            model: null,
            meta: ['ticket_type_channel_quota_uuid' => $event->ticketTypeChannelQuotaUuid],
            actorId: $event->actorId
        );
    }
}
