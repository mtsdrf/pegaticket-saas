<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de risco básico (roadmap Fase 7) — flag informativo, nunca
 * bloqueia a venda. Setado por RiskEngineService::evaluateSalePaid()
 * quando o pagamento confirmado dispara SalePaid e a heurística de
 * "múltiplas compras do mesmo cliente/e-mail para o mesmo evento em
 * pouco tempo" (sinal clássico de revenda/scalping automatizado) bate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('risk_flagged')->default(false)->after('status');
            $table->string('risk_reason')->nullable()->after('risk_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['risk_flagged', 'risk_reason']);
        });
    }
};
