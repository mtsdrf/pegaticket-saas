<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura de uma assinatura (roadmap 1B). `fiscal_document_id` fica pronta
 * mas SEM FK — a tabela `fiscal_documents` só será criada na Onda 2; a
 * coluna nullable existe desde já pro encaixe futuro do módulo fiscal.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('competence_period', 20); // ex: "2026-08"
            $table->date('due_date');
            $table->decimal('amount_gross', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('amount_net', 10, 2);
            $table->string('status', 20)->index(); // open|paid|overdue|canceled|refunded

            // FK futura pro módulo fiscal (Onda 2) — sem constraint ainda,
            // a tabela fiscal_documents não existe nesta onda.
            $table->unsignedBigInteger('fiscal_document_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
