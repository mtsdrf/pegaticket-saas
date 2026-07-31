<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMV real daqui pra frente (roadmap A3.13, decisão registrada em
 * architecture-decisions.md). `unit_cost` só é preenchido em movimentações
 * `type=entry` a partir de agora; entradas antigas ficam `null` (sem
 * retroatividade) e são ignoradas no cálculo de custo médio ponderado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
