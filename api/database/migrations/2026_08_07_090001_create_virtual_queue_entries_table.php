<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila virtual (roadmap Fase 7) — uma entrada por session_token+evento.
 * `position` é o número sequencial de entrada na fila (ordem de chegada,
 * atribuído em VirtualQueueService::enterOrStatus()). `admitted_at`
 * marca quando a entrada foi promovida por AdmitVirtualQueueEntriesCommand;
 * usado também para expirar a admissão depois de uma janela técnica
 * (ver VirtualQueueService::ADMISSION_WINDOW_MINUTES) e liberar o slot
 * para o próximo lote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_queue_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('final_customer_id')->nullable()->index();

            $table->string('session_token', 120);
            $table->unsignedBigInteger('position');
            $table->string('status', 20)->default('waiting')->index();
            $table->timestamp('admitted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'event_id', 'session_token'], 'uniq_virtual_queue_tenant_event_session');
            $table->index(['tenant_id', 'event_id', 'status'], 'idx_virtual_queue_tenant_event_status');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('final_customer_id')->references('id')->on('final_customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_queue_entries');
    }
};
