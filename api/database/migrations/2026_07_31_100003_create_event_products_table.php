<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adicional/estacionamento do evento (spec seção 5.8) — estoque próprio
 * (quantity_available), independente do estoque de ingressos (TicketType/
 * Stock). Sem relação com o módulo de Estoque físico de propósito.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);

            $table->integer('quantity_available')->nullable();
            $table->integer('max_per_order')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();

            $table->string('kind')->default('addon')->index();
            $table->boolean('requires_plate')->default(false);
            $table->boolean('requires_model')->default(false);
            $table->boolean('requires_color')->default(false);

            $table->string('status')->default('rascunho')->index();

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
        Schema::dropIfExists('event_products');
    }
};
