<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço de um plano por período de cobrança (roadmap 1B — cobrança de
 * planos). O desconto trimestral/anual é congelado aqui (discount_percent),
 * não hardcoded no código. `amount` é o preço de tabela do período ANTES do
 * desconto; InvoiceService aplica discount_percent sobre ele. Dinheiro em
 * decimal(10,2), padrão do projeto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 20); // monthly | quarterly | yearly
            $table->decimal('amount', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'billing_period', 'is_active'], 'idx_plan_price_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
