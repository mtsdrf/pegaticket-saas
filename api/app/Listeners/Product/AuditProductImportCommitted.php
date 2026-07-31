<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductImportCommitted;
use App\Models\AuditLog;

class AuditProductImportCommitted
{
    /**
     * `product_uuids` fica limitado às 100 primeiras (amostra) — meta é
     * JSON em `audit_logs`, gravar até 2000 uuids por importação infla a
     * tabela sem necessidade (contadores já dão o resumo auditável).
     */
    private const UUID_SAMPLE_LIMIT = 100;

    public function handle(ProductImportCommitted $event): void
    {
        AuditLog::record(
            event: 'product_import_committed',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
                'created_count' => $event->createdCount,
                'skipped_count' => $event->skippedCount,
                'categories_created_count' => $event->categoriesCreatedCount,
                'types_created_count' => $event->typesCreatedCount,
                'product_uuids_sample' => array_slice($event->productUuids, 0, self::UUID_SAMPLE_LIMIT),
                'product_uuids_truncated' => count($event->productUuids) > self::UUID_SAMPLE_LIMIT,
            ],
            actorId: $event->actorId
        );
    }
}
