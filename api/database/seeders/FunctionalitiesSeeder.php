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
            ['name' => 'Categorias de Evento', 'slug' => 'event_categories', 'description' => 'Gestão de categorias de evento', 'is_active' => true],
            ['name' => 'Eventos', 'slug' => 'events', 'description' => 'Gestão de eventos', 'is_active' => true],
            ['name' => 'Sessões do Evento', 'slug' => 'event_sessions', 'description' => 'Gestão de sessões e datas operacionais do evento', 'is_active' => true],
            ['name' => 'Tipos de Ingresso', 'slug' => 'ticket_types', 'description' => 'Gestão de tipos de ingresso', 'is_active' => true],
            ['name' => 'Lotes de Ingresso', 'slug' => 'ticket_batches', 'description' => 'Gestão de lotes de venda por tipo de ingresso', 'is_active' => true],
            ['name' => 'Produtos do Evento', 'slug' => 'event_products', 'description' => 'Gestão de adicionais e estacionamento do evento', 'is_active' => true],
            ['name' => 'Locais', 'slug' => 'venues', 'description' => 'Gestão de locais e mapas de evento', 'is_active' => true],
            ['name' => 'Assentos e Mesas', 'slug' => 'seats', 'description' => 'Gestão de assentos, mesas, áreas e camarotes', 'is_active' => true],
            ['name' => 'Disponibilidade', 'slug' => 'stock', 'description' => 'Controle de saldos, reservas e movimentações de disponibilidade', 'is_active' => true],
            ['name' => 'Pedidos', 'slug' => 'orders', 'description' => 'Gestão de pedidos', 'is_active' => true],
            ['name' => 'Compradores', 'slug' => 'customers', 'description' => 'Busca de compradores (FinalCustomer) pelo staff, usada no pedido manual', 'is_active' => true],
            ['name' => 'Vendas Online', 'slug' => 'storefront-orders', 'description' => 'Gestão das vendas geradas pela bilheteria online (aprovar, cancelar, despachar, entregar)', 'is_active' => true],
            ['name' => 'Relatórios', 'slug' => 'reports', 'description' => 'Relatórios, indicadores e painel', 'is_active' => true],
            ['name' => 'Análises', 'slug' => 'analytics', 'description' => 'Análises avançadas de vendas', 'is_active' => true],
            ['name' => 'Visão Geral', 'slug' => 'dashboard', 'description' => 'Indicadores e números da tela inicial', 'is_active' => true],
            ['name' => 'Auditoria', 'slug' => 'audit_logs', 'description' => 'Consulta ao histórico de auditoria da plataforma', 'is_active' => true],
            ['name' => 'Configurações', 'slug' => 'tenant_settings', 'description' => 'Gestão das configurações da empresa', 'is_active' => true],
            ['name' => 'Perfil da Empresa', 'slug' => 'tenant-profile', 'description' => 'Edição de nome e logo da própria empresa pelo dono', 'is_active' => true],
            ['name' => 'Bilheteria Online', 'slug' => 'storefront', 'description' => 'Catálogo público de eventos e checkout da bilheteria online do tenant', 'is_active' => true],
            ['name' => 'Assinatura', 'slug' => 'subscription', 'description' => 'Assinatura do plano da empresa (cobrança, faturas, cancelamento, arrependimento)', 'is_active' => true],
            ['name' => 'Novidades', 'slug' => 'release_notes', 'description' => 'Gestão de release notes (novidades da plataforma)', 'is_active' => true],
            ['name' => 'Financeiro', 'slug' => 'finance', 'description' => 'Conciliação financeira (pagamentos, estornos e eventos de webhook)', 'is_active' => true],
            ['name' => 'Central de Chamados', 'slug' => 'support', 'description' => 'Abertura e listagem de chamados de suporte, com diagnóstico automático opcional', 'is_active' => true],
            ['name' => 'Operação de Pagamentos', 'slug' => 'payment_admin', 'description' => 'Painel cross-tenant do staff da PegaTicket para pendências de pagamento/assinatura (divergências, idempotência ambígua, contestações, webhooks falhos) e reprocessamento manual', 'is_active' => true],
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
