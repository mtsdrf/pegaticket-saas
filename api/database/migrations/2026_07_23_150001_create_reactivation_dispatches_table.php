<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de disparo da régua de reativação (roadmap A5, item 18) — 1
 * linha por cupom de reativação gerado para um cliente. Mesmo desvio
 * deliberado já usado em coupon_redemptions/final_customer_tenant_links:
 * sem BaseModel/soft delete/created_by, é só um log gerado pelo comando
 * agendado, nunca editado por staff. Usado por
 * ReactivationDispatchService::processTenant() para o cooldown (não gerar
 * novo cupom pro mesmo cliente antes do cupom anterior expirar — resolvido
 * via join com coupons.expires_at, não uma coluna própria) e, no futuro,
 * para reportar quantos clientes foram reativados.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reactivation_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();

            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'dispatched_at'], 'idx_reactivation_dispatch_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivation_dispatches');
    }
};
