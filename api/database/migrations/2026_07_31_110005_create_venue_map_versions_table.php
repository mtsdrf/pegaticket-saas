<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VenueMapVersion = versão congelada de um Venue (spec 5.7: "a versão do
 * mapa utilizada pelo evento deverá ser preservada"). Sem controller
 * próprio (ver VenueService/SeatService): toda alteração de Seat opera
 * sobre o draft (is_published=false) mais recente do Venue; publicar
 * (VenueController::publish) marca o draft atual como is_published=true e
 * cria automaticamente um novo draft com os assentos clonados, para que o
 * mapa continue editável sem afetar a versão já publicada/vendida.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('venue_map_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();

            $table->integer('version_number');
            $table->boolean('is_published')->default(false);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['venue_id', 'version_number'], 'uniq_venue_version_number');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_map_versions');
    }
};
