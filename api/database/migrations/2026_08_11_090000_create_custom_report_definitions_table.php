<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definição salva de relatório personalizado (construtor livre, roadmap
 * 5.6). Guarda só CHAVES (data_source/dimensions/metrics/filters), nunca
 * SQL — a tradução chave->coluna é feita em tempo de execução por
 * App\Support\Report\CustomReportFieldWhitelist, nunca a partir do que
 * está gravado aqui sem revalidação. `calculated_metrics` guarda pares
 * {name, formula}; a formula é validada contra a whitelist de métricas na
 * hora de salvar (CustomReportFormulaValidator) e revalidada na hora de
 * executar, pois a definição pode ter sido criada antes de uma métrica ser
 * removida da whitelist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_report_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('name');
            $table->string('data_source', 40)->index();

            $table->json('dimensions');
            $table->json('metrics');
            $table->json('calculated_metrics')->nullable();
            $table->json('filters')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_definitions');
    }
};
