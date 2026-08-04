<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automação de recompra (Fase 6, fatia final) — mesmo espírito de
 * `reminder_sent_at`: marca a venda PAGA MAIS RECENTE de um comprador (por
 * tenant) que já recebeu o e-mail de "sentimos sua falta", evitando reenvio
 * enquanto ele não compra de novo. Ver SendRecompraNudgeMailsCommand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('recompra_nudge_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('recompra_nudge_sent_at');
        });
    }
};
