<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicação transacional mínima (roadmap Fase 1 — lembrete de evento):
 * marca quando o lembrete de ingresso já foi enviado para a venda, evitando
 * reenvio duplicado a cada execução de EventReminderCommand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
