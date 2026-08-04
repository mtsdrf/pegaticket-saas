<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentual padrão de comissão de afiliado do tenant — nullable, mesmo
 * padrão de hold_duration_minutes/minimum_order_value: ausência = estado
 * neutro (Affiliate.commission_percentage próprio ou nada). A feature só
 * "liga" quando o tenant tem pelo menos 1 affiliate ativo, sem toggle
 * separado. Default técnico documentado em AffiliateCommissionService —
 * NÃO validado com o usuário (percentual e regra de aprovação de afiliado
 * são decisão de negócio real, fora do escopo desta migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->decimal('affiliate_default_commission_percentage', 5, 2)->nullable()->after('hold_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('affiliate_default_commission_percentage');
        });
    }
};
