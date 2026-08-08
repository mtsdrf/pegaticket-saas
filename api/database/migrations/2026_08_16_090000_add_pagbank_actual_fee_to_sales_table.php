<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase R5 (roadmap docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md,
 * seção 6, gap 2.9 / decisão #1 da seção 5) — custo REAL do PSP por venda,
 * distinto da estimativa usada hoje só no simulador
 * (TicketFeeSimulationService::estimated_*_processing_percentage*).
 *
 * `pagbank_fee_actual` fica nullable porque, na data desta migration, a doc
 * oficial do PagBank (GET /orders/{id} e GET /charges/{id}) não expõe
 * nenhum campo de tarifa/fee cobrado na resposta do Order/Charge — ver
 * PagBankPaymentProvider::extractActualFeeCents() para o TODO de
 * confirmação do campo antes de produção. A coluna já fica pronta para
 * receber o valor assim que o campo for confirmado, sem nova migration.
 *
 * `platform_net_revenue` (platform_fee_total_amount - pagbank_fee_actual)
 * é deliberadamente NÃO persistido como coluna — é sempre derivado
 * (Sale::platformNetRevenue()), só quando pagbank_fee_actual estiver
 * disponível, para nunca divergir do par de valores-fonte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('pagbank_fee_actual', 10, 2)->nullable()->after('platform_fee_payer_snapshot');
            $table->dateTime('pagbank_fee_actual_captured_at')->nullable()->after('pagbank_fee_actual');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['pagbank_fee_actual', 'pagbank_fee_actual_captured_at']);
        });
    }
};
