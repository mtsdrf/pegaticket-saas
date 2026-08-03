<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régua de reativação de cliente (roadmap A5, item 18) — tabela singleton
 * por tenant, mesmo padrão de tenant_settings (findOrCreateForTenant):
 * cliente sem venda há days_without_order dias recebe cupom automático
 * (coupon_type/coupon_value/coupon_validity_days) + push nativo, via
 * comando agendado reactivation:process. is_active=false (default) não
 * dispara nada — tenant precisa configurar e ativar explicitamente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reactivation_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('days_without_order')->default(30);
            $table->string('coupon_type', 20)->default('percentage');
            $table->decimal('coupon_value', 10, 2)->default(10);
            $table->unsignedInteger('coupon_validity_days')->default(7);
            $table->boolean('is_active')->default(false);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id'], 'uniq_reactivation_rule_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivation_rules');
    }
};
