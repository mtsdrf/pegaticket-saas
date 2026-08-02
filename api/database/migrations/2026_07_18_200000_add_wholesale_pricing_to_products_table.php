<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço de atacado por quantidade (1 único patamar) — os dois nulos = sem
 * atacado configurado. Só se aplica a cliente SEM categoria (categoria
 * sempre vence quando existe, ver StorefrontCheckoutService::
 * resolveEffectiveUnitPrice()). wholesale_min_quantity usa decimal(10,3)
 * (mesma precisão fracionária de quantidade já usada em sale_items).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_min_quantity', 10, 3)->nullable()->after('price');
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('wholesale_min_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_min_quantity', 'wholesale_price']);
        });
    }
};
