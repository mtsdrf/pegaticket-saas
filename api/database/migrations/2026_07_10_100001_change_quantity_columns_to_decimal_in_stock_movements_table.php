<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_07_10_100000_..._stock_balances_table — mesmo motivo (Fase 8,
 * quantidade fracionária real). balance_before/balance_after seguem o
 * campo de saldo que representam (on_hand/reserved/blocked), então também
 * precisam ser decimal(12,3).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('balance_before', 12, 3)->change();
            $table->decimal('balance_after', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
            $table->integer('balance_before')->change();
            $table->integer('balance_after')->change();
        });
    }
};
