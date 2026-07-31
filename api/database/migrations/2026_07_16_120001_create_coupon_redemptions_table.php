<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de uso de cupom (roadmap Delivery, Fase 3) — mesmo desvio deliberado
 * de final_customer_tenant_links: sem BaseModel/soft delete/created_by, só
 * criado pelo sistema dentro da transação de checkout, nunca editado por
 * staff. Usado para checar max_uses_total (count por coupon_id) e
 * max_uses_per_customer (count por coupon_id+final_customer_id) em
 * CouponService::validateForCheckout(). Removido por
 * OrderService::reject() quando o pedido recusado tinha cupom — pedido
 * recusado nunca deve consumir o limite de uso do cliente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('final_customer_id')->constrained('final_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
