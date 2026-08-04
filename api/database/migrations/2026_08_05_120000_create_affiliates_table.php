<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 (roadmap 2026-08-02), primeira fatia — afiliado/promotor
 * tenant-scoped com link rastreável (tracking_code, tipo cupom curto,
 * único por tenant) e comissão opcional (commission_percentage nullable —
 * quando ausente, AffiliateCommissionService usa
 * tenant_settings.affiliate_default_commission_percentage; quando os dois
 * são nulos, nenhuma comissão é gerada — mesmo espírito neutro/opt-in de
 * PlatformFinanceSettings.extra_reserve_enabled).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('tracking_code', 40);
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->string('status', 20)->default('active');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'tracking_code'], 'uniq_tenant_affiliate_tracking_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
