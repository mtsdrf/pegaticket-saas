<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSP real (roadmap Fase B, item 1 — Mercado Pago). Cobrança Pix devolve
 * QR code (texto copia-e-cola + base64) e cartão devolve ticket_url/status
 * detalhado — nada disso cabia nas colunas existentes de `payments`
 * (pensadas só pro fluxo manual). `metadata` json nullable guarda esse
 * retorno bruto do provedor (sem dado de cartão, nunca PAN/CVV — só o que o
 * PSP devolve pós-tokenização) para o frontend exibir o QR/redirect.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
