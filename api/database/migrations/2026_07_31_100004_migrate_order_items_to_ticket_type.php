<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OrderItem passa a apontar para TicketType OU EventProduct (exatamente um
 * preenchido por item, garantido em OrderService, não em constraint de
 * banco) — substitui `product_id` (FK única pra `products`, que está sendo
 * removida). Banco de dev zerado (sem dado a migrar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->after('order_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->foreignId('event_product_id')
                ->nullable()
                ->after('ticket_type_id')
                ->constrained('event_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
            $table->dropForeign(['event_product_id']);
            $table->dropColumn(['ticket_type_id', 'event_product_id']);
        });
    }
};
