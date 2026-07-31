<?php

namespace App\Events\Product;

/**
 * Evento AGREGADO de 1 chamada de ProductImportService::commit() (roadmap
 * A2) — decisão deliberada de não disparar 1 ProductCreated por produto
 * importado (até 2000 por commit): volume alto tornaria a fila de
 * listeners/gravação de audit_logs pesada sem ganho real de rastreio (o
 * "quem/quando importou" já é coberto por este evento único). Categorias/
 * tipos criados durante a importação continuam dedicados 1:1
 * (ProductCategoryCreated/ProductTypeCreated), pois o volume é baixo.
 */
class ProductImportCommitted
{
    public function __construct(
        public int $tenantId,
        public int $actorId,
        public int $createdCount,
        public int $skippedCount,
        public int $categoriesCreatedCount,
        public int $typesCreatedCount,
        public array $productUuids,
    ) {
    }
}
