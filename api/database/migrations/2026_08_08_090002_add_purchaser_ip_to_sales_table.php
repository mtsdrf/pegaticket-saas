<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de risco (roadmap Fase 7, velocity por IP) — IP do comprador
 * capturado no checkout público (StorefrontCheckoutController::store()
 * via $request->ip()), nunca no fluxo staff (venda manual não tem IP de
 * comprador). 45 chars comporta IPv6 (RFC 5952, formato mais longo
 * possível incluindo IPv4-mapped). Nullable: vendas staff e vendas
 * antigas (antes desta coluna existir) ficam sem IP, RiskEngineService
 * trata ausência como "não avalia" essa heurística, nunca erro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('purchaser_ip', 45)->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('purchaser_ip');
        });
    }
};
