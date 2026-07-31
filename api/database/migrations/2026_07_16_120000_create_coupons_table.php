<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupom de desconto sobre o carrinho todo (roadmap Delivery, Fase 3) —
 * decisão travada com o usuário: sem restrição por produto/categoria, só
 * elegibilidade geral (ativo/janela de datas/mínimo/limites de uso). `value`
 * é nullable porque free_shipping não usa (o desconto ali é sempre igual à
 * taxa de entrega resolvida no checkout, não um valor configurado aqui).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('code');
            $table->string('type', 20);
            $table->decimal('value', 10, 2)->nullable();
            $table->decimal('minimum_order_value', 10, 2)->nullable();
            $table->unsignedInteger('max_uses_total')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'uniq_tenant_coupon_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
