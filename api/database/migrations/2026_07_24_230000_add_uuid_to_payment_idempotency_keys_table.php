<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Painel administrativo de pendências de pagamento (roadmap 2026-07-24):
 * `payment_idempotency_keys` precisa de um identificador público estável
 * para a listagem/reprocessamento manual do staff da PegaTicket, mesmo
 * critério já usado em `webhook_deliveries` (tabela técnica, sem
 * created_by/soft delete, mas com `uuid` público). Backfill primeiro,
 * unique depois — evita erro de constraint em ambiente com linhas
 * existentes (produção real desde 2026-07-24, roadmap Fase B).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_idempotency_keys', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('payment_idempotency_keys')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('payment_idempotency_keys')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('payment_idempotency_keys', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
