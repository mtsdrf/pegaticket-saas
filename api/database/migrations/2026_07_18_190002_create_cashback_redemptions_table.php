<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de resgate de cashback (roadmap Delivery, Fase 5) — mesmo desvio
 * deliberado de coupon_redemptions: sem BaseModel/soft delete, só criado
 * pelo sistema dentro da transação de checkout. 1 linha por lote de
 * cashback_earnings consumido (um resgate pode gerar mais de uma linha se
 * drenar mais de um lote via FIFO por expires_at). Removida por
 * OrderService::reject(), que também devolve o valor a remaining_amount do
 * lote de origem.
 *
 * order_id nullable de propósito: CashbackService::reserveRedemption()
 * roda ANTES de Order existir (o valor reservado precisa ser conhecido pra
 * calcular orders.total_amount), preenchido logo depois via
 * attachRedemptionToOrder() — mesma transação do checkout inteira, nunca
 * fica órfão de fato (rollback desfaz tudo se algo falhar no meio).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashback_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('final_customer_id')->constrained('final_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('cashback_earning_id')->constrained('cashback_earnings')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_redemptions');
    }
};
