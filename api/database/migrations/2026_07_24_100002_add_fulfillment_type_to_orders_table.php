<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retirada na loja (roadmap Delivery) — 'delivery'|'pickup', persistido no
 * pedido (imutável depois de criado, mesmo espírito de origin/status).
 * default 'delivery' preserva 100% o comportamento atual.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type', 20)->default('delivery')->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });
    }
};
