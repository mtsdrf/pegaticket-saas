<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endereço próprio da empresa (loja pública) — reaproveita o model genérico
 * Endereco (mesma tabela `enderecos` usada por Client). nullOnDelete: se o
 * endereço for removido, a loja só perde a referência, não é apagada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('endereco_id')
                ->nullable()
                ->after('logo_mime')
                ->constrained('enderecos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('endereco_id');
        });
    }
};
