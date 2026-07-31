<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comanda: agregado do fluxo presencial legado que vivia o ciclo da mesa.
 * Decisão central da época: NÃO estender Order com "mesa aberta" — a comanda
 * vive aberta e só materializa um Order (origin='counter') no fechamento
 * (order_id preenchido nesse momento). service_fee_percent é congelado da
 * config do tenant no momento da abertura. table_id nullable = atendimento
 * sem mesa; label nullable = nome da pessoa (divisão por pessoa).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comandas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();

            $table->string('label')->nullable();
            $table->string('status', 20)->default('open')->index();

            $table->unsignedBigInteger('opened_by')->nullable()->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('service_fee_percent', 5, 2)->nullable();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comandas');
    }
};
