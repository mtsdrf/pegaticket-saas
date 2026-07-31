<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurador de formas de entrega — equivalente simétrico de
 * allow_store_pickup pra entrega. default true preserva 100% o
 * comportamento atual (entrega sempre foi implicitamente aceita, nenhum
 * tenant existente pode perder a única forma de recebimento que já usava).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('allow_delivery')->default(true)->after('allow_store_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('allow_delivery');
        });
    }
};
