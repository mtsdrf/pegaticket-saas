<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento fiscal (roadmap Fiscal D0). NENHUMA emissão real acontece nesta
 * fatia — a tabela é o "encaixe" (máquina de estados) pronto pra um
 * FiscalProvider de verdade (serviço pago ou lib sped-nfe) plugar depois SEM
 * mudar a modelagem. `provider` default 'none': nenhum provedor real ainda;
 * o ManualFiscalProvider grava 'manual' e status 'pending', nunca autoriza.
 *
 * Polimórfico (`documentable_type`/`documentable_id`), mesma filosofia de
 * payments/refunds (Onda 2A): pode ligar a uma Invoice de assinatura OU a um
 * Order futuramente. `access_key` é a chave de acesso de 44 dígitos que
 * NF-e/NFC-e têm; `xml_content`/`pdf_path` só são preenchidos por um
 * provider real de emissão.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');

            $table->string('document_type', 10); // nfse|nfe|nfce
            $table->string('status', 20)->index(); // pending|authorized|rejected|denied|canceled
            $table->string('provider', 30)->default('none');
            $table->string('provider_document_id')->nullable();
            $table->longText('xml_content')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('access_key', 44)->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id'], 'idx_fiscal_docs_documentable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
