<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase R2.6 (roadmap docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md,
 * seção 9.5.8/9.10) — snapshot, por venda, de qual receptor foi
 * efetivamente usado no split no momento da cobrança. Mesmo princípio já
 * aplicado à taxa de serviço PegaTicket (`platform_fee_*_snapshot`, ver
 * migration 2026_08_12_090000): `resolveSplitSettings()`/`buildSplitPayload()`
 * resolvem o destino do split em tempo real a partir da config ATUAL do
 * tenant a cada tentativa de cobrança — sem snapshot, uma reconciliação
 * futura recalcularia com a config vigente na reconsulta, não a
 * efetivamente usada na cobrança real.
 *
 * Fica em `sales` (não `sale_items`) porque o receptor do split é
 * resolvido por venda inteira, não por item — mesmo nível de
 * `platform_fee_payer_snapshot`.
 *
 * `tenant_receiver_provider` é deliberadamente provider-agnostic (string
 * livre, hoje só 'pagbank') — preparo para multi-gateway futuro sem
 * exigir abstração agora (mesma postura de "não overengineer" da seção
 * 9.4). Aditiva, nullable, sem default — não recalcula vendas antigas
 * (ficam com os 3 campos null, esperado: o conceito não existia antes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('tenant_receiver_provider')->nullable()->after('platform_fee_payer_snapshot');
            $table->string('tenant_receiver_account_id_snapshot')->nullable()->after('tenant_receiver_provider');
            $table->string('tenant_receiver_source_snapshot')->nullable()->after('tenant_receiver_account_id_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_receiver_provider',
                'tenant_receiver_account_id_snapshot',
                'tenant_receiver_source_snapshot',
            ]);
        });
    }
};
