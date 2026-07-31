<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();

            $table->boolean('is_installment')->default(false)->index();

            // Sempre calculado no backend a partir da soma dos order_items,
            // nunca confiado no valor enviado pelo request.
            $table->decimal('total_amount', 10, 2);

            $table->boolean('is_paid')->default(false)->index();
            $table->timestamp('paid_at')->nullable();

            $table->boolean('is_delivered')->default(false)->index();
            $table->timestamp('delivered_at')->nullable();

            // Só relevante para pedido não parcelado (calculado no backend);
            // pedido parcelado tem vencimento por parcela em order_installments.
            $table->date('due_date')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
