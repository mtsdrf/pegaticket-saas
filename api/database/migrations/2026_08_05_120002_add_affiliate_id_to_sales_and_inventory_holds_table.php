<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuição de venda ao afiliado (Fase 6, fatia 1). O código de
 * rastreio (?ref=/?aff=, ver StorefrontCreateHoldRequest) é capturado na
 * criação do InventoryHold (mesmo ponto da jornada onde session_token já
 * é persistido) e propagado para sales.affiliate_id quando o checkout
 * consome o hold (StorefrontCheckoutService::consumeHold) ou quando o
 * checkout informa o código diretamente (fallback sem hold).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_holds', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('final_customer_id')
                ->constrained('affiliates')->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('final_customer_id')
                ->constrained('affiliates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });

        Schema::table('inventory_holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });
    }
};
