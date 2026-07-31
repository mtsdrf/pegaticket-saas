<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TicketBatch = "Lote" (spec 5.6). Pertence a um TicketType; preço/
 * quantidade próprios (podem divergir do TicketType base). quantity_sold
 * é incrementado por OrderService dentro da mesma transação da venda,
 * garantindo (junto com lockForUpdate) que a venda nunca ultrapasse
 * `quantity`. Virada automática por data/esgotamento (auto_advance) é
 * campo de configuração já modelado, mas o motor de virada (cron/job) é
 * pendência futura — só a validação de limite roda nesta rodada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->integer('quantity_sold')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('auto_advance')->default(true);
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
        Schema::dropIfExists('ticket_batches');
    }
};
