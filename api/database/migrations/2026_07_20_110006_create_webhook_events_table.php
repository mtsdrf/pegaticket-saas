<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotência de webhooks de pagamento (roadmap 1B). Unique composto
 * (provider, external_id) garante que o mesmo evento externo não seja
 * processado duas vezes. Tabela interna, nunca exposta por API (sem uuid).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('external_id');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id'], 'uniq_webhook_provider_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
