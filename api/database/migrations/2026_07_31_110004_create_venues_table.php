<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue = mapa reutilizável (spec 5.7), não é 1:1 com Event — vários
 * eventos do mesmo tenant podem reaproveitar o mesmo Venue. width/height
 * são as dimensões do canvas para o editor visual futuro (fora de escopo
 * aqui, só dados).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('name');

            $table->string('background_image_path')->nullable();
            $table->binary('background_image_data')->nullable();
            $table->string('background_image_mime')->nullable();

            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE venues MODIFY background_image_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
