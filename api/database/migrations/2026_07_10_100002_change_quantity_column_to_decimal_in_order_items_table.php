<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_07_10_100000_..._stock_balances_table — mesmo motivo (Fase 8,
 * quantidade fracionária real). unit_price/line_total continuam decimal(10,2)
 * (preço em reais), só quantity muda.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
    }
};
