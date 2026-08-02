<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migração final do pivot Product -> Event/TicketType/EventProduct
 * (roadmap PegaTicket, seções 2.4/2.8/4/4A). Todas as tabelas
 * dependentes de `products`/`product_categories`/`product_types` já foram
 * repontadas pra `ticket_types`/`events`/`event_categories` nas migrations
 * anteriores desta mesma leva (sale_items, stock_balances,
 * stock_movements, product_promotions, product_favorites->event_favorites)
 * — o que sobra aqui é dropar, na ordem filho->pai, o que não tem mais
 * consumidor: grupos de opcionais de produto (feature descontinuada,
 * fora do MVP de ingresso), `product_category_prices` (preço por
 * categoria B2B, roadmap 2.4 REMOVER), e as próprias `products`/
 * `product_types`/`product_categories`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('sale_item_options');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('product_category_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_categories');
    }

    public function down(): void
    {
        // Irreversível de propósito: recriar o schema antigo exigiria
        // duplicar todas as migrations originais de products/product_types/
        // product_categories/product_option_groups/product_options/
        // product_category_prices. Banco de dev está zerado (sem dado a
        // preservar) — reverter esta migração não é um caminho suportado.
    }
};
