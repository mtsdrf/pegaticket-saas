<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Limpeza final de schema legado/orfão após a migração para o contexto atual
 * do PegaTicket.
 *
 * Mantemos aqui apenas tabelas que, no estado atual do código, se enquadram em
 * pelo menos um destes cenários:
 * - domínio explicitamente substituído por outro (clients -> final_customers,
 *   products -> events/ticket_types/event_products);
 * - tabela sem Model/Service/Controller/rota/teste funcional ativo;
 * - tentativa antiga de roadmap que nunca foi concluída no código.
 *
 * `dropIfExists` deixa a migration idempotente para bases já parcialmente
 * saneadas por migrations anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dependentes do CRM/precificação antiga antes das tabelas-pai.
        Schema::dropIfExists('product_category_prices');
        Schema::dropIfExists('client_client_categories');
        Schema::dropIfExists('reactivation_dispatches');

        // Legado de clientes substituído por final_customers + links.
        Schema::dropIfExists('client_categories');
        Schema::dropIfExists('clients');

        // Legado de catálogo/produto removido do domínio principal.
        Schema::dropIfExists('sale_item_options');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_categories');

        // Cadastros antigos sem uso real no código atual.
        Schema::dropIfExists('dia_ideais');
        Schema::dropIfExists('periodo_ideais');

        // Roadmaps abortados/não implementados.
        Schema::dropIfExists('receivable_interactions');
        Schema::dropIfExists('reactivation_rules');

        // Estruturas iniciadas no schema, mas sem fluxo ativo no sistema.
        Schema::dropIfExists('cashback_redemptions');
        Schema::dropIfExists('cashback_earnings');

        // Autenticação operacional planejada, mas não implementada no produto atual.
        Schema::dropIfExists('user_pins');
    }

    public function down(): void
    {
        // Limpeza destrutiva deliberada: não recriamos schema legado/orfão.
    }
};
