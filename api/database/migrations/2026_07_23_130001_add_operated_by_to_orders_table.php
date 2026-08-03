<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operador identificado por PIN (roadmap A4, item 15) que efetivamente
 * registrou a venda interna da época — distinto de `created_by` (sempre o
 * usuário do JWT da sessão) e de `cash_sessions.opened_by` (quem abriu o
 * caixa naquele fluxo legado). Nullable: só era setado quando aquele fluxo
 * operacional informava um operador resolvido; venda comum permanece null.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Achado durante migrate:fresh (2026-07-31, fora do escopo da
            // migração Client->FinalCustomer): ->after('cash_session_id')
            // referenciava uma coluna que nunca chegou a existir em
            // nenhuma migration deste repositório — bug pré-existente,
            // quebrava migrate:fresh do zero. Removido o ->after() (só
            // afeta ordem cosmética de coluna, sem efeito em dado/schema).
            $table->unsignedBigInteger('operated_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('operated_by');
        });
    }
};
