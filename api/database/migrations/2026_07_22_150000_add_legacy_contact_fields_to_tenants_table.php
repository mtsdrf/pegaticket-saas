<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de contato/razão social sem equivalente em `tenants` hoje —
 * necessários para preservar dado real do legado na migração do
 * estabelecimento "Js Queijos e Doces" (ver
 * `.claude/memory/database-analysis/09-estab4-migration-data-audit.md`,
 * decisão pendente nº 9). Todos nullable — aditiva, não quebra tenant
 * existente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('razao_social')->nullable()->after('name');
            $table->string('email')->nullable()->after('razao_social');
            $table->string('phone')->nullable()->after('email');
            $table->string('mobile_phone')->nullable()->after('phone');
            $table->string('whatsapp')->nullable()->after('mobile_phone');
            $table->string('facebook')->nullable()->after('whatsapp');
            $table->string('instagram')->nullable()->after('facebook');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'razao_social',
                'email',
                'phone',
                'mobile_phone',
                'whatsapp',
                'facebook',
                'instagram',
            ]);
        });
    }
};
