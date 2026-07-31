<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliação do cliente final sobre um pedido já entregue (roadmap Delivery,
 * Fase 4 — retenção). Mesmo desvio deliberado de product_favorites: sem
 * BaseModel/soft delete/created_by. Unique em order_id garante 1 avaliação
 * por pedido (OrderRatingService checa antes de deixar o DB estourar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique('order_id', 'uniq_order_rating_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ratings');
    }
};
