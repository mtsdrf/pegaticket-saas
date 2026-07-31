<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço promocional "de/por" por produto (roadmap Delivery, Fase 3) — upsert
 * 1 por produto, mesmo shape de store_delivery_fees. Sempre vence sobre
 * preço de categoria/base quando ativa e dentro da janela de datas (decisão
 * travada com o usuário: é o preço de venda público que o tenant decidiu).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->decimal('promo_price', 10, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'product_id'], 'uniq_tenant_product_promotion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
