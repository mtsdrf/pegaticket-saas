<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase R2.2 (roadmap docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md,
 * seção 9.4/9.10) — caminho Account/Cadastro (tenant sem conta PagBank,
 * PegaTicket cria uma SELLER nova). Estende `tenant_pagbank_connections`
 * em vez de criar tabela paralela (decisão de modelagem da seção 9.4):
 * mesma tabela/model/eligibility já testados de R2.1 (Connect), com
 * `connection_type` diferenciando qual subconjunto de estados um registro
 * deve transitar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_pagbank_connections', function (Blueprint $table) {
            $table->string('connection_type', 30)->default('connected_existing')->after('provider');
            $table->string('account_person_type', 5)->nullable()->after('connection_type');
            $table->text('document_encrypted')->nullable()->after('refresh_token_encrypted');
            $table->string('provider_status', 40)->nullable()->after('status');
            $table->dateTime('submitted_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_pagbank_connections', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'account_person_type',
                'document_encrypted',
                'provider_status',
                'submitted_at',
            ]);
        });
    }
};
