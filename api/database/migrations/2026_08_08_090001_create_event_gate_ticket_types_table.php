<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot simples (sem uuid/timestamps próprios, mesmo padrão de outros
 * pivots do projeto) que restringe OPCIONALMENTE quais ticket_types podem
 * entrar por uma portaria. Sem nenhuma linha para um event_gate = aceita
 * QUALQUER ticket_type daquele evento (comportamento aberto por padrão,
 * ver App\Services\Ticket\CheckinService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_gate_ticket_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_gate_id')
                ->constrained('event_gates')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['event_gate_id', 'ticket_type_id'], 'uniq_event_gate_ticket_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gate_ticket_types');
    }
};
