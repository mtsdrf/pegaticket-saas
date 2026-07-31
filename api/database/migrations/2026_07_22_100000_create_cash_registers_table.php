<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrador físico/lógico de caixa do tenant. stock_location_id opcional
 * (nullOnDelete): define o local de estoque usado pela operação assistida;
 * quando ausente, a venda usa o local default do tenant (mesma resolução de
 * OrderService). Sem CRUD dedicado nesta fase — CashSessionService
 * resolve-ou-cria um "Caixa Principal" default por tenant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()
                ->constrained('stock_locations')->nullOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
