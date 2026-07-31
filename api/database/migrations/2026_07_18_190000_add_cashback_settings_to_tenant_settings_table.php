<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Config de cashback por tenant (roadmap Delivery, Fase 5) — mesmo padrão
 * incremental de minimum_order_value/estimated_preparation_minutes.
 * cashback_enabled default false preserva 100% o comportamento atual pra
 * quem já tem tenant_settings (nenhuma loja ganha cashback sem configurar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('cashback_enabled')->default(false)->after('estimated_preparation_minutes');
            $table->decimal('cashback_percentage', 5, 2)->nullable()->after('cashback_enabled');
            $table->decimal('cashback_max_per_order', 10, 2)->nullable()->after('cashback_percentage');
            $table->unsignedSmallInteger('cashback_hold_days')->nullable()->after('cashback_max_per_order');
            $table->unsignedSmallInteger('cashback_expiration_days')->nullable()->after('cashback_hold_days');
            $table->decimal('cashback_redeem_max_percentage', 5, 2)->nullable()->after('cashback_expiration_days');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cashback_enabled',
                'cashback_percentage',
                'cashback_max_per_order',
                'cashback_hold_days',
                'cashback_expiration_days',
                'cashback_redeem_max_percentage',
            ]);
        });
    }
};
