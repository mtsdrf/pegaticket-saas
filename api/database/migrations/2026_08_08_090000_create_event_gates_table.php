<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EventGate = "Portaria" formal, opcional. `ticket_checkins.gate_name`
 * continua uma string livre digitada pelo operador (não é FK pra cá) —
 * quando o valor digitado bate com o `name` de um EventGate ativo cadastrado
 * NO EVENTO do ingresso, CheckinService passa a validar restrição de tipo
 * de ingresso (ver event_gate_ticket_types); se não bater com nenhum
 * EventGate, o comportamento antigo (sem validação) é preservado — recurso
 * é aditivo/opt-in, nunca regressivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_gates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gates');
    }
};
