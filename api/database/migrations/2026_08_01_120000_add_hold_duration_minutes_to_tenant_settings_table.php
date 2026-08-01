<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazo de reserva de estoque (spec 5.9 "O prazo padrão será de 15
 * minutos") configurável por tenant — nullable, StorefrontHoldService cai
 * pro default de 15 quando ausente (mesmo espírito de minimum_order_value).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('hold_duration_minutes')->nullable()->after('storefront_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('hold_duration_minutes');
        });
    }
};
