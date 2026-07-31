<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operador identificado por PIN (roadmap A4, item 15) que efetivamente
 * bateu a venda no PDV — distinto de `created_by` (sempre o usuário do JWT
 * da sessão, tipicamente o caixa "dono" do terminal) e de
 * `cash_sessions.opened_by` (quem abriu o caixa). Nullable: só é setado
 * quando a venda de PDV informa um `operator_uuid` resolvido via
 * /pdv/operator-session; pedido comum (não-PDV) fica null.
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
