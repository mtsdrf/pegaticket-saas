<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Múltiplos turnos por dia (ex.: manhã + tarde) — remove a unique
 * (tenant_id, day_of_week) para permitir mais de uma linha por dia da
 * semana. Demais colunas permanecem iguais. A substituição em lote passa a
 * ser DELETE + INSERT (StoreBusinessHourRepository::upsertForTenant), sem
 * depender de unique key.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('store_business_hours', function (Blueprint $table) {
            // MySQL exige um índice cobrindo `tenant_id` pra sustentar a FK
            // antes de derrubar a unique composta que hoje faz esse papel —
            // sem isso, `dropUnique` falha com erro 1553 ("needed in a
            // foreign key constraint"), reproduzido ao aplicar na staging
            // (SQLite dos testes não tem essa exigência, por isso passou
            // despercebido localmente).
            $table->index('tenant_id', 'idx_store_business_hours_tenant_id');
            $table->dropUnique('uniq_tenant_business_hour_day');
        });
    }

    public function down(): void
    {
        Schema::table('store_business_hours', function (Blueprint $table) {
            $table->unique(['tenant_id', 'day_of_week'], 'uniq_tenant_business_hour_day');
            $table->dropIndex('idx_store_business_hours_tenant_id');
        });
    }
};
