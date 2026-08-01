<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guard de "retirada na loja física" já foi removido do backend
 * (StorefrontCheckoutService::createFromCart) em 2026-08-01 — resquício de
 * loja física retail sem equivalente no domínio de bilheteria.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_settings') && Schema::hasColumn('tenant_settings', 'allow_store_pickup')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                $table->dropColumn('allow_store_pickup');
            });
        }
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
