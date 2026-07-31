<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 (migração de dados reais) encontrou numero/complemento gravados no
 * cliente no legado, não no endereço — modelagem ruim herdada. No sistema
 * novo, número/complemento são atributos do endereço físico, não do
 * cliente, então entram em `enderecos`. string (não numeric) porque
 * endereço pode ter "S/N", "123A" etc.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('enderecos', function (Blueprint $table) {
            $table->string('numero')->nullable()->after('logradouro');
            $table->string('complemento')->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('enderecos', function (Blueprint $table) {
            $table->dropColumn(['numero', 'complemento']);
        });
    }
};
