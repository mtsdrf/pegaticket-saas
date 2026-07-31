<?php

namespace App\Listeners\Location;

use App\Events\Location\EnderecoDeleted;
use App\Models\AuditLog;

class AuditEnderecoDeleted
{
    public function handle(EnderecoDeleted $event): void
    {
        AuditLog::record(
            event: 'endereco_deleted',
            model: null,
            meta: ['endereco_uuid' => $event->enderecoUuid],
            actorId: $event->actorId
        );
    }
}
