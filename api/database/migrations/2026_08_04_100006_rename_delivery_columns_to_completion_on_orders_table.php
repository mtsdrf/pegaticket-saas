<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_delivered`/`delivered_at` nunca representou entrega física (já
 * removida em 2026_08_01) — era o gate de conclusão da venda (edição de
 * itens, quitação de parcela, elegibilidade de cancelamento, relatório
 * financeiro). Renomeado para refletir a semântica real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->renameColumn('is_delivered', 'is_completed');
            $table->renameColumn('delivered_at', 'completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->renameColumn('is_completed', 'is_delivered');
            $table->renameColumn('completed_at', 'delivered_at');
        });
    }
};
