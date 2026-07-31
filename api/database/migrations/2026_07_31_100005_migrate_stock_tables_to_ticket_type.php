<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque (roadmap PegaTicket, seção 2.4 do roadmap de migração) continua
 * vivo nesta rodada — troca mecânica de `product_id` por `ticket_type_id`,
 * sem redesenho de semântica. Semântica de estoque físico deveria ser
 * revista quando `Batch`/lote existir (roadmap seção 2.8).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropUnique('uniq_stock_balance_product_location');
            $table->renameColumn('product_id', 'ticket_type_id');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
            $table->unique(['ticket_type_id', 'location_id'], 'uniq_stock_balance_ticket_type_location');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->renameColumn('product_id', 'ticket_type_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
            $table->dropUnique('uniq_stock_balance_ticket_type_location');
            $table->renameColumn('ticket_type_id', 'product_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
            $table->renameColumn('ticket_type_id', 'product_id');
        });
    }
};
