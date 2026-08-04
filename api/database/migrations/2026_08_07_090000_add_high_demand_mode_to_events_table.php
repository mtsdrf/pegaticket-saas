<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila virtual para alta demanda (roadmap Fase 7). Opt-in por evento —
 * default false não afeta eventos existentes. Quando ativado,
 * StorefrontHoldService::createHold() passa a exigir uma
 * VirtualQueueEntry com status='admitted' para o session_token antes de
 * reservar de verdade. `virtual_queue_admission_batch_size` é o limite de
 * admissões simultâneas processado por AdmitVirtualQueueEntriesCommand —
 * default técnico (50) documentado como NÃO validado com o usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('high_demand_mode')->default(false)->after('status');
            $table->unsignedInteger('virtual_queue_admission_batch_size')->default(50)->after('high_demand_mode');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['high_demand_mode', 'virtual_queue_admission_batch_size']);
        });
    }
};
