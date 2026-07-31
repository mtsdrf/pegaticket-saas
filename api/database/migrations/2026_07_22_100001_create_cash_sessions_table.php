<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessão de caixa (roadmap PDV, Fase PDV-1) — um ciclo abertura→fechamento
 * de um cash_register. opened_by/closed_by são ids de `users` (staff),
 * nullable como os demais *_by do projeto. closing_amount_expected/difference
 * só existem depois do fechamento. Regra dura: nunca duas sessões `open`
 * simultâneas pro mesmo cash_register (garantido no CashSessionService, mais
 * o índice parcial abaixo como rede de segurança onde o SGBD suportar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();

            $table->unsignedBigInteger('opened_by')->nullable()->index();
            $table->unsignedBigInteger('closed_by')->nullable()->index();

            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 10, 2)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_amount_declared', 10, 2)->nullable();
            $table->decimal('closing_amount_expected', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();

            $table->string('status', 20)->default('open');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['cash_register_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
