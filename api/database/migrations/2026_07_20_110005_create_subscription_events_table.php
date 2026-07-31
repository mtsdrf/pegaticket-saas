<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha imutável de eventos de uma assinatura (roadmap 1B) — cada
 * transição de estado grava uma linha. SEM updated_at/soft delete: é log,
 * nunca é editado nem removido. `created_at` com useCurrent(); o model usa
 * $timestamps = false e seta created_at manualmente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
