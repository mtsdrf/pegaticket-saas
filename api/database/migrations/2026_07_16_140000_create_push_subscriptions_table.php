<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscrição de Web Push (VAPID) do cliente final (roadmap Delivery, Fase 4
 * — Web Push real). Mesmo desvio deliberado de product_favorites/
 * sale_ratings: sem BaseModel/soft delete/created_by (sempre criado pelo
 * próprio cliente final, nunca por staff), sem updated_at (subscribe é
 * upsert por endpoint, nunca update parcial de outros campos).
 * Escopado GLOBALMENTE por final_customer_id, não por tenant — o mesmo
 * cliente pode receber push de qualquer loja onde tiver pedido.
 *
 * `endpoint` como chave única: string(500) é suficiente para os endpoints
 * reais de push service (FCM/Mozilla/etc, tipicamente bem abaixo de 500
 * caracteres) e cabe no limite de índice único do MySQL/InnoDB com
 * utf8mb4 (500 * 4 bytes = 2000 bytes, dentro do limite de 3072 bytes do
 * innodb_large_prefix, default habilitado desde MySQL 5.7.9/8.0) — decisão
 * mais simples que indexar um hash do endpoint, sem esse risco de estourar
 * o limite de chave.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->string('endpoint', 500);
            $table->string('p256dh');
            $table->string('auth');

            $table->timestamp('created_at')->useCurrent();

            $table->unique('endpoint', 'uniq_push_subscription_endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
