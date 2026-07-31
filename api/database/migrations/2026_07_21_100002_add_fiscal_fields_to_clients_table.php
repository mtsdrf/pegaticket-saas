<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro fiscal do destinatário (roadmap Fiscal D0). Todos nullable —
 * não quebram cliente existente. `clients` não tinha nenhum campo de
 * documento antes (explorado), então `cpf_cnpj` é criado aqui (não há
 * duplicação). `ie_indicator` é o indicador de IE do destinatário
 * (contribuinte|isento|nao_contribuinte), exigido por qualquer NF-e futura.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('cpf_cnpj', 14)->nullable()->after('name');
            $table->string('ie')->nullable()->after('cpf_cnpj');
            $table->string('ie_indicator', 20)->nullable()->after('ie'); // contribuinte|isento|nao_contribuinte
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['cpf_cnpj', 'ie', 'ie_indicator']);
        });
    }
};
