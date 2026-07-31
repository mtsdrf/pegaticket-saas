<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        // Evita auditoria durante seed/migrations (console)
        if (app()->runningInConsole()) {
            return;
        }

        static::created(fn($model) => AuditLog::record('created', $model));
        static::updated(fn($model) => AuditLog::record('updated', $model));
        static::deleted(fn($model) => AuditLog::record('deleted', $model));
    }

    /**
     * Retorna [oldValues, newValues] dependendo do evento.
     */
    protected function auditOldNewValues(string $event): array
    {
        $old = null;
        $new = null;

        if ($event === 'updated') {
            $old = $this->getOriginal();
            $new = $this->getAttributes();
        } elseif ($event === 'created') {
            $new = $this->getAttributes();
        } elseif ($event === 'deleted') {
            $old = $this->getOriginal();
        }

        return [$old, $new];
    }
}