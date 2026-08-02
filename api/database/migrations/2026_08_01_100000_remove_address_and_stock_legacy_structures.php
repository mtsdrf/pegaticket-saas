<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('final_customer_tenant_links') && Schema::hasColumn('final_customer_tenant_links', 'endereco_id')) {
            Schema::table('final_customer_tenant_links', function (Blueprint $table) {
                $table->dropForeign(['endereco_id']);
                $table->dropColumn('endereco_id');
            });
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'endereco_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign(['endereco_id']);
                $table->dropColumn('endereco_id');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'stock_location_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['stock_location_id']);
                $table->unsignedBigInteger('stock_location_id')->nullable()->change();
            });
        }

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('store_delivery_fees');
        Schema::dropIfExists('enderecos');
        Schema::dropIfExists('bairros');
        Schema::dropIfExists('cidades');
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
