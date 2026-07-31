<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha o risco de cobrança/assinatura duplicada quando a chamada ao PSP
 * (Mercado Pago) dá timeout/erro de rede DEPOIS que ele já processou do
 * lado dele, mas a resposta nunca chega ao backend. Antes desta tabela, um
 * retry gerava um X-Idempotency-Key novo (Str::uuid() a cada chamada), e o
 * Mercado Pago tratava a repetição como uma cobrança/assinatura nova.
 *
 * `operation` é a identidade lógica ESTÁVEL da operação (ex.:
 * "order_charge:{order_uuid}", "preapproval_create:{subscription_uuid}",
 * "preapproval_change:{subscription_uuid}") — não um id de tentativa. A
 * unique composta (tenant_id, operation) garante no máximo 1 tentativa "em
 * voo" por operação lógica por vez.
 *
 * `locked_until`: TTL do lock (ver services.mercadopago.
 * idempotency_lock_seconds) — tempo suficiente para cobrir um timeout de
 * rede real sem travar o usuário indefinidamente se a tentativa original
 * realmente falhou rápido.
 *
 * Não estende BaseModel/tabela de domínio de negócio (sem soft delete,
 * sem created_by/updated_by) — mesmo raciocínio de `webhook_deliveries`:
 * é um controle técnico interno gerado pelo próprio backend, não uma ação
 * de usuário staff.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('operation');
            $table->uuid('idempotency_key');
            $table->string('status')->default('pending');
            $table->json('provider_response')->nullable();
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'operation'], 'uniq_tenant_operation');
            $table->index(['status', 'locked_until'], 'idx_payment_idempotency_status_locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_idempotency_keys');
    }
};
