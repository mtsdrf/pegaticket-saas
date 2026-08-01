<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Feature "preço promocional de/por" por ticket type — removida por
 * completo (model, controller, service, repository, DTOs, requests,
 * resources, rotas). Resíduo do produto antigo "Maskats": sem consumidor
 * no frontend PegaTicket (nem gestão nem exibição no catálogo).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_promotions');
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
