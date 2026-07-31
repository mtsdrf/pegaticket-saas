<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observação/recado por item do carrinho (roadmap Delivery — gap-analysis
 * de catálogo/cardápio) — distinto de orders.notes (recado do pedido
 * inteiro, já existente). Ex: "sem cebola", "bem passado".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('notes', 200)->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
