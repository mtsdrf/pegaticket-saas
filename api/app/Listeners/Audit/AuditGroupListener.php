<?php

namespace App\Listeners\Audit;

use App\Models\AuditLog;

class AuditGroupListener
{
    public function handle(object $event): void
    {
        AuditLog::record(
            event: class_basename($event),
            model: null,
            meta: get_object_vars($event),
            actorId: $event->actorId ?? null
        );
    }
}