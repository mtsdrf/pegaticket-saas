<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retirada na loja (roadmap Delivery) — habilita a opção do cliente final
 * retirar o pedido em vez de receber entrega. default false preserva 100%
 * o comportamento atual (nenhuma loja ganha pickup sem configurar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('allow_store_pickup')->default(false)->after('service_fee_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('allow_store_pickup');
        });
    }
};
