<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Consumidor final sem cadastro" na venda de balcão (roadmap PDV, Fase
 * PDV-1): o cliente avulso do PDV é um Client real (name='Consumidor Final')
 * mas SEM endereço — endereco_id passa a ser nullable. Manter o cliente como
 * linha real (em vez de orders.client_id nullable) preserva todos os
 * relatórios/joins que agrupam por cliente; só o endereço deixa de ser
 * obrigatório. FK continua nullOnDelete. Ver architecture-decisions.md.
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
