<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Functionality\Functionality;

class FunctionalitiesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Usuários', 'slug' => 'users', 'description' => 'Gestão de usuários', 'is_active' => true],
            ['name' => 'Grupos', 'slug' => 'groups', 'description' => 'Gestão de grupos', 'is_active' => true],
            ['name' => 'Funcionalidades', 'slug' => 'functionalities', 'description' => 'Gestão de funcionalidades', 'is_active' => true],
            ['name' => 'Planos', 'slug' => 'plans', 'description' => 'Gestão de planos', 'is_active' => true],
            ['name' => 'Empresas', 'slug' => 'tenants', 'description' => 'Gestão de empresas', 'is_active' => true],
            ['name' => 'Perfis da Empresa', 'slug' => 'tenant_roles', 'description' => 'Gestão de perfis da empresa', 'is_active' => true],
            ['name' => 'Usuários da Empresa', 'slug' => 'tenant_users', 'description' => 'Gestão de usuários da empresa', 'is_active' => true],
            ['name' => 'Categorias de Cliente', 'slug' => 'client_categories', 'description' => 'Gestão de categorias de cliente', 'is_active' => true],
            ['name' => 'Categorias de Produto', 'slug' => 'product_categories', 'description' => 'Gestão de categorias de produto', 'is_active' => true],
            ['name' => 'Tipos de Produto', 'slug' => 'product_types', 'description' => 'Gestão de tipos de produto', 'is_active' => true],
            ['name' => 'Estados', 'slug' => 'estados', 'description' => 'Gestão de estados globais', 'is_active' => true],
            ['name' => 'Cidades', 'slug' => 'cidades', 'description' => 'Gestão de cidades globais', 'is_active' => true],
            ['name' => 'Bairros', 'slug' => 'bairros', 'description' => 'Gestão de bairros globais', 'is_active' => true],
            ['name' => 'Endereços', 'slug' => 'enderecos', 'description' => 'Gestão de endereços da empresa', 'is_active' => true],
            ['name' => 'Dias Ideais', 'slug' => 'dias_ideais', 'description' => 'Gestão de dias ideais', 'is_active' => true],
            ['name' => 'Períodos Ideais', 'slug' => 'periodos_ideais', 'description' => 'Gestão de períodos ideais', 'is_active' => true],
            ['name' => 'Clientes', 'slug' => 'clients', 'description' => 'Gestão de clientes', 'is_active' => true],
            ['name' => 'Produtos', 'slug' => 'products', 'description' => 'Gestão de produtos', 'is_active' => true],
            ['name' => 'Locais de Estoque', 'slug' => 'stock_locations', 'description' => 'Gestão de locais de estoque', 'is_active' => true],
            ['name' => 'Estoque', 'slug' => 'stock', 'description' => 'Gestão de saldos e movimentações de estoque', 'is_active' => true],
            ['name' => 'Pedidos', 'slug' => 'orders', 'description' => 'Gestão de pedidos', 'is_active' => true],
            ['name' => 'Pedidos da Loja', 'slug' => 'storefront-orders', 'description' => 'Gestão dos pedidos gerados pela loja online (aprovar, cancelar, despachar, entregar)', 'is_active' => true],
            ['name' => 'Relatórios', 'slug' => 'reports', 'description' => 'Relatórios, indicadores e painel', 'is_active' => true],
            ['name' => 'Análises', 'slug' => 'analytics', 'description' => 'Análises avançadas de vendas', 'is_active' => true],
            ['name' => 'Visão Geral', 'slug' => 'dashboard', 'description' => 'Indicadores e números da tela inicial', 'is_active' => true],
            ['name' => 'Auditoria', 'slug' => 'audit_logs', 'description' => 'Consulta ao histórico de auditoria da plataforma', 'is_active' => true],
            ['name' => 'Rotas', 'slug' => 'routes', 'description' => 'Montagem de rotas de entrega e cobrança com mapa', 'is_active' => true],
            ['name' => 'Configurações', 'slug' => 'tenant_settings', 'description' => 'Gestão das configurações da empresa', 'is_active' => true],
            ['name' => 'Perfil da Empresa', 'slug' => 'tenant-profile', 'description' => 'Edição de nome e logo da própria empresa pelo dono', 'is_active' => true],
            ['name' => 'Redes Sociais', 'slug' => 'social_media', 'description' => 'Geração e compartilhamento de stories (produtos, indicadores, comunicados)', 'is_active' => true],
            ['name' => 'Loja Online', 'slug' => 'storefront', 'description' => 'Catálogo público e checkout da loja online do tenant', 'is_active' => true],
            ['name' => 'Cashback', 'slug' => 'cashback', 'description' => 'Crédito, resgate e extrato de cashback para clientes da loja online', 'is_active' => true],
            ['name' => 'Assinatura', 'slug' => 'subscription', 'description' => 'Assinatura do plano da empresa (cobrança, faturas, cancelamento, arrependimento)', 'is_active' => true],
            ['name' => 'Regras Tributárias', 'slug' => 'tax-rules', 'description' => 'Cadastro parametrizado e versionado de regras tributárias (fiscal D0)', 'is_active' => true],
            ['name' => 'Acesso do Contador', 'slug' => 'accounting-access', 'description' => 'Aprovação/revogação de acesso do escritório de contabilidade e central de pendências', 'is_active' => true],
            ['name' => 'PDV', 'slug' => 'pdv', 'description' => 'Ponto de venda: caixa (abertura/fechamento/sangria/suprimento) e venda rápida de balcão', 'is_active' => true],
            ['name' => 'Balcão', 'slug' => 'balcao', 'description' => 'Mesa/comanda/cozinha/bar: abertura de comanda, envio à estação (KDS), preparo e fechamento da conta', 'is_active' => true],
            ['name' => 'Novidades', 'slug' => 'release_notes', 'description' => 'Gestão de release notes (novidades da plataforma)', 'is_active' => true],
            ['name' => 'Financeiro', 'slug' => 'finance', 'description' => 'Conciliação financeira (pagamentos, estornos e eventos de webhook)', 'is_active' => true],
            ['name' => 'Central de Chamados', 'slug' => 'support', 'description' => 'Abertura e listagem de chamados de suporte, com diagnóstico automático opcional', 'is_active' => true],
            ['name' => 'Reativação de Cliente', 'slug' => 'reactivation', 'description' => 'Régua automática de reativação: cupom + push nativo para clientes sem pedido há N dias', 'is_active' => true],
            ['name' => 'API e Webhooks', 'slug' => 'api-access', 'description' => 'Gestão de API keys e webhook subscriptions para integração externa', 'is_active' => true],
            ['name' => 'Operação de Pagamentos', 'slug' => 'payment_admin', 'description' => 'Painel cross-tenant do staff da Maskats para pendências de pagamento/assinatura (divergências, idempotência ambígua, contestações, webhooks falhos) e reprocessamento manual', 'is_active' => true],
        ];

        foreach ($items as $data) {
            // inclui soft-deletados, pois você usa soft delete sempre
            $record = Functionality::withTrashed()->where('slug', $data['slug'])->first();

            if (!$record) {
                // create -> dispara creating -> HasUuid preenche uuid
                Functionality::create($data);
                continue;
            }

            // se existir e estiver deletado, restaura
            if ($record->trashed()) {
                $record->restore();
            }

            $record->fill($data);
            $record->save();
        }
    }
}
