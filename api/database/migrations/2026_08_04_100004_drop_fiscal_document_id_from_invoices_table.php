<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placeholder do módulo Fiscal já cortado do roadmap.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'fiscal_document_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex('invoices_fiscal_document_id_index');
                $table->dropColumn('fiscal_document_id');
            });
        }
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
