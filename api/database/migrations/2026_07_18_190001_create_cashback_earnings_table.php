<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de crédito de cashback (roadmap Delivery, Fase 5) — 1 linha por
 * pedido pago que gerou crédito. remaining_amount começa igual a amount e
 * só decresce via resgate (cashback_redemptions) ou zera na expiração/
 * reversão — nunca sobrescrito fora desse fluxo. status pending→available
 * é promovido pelo comando agendado cashback:process (carência configurável
 * por tenant); available→expired também por ele, quando expires_at vence
 * com saldo ainda não consumido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashback_earnings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('final_customer_id')->constrained('final_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('available_at');
            $table->timestamp('expires_at');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'final_customer_id', 'status'], 'idx_cashback_earnings_balance');
            $table->index(['status', 'available_at'], 'idx_cashback_earnings_hold');
            $table->index(['status', 'expires_at'], 'idx_cashback_earnings_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_earnings');
    }
};
