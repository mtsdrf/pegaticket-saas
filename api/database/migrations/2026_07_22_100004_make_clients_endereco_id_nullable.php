<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite clientes operacionais avulsos sem endereço obrigatório. O cliente
 * continua sendo uma linha real em `clients` (em vez de sales.client_id
 * nullable), preservando relatórios/joins que agrupam por cliente; só o
 * endereço deixa de ser obrigatório. FK continua nullOnDelete.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('endereco_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('endereco_id')->nullable(false)->change();
        });
    }
};
