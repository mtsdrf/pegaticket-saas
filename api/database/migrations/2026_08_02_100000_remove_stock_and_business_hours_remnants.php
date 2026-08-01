<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Continuação de 2026_08_01_100000_remove_address_and_stock_legacy_structures
 * — aquela migration dropou as tabelas de estoque (stock_locations/
 * stock_balances/stock_movements) e tornou orders.stock_location_id
 * nullable, mas deixou a coluna em si e store_business_hours pra trás.
 * Controle de venda de ingresso passa a ser só TicketType.quantity_available/
 * TicketBatch.quantity_sold — sem local de estoque físico nem horário de
 * funcionamento de loja (evento tem starts_at/ends_at próprio).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'stock_location_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_location_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'stock_reserved')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_reserved');
            });
        }

        if (Schema::hasTable('tenant_settings') && Schema::hasColumn('tenant_settings', 'block_order_without_stock')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                $table->dropColumn('block_order_without_stock');
            });
        }

        Schema::dropIfExists('store_business_hours');
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
