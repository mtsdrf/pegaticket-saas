<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EventSession = "Sessão" (spec 5.4). Um evento pode ter 0 (venda direta
 * pelo TicketType) ou várias sessões (ex.: sessão da tarde/noite do mesmo
 * evento). TicketType passa a poder referenciar uma sessão específica
 * (event_session_id, nullable) via migration seguinte.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('gate_opens_at')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status')->default('rascunho')->index();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();

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
        Schema::dropIfExists('event_sessions');
    }
};
