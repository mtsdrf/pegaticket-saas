<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado do fluxo OAuth Connect do PagBank por tenant (roadmap
 * docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md, fase
 * R2). Tabela própria (não reaproveita tenant_settings): token/estado de
 * OAuth é dado sensível com ciclo de vida próprio (pending/enabled/
 * disabled), auditável e com histórico implícito via soft delete —
 * diferente do `pagbank_receiver_account_id` colado manualmente que já
 * existe em tenant_settings (fluxo manual antigo, mantido em paralelo).
 *
 * Status oficiais (ver .claude/skills/pagbank-integration.md §14):
 * not_configured|pending_connection|pending_kyc|under_review|enabled|
 * restricted|disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_pagbank_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('provider', 30)->default('pagbank');
            $table->string('status', 30)->default('not_configured');
            $table->string('account_id', 80)->nullable();

            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('scope')->nullable();

            $table->string('connect_state', 128)->nullable()->index();

            $table->dateTime('connected_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('disconnected_at')->nullable();

            $table->string('environment', 20)->default('sandbox');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id', 'uniq_tenant_pagbank_connection');
            $table->index(['tenant_id', 'status'], 'idx_tenant_pagbank_connection_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pagbank_connections');
    }
};
