<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coluna `invoices.fiscal_document_id` já existe (nullable, indexada)
 * desde a Onda 1, sem FK — a tabela `fiscal_documents` só passou a existir
 * agora (Onda 2B). Esta migration só adiciona a FK que faltava.
 * nullOnDelete: apagar o documento fiscal não deve apagar a fatura, só
 * soltar o vínculo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('fiscal_document_id')
                ->references('id')
                ->on('fiscal_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['fiscal_document_id']);
        });
    }
};
