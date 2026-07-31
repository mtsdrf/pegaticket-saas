<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat = elemento do mapa (mesa, assento individual, área ou camarote —
 * spec 5.7). status aqui cobre só os estados estruturais/administrativos
 * (disponivel/bloqueado/indisponivel); os estados dinâmicos de venda
 * (RESERVADO_TEMPORARIAMENTE/VENDIDO/SELECIONADO_PELO_CLIENTE) pertencem
 * ao domínio de Hold/Ticket ainda não construído (próxima fase).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('venue_map_version_id')
                ->constrained('venue_map_versions')
                ->cascadeOnDelete();

            $table->string('sector_name')->nullable();
            $table->string('label');
            $table->string('kind')->default('assento')->index();
            $table->integer('capacity')->default(1);
            $table->decimal('pos_x', 10, 2)->default(0);
            $table->decimal('pos_y', 10, 2)->default(0);
            $table->boolean('is_accessible')->default(false);
            $table->string('status')->default('disponivel')->index();

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
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
