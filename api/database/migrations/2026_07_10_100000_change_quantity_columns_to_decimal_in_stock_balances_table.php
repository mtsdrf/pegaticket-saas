<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 (migração de dados reais) encontrou quantidade fracionária real
 * (produto vendido por peso, ex.: 0.5kg) — quantity_on_hand/reserved/
 * blocked eram integer, incompatível com o dado real a migrar. decimal(12,3)
 * cobre até 3 casas decimais (grama), suficiente para peso/fração de
 * unidade sem perder precisão.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 12, 3)->default(0)->change();
            $table->decimal('quantity_reserved', 12, 3)->default(0)->change();
            $table->decimal('quantity_blocked', 12, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->unsignedInteger('quantity_on_hand')->default(0)->change();
            $table->unsignedInteger('quantity_reserved')->default(0)->change();
            $table->unsignedInteger('quantity_blocked')->default(0)->change();
        });
    }
};
