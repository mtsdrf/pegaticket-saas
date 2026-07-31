<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de segurança (2026-07-23): a unique original era só
 * (provider, external_id). O Mercado Pago usa `data.id` como identificador
 * dentro de cada TIPO de recurso (order, subscription_authorized_payment,
 * subscription_preapproval, chargeback, claim, ...) — não há garantia de
 * que os IDs sejam únicos ENTRE tipos diferentes (ex.: um authorized_payment
 * e um chargeback numéricos podem colidir por coincidência). Sem o tipo na
 * chave de idempotência, um evento de um tipo poderia ser silenciosamente
 * descartado (`processed_at` já setado) por causa de um evento de outro
 * tipo que só por coincidência compartilhou o mesmo `data.id` — perda real
 * de notificação financeira. `type` entra na unique para eliminar essa
 * classe de colisão; valor vazio ('') preserva o comportamento anterior
 * para o registro/idempotência de providers sem `type` (fluxo legado
 * `external_id`/`id`, ex. providers != mercadopago).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('type', 60)->default('')->after('external_id');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('uniq_webhook_provider_external');
            $table->unique(['provider', 'type', 'external_id'], 'uniq_webhook_provider_type_external');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('uniq_webhook_provider_type_external');
            $table->unique(['provider', 'external_id'], 'uniq_webhook_provider_external');
            $table->dropColumn('type');
        });
    }
};
