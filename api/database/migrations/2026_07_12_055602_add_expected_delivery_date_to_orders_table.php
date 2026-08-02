<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Informativo, capturado só na criação — distinto de
            // delivered_at, que continua sendo setado exclusivamente por
            // deliver()/a cascata de payInstallment(). Réplica do
            // data_entrega livre do legado, mas imutável após a criação
            // (decisão confirmada: Order não tem update genérico).
            $table->date('expected_delivery_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('expected_delivery_date');
        });
    }
};
