<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Número sequencial de exibição pro cliente, por tenant (via
            // tenants.next_sale_code, ver OrderService::create()) —
            // nullable pra não quebrar vendas já existentes; preenchido
            // via `php artisan sales:backfill-codigo`.
            $table->string('codigo')->nullable()->after('uuid');

            $table->unique(['tenant_id', 'codigo'], 'uniq_tenant_order_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('uniq_tenant_order_codigo');
            $table->dropColumn('codigo');
        });
    }
};
