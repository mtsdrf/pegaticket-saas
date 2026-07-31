<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula a venda de balcão à sessão de caixa (roadmap PDV, Fase PDV-1) — só
 * preenchida em pedidos origin='pdv'. nullOnDelete() (não cascade): apagar a
 * sessão não pode apagar o pedido histórico, mesmo espírito de orders.coupon_id.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('stock_location_id')
                ->constrained('cash_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });
    }
};
