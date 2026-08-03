<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caixa (roadmap Fase 2 — bilheteria presencial e operação de acesso
 * robusta, "caixa e estações de venda"). Um caixa aberto por vez por
 * tenant; vendas manuais em dinheiro feitas com o caixa aberto entram no
 * cálculo do valor esperado no fechamento (ver CashSessionService, que
 * soma `sales.payment_method='dinheiro'` no intervalo aberto/fechado —
 * sem FK extra em `sales`, calculado por janela de tempo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->unsignedBigInteger('opened_by')->index();
            $table->unsignedBigInteger('closed_by')->nullable()->index();

            $table->decimal('opening_amount', 10, 2);
            $table->decimal('closing_amount', 10, 2)->nullable();
            $table->decimal('expected_cash_amount', 10, 2)->nullable();
            $table->decimal('difference_amount', 10, 2)->nullable();

            $table->string('status', 20)->default('aberto')->index(); // aberto|fechado

            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();

            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();

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
        Schema::dropIfExists('cash_sessions');
    }
};
