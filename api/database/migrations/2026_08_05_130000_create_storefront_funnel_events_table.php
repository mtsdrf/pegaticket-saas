<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funil de conversão anônimo (roadmap A2, decisão 2026-08-05 §7.1 item 3):
 * evento de página/etapa vista, keyed por `session_id` técnico do carrinho
 * (mesmo `session_id` de `cart_events`) — sem dado pessoal. Sem
 * `created_by`/`updated_by`/`deleted_by`/soft delete: mesmo espírito de
 * `cart_events`/`webhook_events`, log de evento append-only, não domínio de
 * staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('session_id');
            $table->string('step', 40)->index();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'event_id', 'step', 'created_at'], 'idx_funnel_events_tenant_event_step_date');
            $table->index(['tenant_id', 'event_id', 'session_id'], 'idx_funnel_events_tenant_event_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_funnel_events');
    }
};
