<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_completed`/`completed_at` era um gate manual de conclusão sem
 * efeito de negócio real (nunca mexeu em estoque/tickets) — removido a
 * venda do usuário: venda online só finaliza via retorno do PagBank
 * (is_paid, webhook), venda manual já nasce paga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Índice ainda registrado com o nome antigo (sales_is_delivered_index)
            // — a rename de coluna (2026_08_04_100006) não renomeou o índice.
            // Precisa cair antes do dropColumn, senão o SQLite quebra ao
            // reconstruir a tabela.
            $table->dropIndex('sales_is_delivered_index');
            $table->dropColumn(['is_completed', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('paid_at');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            $table->index('is_completed', 'sales_is_delivered_index');
        });
    }
};
