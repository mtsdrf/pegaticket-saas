<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participantes informados no checkout (spec 5.10 Etapa 2 "Dados dos
 * participantes") por item de ticket_type — JSON temporário
 * [{name, document}, ...] consumido por TicketIssuanceService ao emitir os
 * Tickets (1 registro por unidade de quantity). SIMPLIFICAÇÃO DOCUMENTADA:
 * sem edição/preenchimento posterior nesta rodada (fora de escopo), sem
 * validação de questionário (spec cita "respostas de questionário" —
 * não implementado).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('attendee_data')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('attendee_data');
        });
    }
};
