<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telemetria de carrinho da loja online (roadmap A3.14) — evento anônimo
 * disparado pelo checkout client-side (ex.: `cart_abandoned`). Sem
 * `created_by`/`updated_by`/`deleted_by`/soft delete: quem gera o evento é
 * um visitante anônimo da loja pública, não um staff autenticado, e o dado
 * é analítico/append-only (mesmo espírito de `webhook_events`, que também
 * foge do padrão `BaseModel` por não ter um "dono" staff). `session_id` é
 * um identificador anônimo gerado no cliente (ex.: localStorage), não um
 * FinalCustomer — carrinho pode ser abandonado antes de qualquer
 * identificação/OTP do Portal.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('session_id');
            $table->string('event_type', 30)->index();
            $table->json('payload');

            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'created_at'], 'idx_cart_events_tenant_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_events');
    }
};
