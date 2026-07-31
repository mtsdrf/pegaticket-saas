<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limite percentual de desconto por perfil (roadmap A1.5). Nullable = sem
 * limite configurado, preserva 100% o comportamento atual (sem restrição)
 * — só passa a existir restrição quando um admin/dono explicitamente
 * configura um valor pra alguma linha (tenant_role, functionality=orders,
 * qualquer action) do perfil. Decisão de onde exatamente o valor é lido
 * (qual linha) documentada em architecture-decisions.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_role_permissions', function (Blueprint $table) {
            $table->decimal('discount_limit_percent', 5, 2)->nullable()->after('action_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_role_permissions', function (Blueprint $table) {
            $table->dropColumn('discount_limit_percent');
        });
    }
};
