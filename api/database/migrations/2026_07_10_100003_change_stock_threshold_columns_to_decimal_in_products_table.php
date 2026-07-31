<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * min_stock/max_stock/reorder_point/reorder_qty usam a mesma unidade de
 * medida do saldo (StockBalance.quantity_on_hand, agora decimal(12,3) —
 * ver 2026_07_10_100000_..._stock_balances_table), então também precisam
 * ser decimais para um limiar de "0.5kg" fazer sentido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_stock', 12, 3)->nullable()->change();
            $table->decimal('max_stock', 12, 3)->nullable()->change();
            $table->decimal('reorder_point', 12, 3)->nullable()->change();
            $table->decimal('reorder_qty', 12, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock')->nullable()->change();
            $table->integer('max_stock')->nullable()->change();
            $table->integer('reorder_point')->nullable()->change();
            $table->integer('reorder_qty')->nullable()->change();
        });
    }
};
