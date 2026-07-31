<?php

namespace App\Listeners\Location;

use App\Events\Location\EnderecoUpdated;
use App\Models\AuditLog;

class AuditEnderecoUpdated
{
    public function handle(EnderecoUpdated $event): void
    {
        AuditLog::record(
            event: 'endereco_updated',
            model: null,
            meta: [
                'endereco_uuid' => $event->enderecoUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
