<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nome customizável do programa de cashback por loja (ex.: "eCash") —
 * null = usa o padrão "Cashback" (fallback resolvido sempre no backend,
 * ver CashbackService::getBalance()).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('cashback_name', 60)->nullable()->after('cashback_redeem_max_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('cashback_name');
        });
    }
};
