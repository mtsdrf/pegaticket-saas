<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.1 do roadmap — geocodificação de endereços via Nominatim,
 * pré-requisito para rotas de entrega/cobrança (Fase 3.2). lat/lng
 * ficam nulos até o job de geocodificação rodar (disparado no
 * create/update do Endereco); geocode_status controla o ciclo de
 * vida (pending -> success|failed).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('enderecos', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('cep');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->string('geocode_status', 20)->default('pending')->after('lng')->index();
            $table->timestamp('geocoded_at')->nullable()->after('geocode_status');
        });
    }

    public function down(): void
    {
        Schema::table('enderecos', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'geocode_status', 'geocoded_at']);
        });
    }
};
