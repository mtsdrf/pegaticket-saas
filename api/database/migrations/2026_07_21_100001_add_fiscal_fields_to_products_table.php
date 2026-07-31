<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro fiscal do produto (roadmap Fiscal D0). Todos nullable — não
 * quebram produto existente. `csosn_cst` é 1 coluna única de propósito:
 * serve como CSOSN quando o tenant é Simples Nacional e como CST caso
 * contrário (Regime Normal). Duas colunas separadas seriam redundância pro
 * D0 — a interpretação depende do `tenants.tax_regime` no momento da
 * emissão, não do cadastro. `origin` é o código de origem da mercadoria
 * (0-8) da tabela do CST/CSOSN.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ncm', 8)->nullable()->after('brand');
            $table->string('cest')->nullable()->after('ncm');
            $table->string('origin', 1)->nullable()->after('cest'); // 0-8
            $table->string('default_cfop')->nullable()->after('origin');
            $table->string('csosn_cst')->nullable()->after('default_cfop');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ncm', 'cest', 'origin', 'default_cfop', 'csosn_cst']);
        });
    }
};
