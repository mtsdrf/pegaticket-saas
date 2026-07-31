<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estação de preparo do módulo presencial legado da época — cozinha, bar,
 * chapa etc. Cada categoria de produto podia rotear para uma estação
 * (product_categories.station_id); o KDS de cada estação fazia polling da
 * sua fila de tickets. tenant-scoped, mesmo padrão de migration de
 * coupons/cash_registers.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type', 20)->default('kitchen');
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name'], 'uniq_tenant_station_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
