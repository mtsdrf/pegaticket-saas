<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo EXPLÍCITO entre a identidade global do cliente final e uma loja
 * (tenant) onde ele confirmou ser o mesmo Client. Nunca criado
 * automaticamente por telefone/nome batendo entre tenants — só via
 * POST /portal/links, a partir de um sale_uuid que o cliente já tinha
 * (prova de posse, mesmo espírito do link público de rastreio da Fase 5.1).
 * Unique composta (final_customer_id, client_id): não faz sentido vincular
 * o mesmo Client duas vezes; linkar de novo é idempotente na Service, não
 * erro. Sem soft delete/created_by (mesmo desvio deliberado de
 * final_customers — não há ator staff neste fluxo).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('final_customer_tenant_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['final_customer_id', 'client_id'], 'uniq_final_customer_client_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_customer_tenant_links');
    }
};
