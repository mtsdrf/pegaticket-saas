<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('stock_locations')
                ->cascadeOnDelete();

            // Só usado em type=transfer (local de destino).
            $table->foreignId('destination_location_id')
                ->nullable()
                ->constrained('stock_locations')
                ->cascadeOnDelete();

            $table->string('type')->index();
            $table->unsignedInteger('quantity');

            // Snapshot do campo relevante para o tipo de movimentação:
            // on_hand para entry/exit/adjustment_*/transfer/return/loss,
            // reserved para reserve/reserve_cancel, blocked para block/unblock.
            $table->integer('balance_before');
            $table->integer('balance_after');

            $table->string('reason');
            $table->text('notes')->nullable();

            // Reservado para o Venda apontar pra cá na Fase 5, e usado já
            // agora por reserve_cancel para apontar para o StockMovement
            // tipo reserve original.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
