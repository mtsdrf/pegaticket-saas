<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreio de campanha (Fase 6, fatia 2) — capturado pelo frontend da loja
 * pública a partir de `?utm_source=/&utm_medium=/&utm_campaign=` na URL
 * (mesmo ponto onde `?ref=` já é capturado para affiliate_code, ver
 * migration add_affiliate_id_to_sales_and_inventory_holds_table), persistido
 * em localStorage com janela de atribuição e enviado no checkout
 * (StorefrontCheckoutService::checkout). Só informativo para o staff (não
 * exposto ao comprador) — sem FK, é texto livre vindo de qualquer provedor
 * de anúncio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->after('affiliate_id');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
