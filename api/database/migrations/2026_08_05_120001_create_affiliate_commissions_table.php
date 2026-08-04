<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro/cálculo de comissão gerado quando uma Sale com affiliate_id é
 * paga (AffiliateCommissionService, no mesmo gatilho SalePaid que já
 * dispara CreateReceivableOnSalePaid). Pagamento real da comissão ao
 * afiliado fica fora desta rodada (extensão futura do domínio Finance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();

            $table->decimal('sale_amount', 12, 2);
            $table->decimal('percentage_applied', 5, 2);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('sale_id', 'uniq_affiliate_commission_sale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
