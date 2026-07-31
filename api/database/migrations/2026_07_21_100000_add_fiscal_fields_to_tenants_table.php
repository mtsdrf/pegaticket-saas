<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro fiscal do emitente (roadmap Fiscal D0). Todos nullable — não
 * podem quebrar nenhum tenant existente, que continua operando sem dado
 * fiscal. Nenhuma emissão real acontece nesta fatia; estes campos só ficam
 * prontos pra um FiscalProvider de emissão de verdade consumir depois.
 * `fiscal_environment` NUNCA tem default 'producao' — homologação é o padrão
 * seguro (emitir em produção por engano gera nota fiscal válida real).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->after('slug');
            $table->string('ie')->nullable()->after('cnpj'); // "ISENTO" é valor válido
            $table->string('im')->nullable()->after('ie');
            $table->string('cnae')->nullable()->after('im');
            $table->string('tax_regime', 30)->nullable()->after('cnae'); // simples_nacional|lucro_presumido|lucro_real
            $table->string('fiscal_environment', 20)->default('homologacao')->after('tax_regime'); // homologacao|producao
            $table->string('ibge_city_code')->nullable()->after('fiscal_environment');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cnpj',
                'ie',
                'im',
                'cnae',
                'tax_regime',
                'fiscal_environment',
                'ibge_city_code',
            ]);
        });
    }
};
