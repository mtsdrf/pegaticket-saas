<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API pública + webhooks de saída (roadmap A6, item 20). Uma URL cadastrada
 * pelo tenant recebe POST assinado (HMAC-SHA256, header
 * `X-Maskats-Signature`) quando um dos `event_types` acontece. `secret` é
 * segredo reversível (precisa ser lido de volta a cada delivery para
 * assinar o payload) — cast `encrypted` no Model, nunca hash irreversível.
 * Ver App\Services\Webhook\WebhookDispatchService.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('url');
            $table->json('event_types');
            $table->text('secret');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active'], 'idx_webhook_subscriptions_tenant_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
