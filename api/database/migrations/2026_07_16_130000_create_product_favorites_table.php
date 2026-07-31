<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favorito de produto pelo cliente final (roadmap Delivery, Fase 4 —
 * retenção). Mesmo desvio deliberado de final_customer_tenant_links/
 * coupon_redemptions: sem BaseModel/soft delete/created_by (sempre
 * criado/removido pelo próprio cliente final via toggle, nunca por staff).
 * Só created_at faz sentido (toggle é create/delete, não update).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['final_customer_id', 'product_id'], 'uniq_final_customer_product_favorite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_favorites');
    }
};
