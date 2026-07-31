<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `product_favorites` -> `event_favorites` (roadmap seção 4A/2.4) —
 * favoritar passou a ser por Evento, não por item de catálogo (menos
 * rework: um evento só tem poucos ticket types/add-ons, favoritar o
 * evento inteiro é a unidade natural pro comprador).
 *
 * Recria a tabela do zero (DROP + CREATE) em vez de RENAME/ALTER — banco
 * de dev está zerado (sem dado real a preservar) e ALTER TABLE
 * DROP/RENAME de FK nomeada tem comportamento inconsistente entre MySQL e
 * SQLite (o driver de teste): MySQL não renomeia o nome interno da
 * constraint em RENAME TABLE, e o grammar de SQLite reconstrói a tabela a
 * partir da definição ORIGINAL, reintroduzindo a FK antiga apontando pra
 * `products` mesmo depois de dropForeign()/renameColumn() — achado real
 * ao rodar esta migração contra os dois drivers.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('event_favorites');

        Schema::create('event_favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['final_customer_id', 'event_id'], 'uniq_final_customer_event_favorite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_favorites');
    }
};
