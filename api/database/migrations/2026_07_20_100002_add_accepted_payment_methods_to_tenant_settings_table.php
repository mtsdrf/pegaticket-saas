<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pagamento aceitas pela loja (lista fixa: cash, pix, credit_card,
 * debit_card). null = nenhuma configurada ainda; exposta no catálogo público
 * como array ([] quando null).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->json('accepted_payment_methods')->nullable()->after('cashback_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('accepted_payment_methods');
        });
    }
};
