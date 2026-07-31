<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override de preço de Product por ClientCategory (roadmap 2.4). Sync
 * (POST /products/{product}/category-prices/sync) sempre substitui o
 * conjunto inteiro via forceDelete()+recreate (ver ProductCategoryPriceRepository)
 * — nunca soft-deleta linha individual nesse fluxo, então a colisão
 * "unique não filtra deleted_at" (documentada em api-patterns.md para
 * order_installments) não se aplica aqui: não existe cenário onde uma
 * linha soft-deletada fica "ocupando" a constraint enquanto o mesmo par
 * product+category é reenviado. softDeletes() mantido só por consistência
 * de schema (database-rules.md), não é exercitado pelo fluxo real.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_category_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('client_category_id')->constrained('client_categories')->cascadeOnDelete();

            $table->decimal('price', 10, 2);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'product_id', 'client_category_id'],
                'uniq_product_category_price'
            );

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_prices');
    }
};
