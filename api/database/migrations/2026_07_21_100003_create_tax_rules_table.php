<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra tributária parametrizada e versionada por vigência (roadmap Fiscal
 * D0). Cada tenant define AS SUAS regras — nunca são globais/hardcoded. Esta
 * fatia é só o CADASTRO versionado: NÃO há motor de cálculo de imposto sobre
 * pedido ainda (isso dependeria de regras tributárias reais não
 * especificadas). O desenho abaixo já comporta um motor plugado depois SEM
 * mudar a tabela.
 *
 * `scope` (json nullable) — critério de aplicação da regra. Null = vale para
 * todo `tax_type` do tenant (regra "coringa"). Quando presente, é um objeto
 * com quaisquer destas chaves (todas opcionais, cada uma um array de
 * strings; ausência = não filtra por aquele critério):
 *   {
 *     "cfop":       ["5102", "6102"],   // CFOPs em que a regra vale
 *     "ncm":        ["22030000"],       // NCMs (ou prefixos) alvo
 *     "uf_origin":  ["SP"],             // UF de origem da operação
 *     "uf_dest":    ["SP", "MG"]        // UF de destino da operação
 *   }
 * O formato é deliberadamente aberto (json) para o motor futuro evoluir os
 * critérios sem migration nova.
 *
 * `rate_percent` decimal(7,4): impostos brasileiros às vezes têm mais de 2
 * casas (ex: PIS 0,6500%). Vigência por `valid_from`/`valid_to` (ambos
 * nullable: null_from = sempre valeu; null_to = ainda vigente).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('tax_type', 20)->index(); // icms|icms_st|ipi|pis|cofins|iss
            $table->json('scope')->nullable();
            $table->decimal('rate_percent', 7, 4);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'tax_type', 'is_active'], 'idx_tax_rules_tenant_type_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
