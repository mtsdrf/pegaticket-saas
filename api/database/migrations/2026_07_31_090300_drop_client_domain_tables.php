<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinalCustomer absorve Client por completo (decisão 2026-07-31):
 * - clients: descontinuado, substituído por final_customers +
 *   final_customer_tenant_links (sales.client_id e
 *   final_customer_tenant_links.client_id já removidos em migrations
 *   anteriores desta mesma leva).
 * - client_categories/client_client_categories: CRM B2B morto, sem
 *   Model/Controller/rota ativos — descartado, não migrado.
 * - product_category_prices: dead code total (sem Model/Service/Controller
 *   ativo), FK para client_categories — descartado junto.
 * - reactivation_dispatches: dead code total (sem Model/Service/Controller
 *   ativo — a migration original citava um ReactivationDispatchService que
 *   nunca chegou a existir no código), FK para clients — descartado junto.
 * Ordem de drop respeita FKs: tabelas dependentes antes das referenciadas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_category_prices');
        Schema::dropIfExists('client_client_categories');
        Schema::dropIfExists('client_categories');
        Schema::dropIfExists('reactivation_dispatches');
        Schema::dropIfExists('clients');
    }

    public function down(): void
    {
        // Tabelas descontinuadas de propósito — down() não recria o
        // schema legado (ver migrations originais no histórico).
    }
};
