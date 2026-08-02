<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o conceito de entrega física de sales — decisão confirmada
 * 2026-08-01: PegaTicket é bilheteria de eventos, não delivery/comércio
 * físico (resíduo do produto antigo "Maskats"). Mantém propositalmente:
 * - is_delivered/delivered_at: continuam sendo o gate de conclusão do
 *   pedido usado na cascata de pagamento (payInstallment/performDelivery)
 *   e nos relatórios de operação/financeiro — não são exclusivos de
 *   entrega física, remover exige redesenho do estado do pedido
 *   (decisão de produto pendente, ver relatório da tarefa).
 * - needs_change/change_for_amount: troco de pagamento em dinheiro,
 *   aplicável também a venda presencial (guichê), não é exclusivo de
 *   "pagar na entrega" (que nem existe como payment_method hoje).
 * - service_fee: taxa de serviço do fluxo presencial, distinta de
 *   entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'delivery_fee')) {
                $table->dropColumn('delivery_fee');
            }

            if (Schema::hasColumn('sales', 'expected_delivery_date')) {
                $table->dropColumn('expected_delivery_date');
            }

            if (Schema::hasColumn('sales', 'fulfillment_type')) {
                $table->dropColumn('fulfillment_type');
            }

            if (Schema::hasColumn('sales', 'is_out_for_delivery')) {
                $table->dropColumn('is_out_for_delivery');
            }

            if (Schema::hasColumn('sales', 'out_for_delivery_at')) {
                $table->dropColumn('out_for_delivery_at');
            }
        });
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático (mesmo padrão de
        // 2026_08_01_110000_drop_allow_delivery_from_tenant_settings_table.php).
    }
};
