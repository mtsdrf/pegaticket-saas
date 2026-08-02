<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('ticket_batch_id')
                ->nullable()
                ->after('event_product_id')
                ->constrained('ticket_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['ticket_batch_id']);
            $table->dropColumn('ticket_batch_id');
        });
    }
};
