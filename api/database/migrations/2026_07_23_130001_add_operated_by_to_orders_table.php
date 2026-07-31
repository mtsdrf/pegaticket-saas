<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operador identificado por PIN (roadmap A4, item 15) que efetivamente
 * registrou a venda interna da época — distinto de `created_by` (sempre o
 * usuário do JWT da sessão) e de `cash_sessions.opened_by` (quem abriu o
 * caixa naquele fluxo legado). Nullable: só era setado quando aquele fluxo
 * operacional informava um operador resolvido; pedido comum permanece null.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('operated_by')->nullable()->index()->after('cash_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('operated_by');
        });
    }
};
