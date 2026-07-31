<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API pública + webhooks de saída (roadmap A6, item 20). Histórico mínimo
 * de entrega para debug/central de chamados futuro — 1 linha por tentativa
 * de POST (não por evento: um evento com 3 tentativas gera 3 linhas).
 * `tenant_id` denormalizado da subscription (convenção do projeto: toda
 * tabela de domínio carrega tenant_id direto, sem depender de join para
 * isolamento). Sem soft delete — é log de auditoria técnica, igual
 * `audit_logs`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_subscription_id')->constrained()->cascadeOnDelete();

            $table->string('event_type');
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'idx_webhook_deliveries_tenant_created');
            $table->index(['webhook_subscription_id', 'created_at'], 'idx_webhook_deliveries_subscription_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
