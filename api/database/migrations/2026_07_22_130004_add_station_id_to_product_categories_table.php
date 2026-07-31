<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roteamento produto→estação por categoria: mais simples que por produto
 * individual. Produto de categoria sem station_id = item avulso ("sem
 * estação") na operação de preparo — alerta visual, não bloqueia.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('station_id')
                ->nullable()
                ->after('is_active')
                ->constrained('stations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
