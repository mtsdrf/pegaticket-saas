<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxa de serviço PegaTicket (10% por ingresso, mínimo R$3) — ver
 * `.claude/memory/api-patterns.md` para o desenho completo da feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->decimal('service_fee_percentage', 5, 2)->default(10.00)->after('extra_reserve_release_offset_days');
            $table->decimal('service_fee_minimum_amount', 10, 2)->default(3.00)->after('service_fee_percentage');
            $table->unsignedInteger('service_fee_rule_version')->default(1)->after('service_fee_minimum_amount');
            $table->decimal('estimated_pix_processing_percentage', 5, 2)->nullable()->after('service_fee_rule_version');
            $table->json('estimated_card_processing_percentage_by_installment')->nullable()->after('estimated_pix_processing_percentage');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('service_fee_payer')->default('buyer')->after('status');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('platform_fee_unit_amount', 10, 2)->default(0)->after('line_total');
            $table->decimal('platform_fee_amount', 10, 2)->default(0)->after('platform_fee_unit_amount');
            $table->decimal('platform_fee_percentage_snapshot', 5, 2)->nullable()->after('platform_fee_amount');
            $table->decimal('platform_fee_minimum_snapshot', 10, 2)->nullable()->after('platform_fee_percentage_snapshot');
            $table->unsignedInteger('platform_fee_rule_version_snapshot')->nullable()->after('platform_fee_minimum_snapshot');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('platform_fee_total_amount', 10, 2)->default(0)->after('discount_amount');
            $table->string('platform_fee_payer_snapshot')->nullable()->after('platform_fee_total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_total_amount', 'platform_fee_payer_snapshot']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee_unit_amount',
                'platform_fee_amount',
                'platform_fee_percentage_snapshot',
                'platform_fee_minimum_snapshot',
                'platform_fee_rule_version_snapshot',
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('service_fee_payer');
        });

        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'service_fee_percentage',
                'service_fee_minimum_amount',
                'service_fee_rule_version',
                'estimated_pix_processing_percentage',
                'estimated_card_processing_percentage_by_installment',
            ]);
        });
    }
};
