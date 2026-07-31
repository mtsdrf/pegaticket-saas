<?php

namespace App\Listeners\Location;

use App\Events\Location\EnderecoCreated;
use App\Models\AuditLog;

class AuditEnderecoCreated
{
    public function handle(EnderecoCreated $event): void
    {
        AuditLog::record(
            event: 'endereco_created',
            model: null,
            meta: ['endereco_uuid' => $event->enderecoUuid],
            actorId: $event->actorId
        );
    }
}
