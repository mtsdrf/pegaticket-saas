<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission\Action;

class ActionsSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            ['key' => 'read', 'name' => 'Visualizar'],
            ['key' => 'create', 'name' => 'Criar'],
            ['key' => 'update', 'name' => 'Atualizar'],
            ['key' => 'delete', 'name' => 'Excluir'],
            // Ações de movimentação de estoque (stock,{action}).
            ['key' => 'entry', 'name' => 'Entrada'],
            ['key' => 'exit', 'name' => 'Saída'],
            ['key' => 'adjustment', 'name' => 'Ajuste'],
            ['key' => 'transfer', 'name' => 'Transferência'],
            ['key' => 'block', 'name' => 'Bloquear'],
            ['key' => 'reserve', 'name' => 'Reservar'],
            ['key' => 'view_costs', 'name' => 'Ver custos'],
            // Reservada para inventário físico, sem rota consumindo ainda —
            // só semeando a permissão para não precisar de migration depois.
            ['key' => 'approve_inventory', 'name' => 'Aprovar inventário'],
            ['key' => 'reverse', 'name' => 'Estornar'],
            // Ações de Pedido (orders,{action}) — Fase 5.
            ['key' => 'deliver', 'name' => 'Entregar'],
            ['key' => 'pay', 'name' => 'Pagar'],
            ['key' => 'cancel', 'name' => 'Cancelar'],
            // Ações da tela dedicada de pedidos da loja (storefront-orders,{action}).
            // 'pay'/'deliver'/'cancel' já existem acima (Ações de Pedido) e
            // são reaproveitadas por storefront-orders,{pay,deliver,cancel}.
            ['key' => 'approve', 'name' => 'Aprovar'],
            ['key' => 'dispatch', 'name' => 'Despachar (saiu para entrega)'],
            ['key' => 'undispatch', 'name' => 'Desfazer saiu para entrega'],
            ['key' => 'undeliver', 'name' => 'Desfazer entrega'],
            // Ação de Relatórios (reports,export_pdf) — Fase 6. 'read' já existe.
            ['key' => 'export_pdf', 'name' => 'Exportar PDF'],
            // Ação do acesso do contador (accounting-access,revoke). 'read'/
            // 'create'/'approve' já existem acima e são reaproveitados.
            ['key' => 'revoke', 'name' => 'Revogar'],
            // Ações do PDV (pdv,{action}) — Fase PDV-1. 'read' já existe acima
            // (listar sessões / sessão atual).
            ['key' => 'open', 'name' => 'Abrir caixa'],
            ['key' => 'close', 'name' => 'Fechar caixa'],
            ['key' => 'movement', 'name' => 'Sangria/Suprimento'],
            ['key' => 'sell', 'name' => 'Vender no PDV'],
            // Ações do Balcão (balcao,{action}) — roadmap Balcão, Fases 1+2.
            // 'read'/'create'/'update'/'delete' (CRUD de mesas/estações) e
            // 'open'/'close' (abrir/fechar comanda) já existem acima e são
            // reaproveitados.
            ['key' => 'add_item', 'name' => 'Adicionar item à comanda'],
            ['key' => 'prep', 'name' => 'Atualizar preparo do item'],
            // Ação de exportação de dados (tenant-profile,export) — roadmap A1.2.
            ['key' => 'export', 'name' => 'Exportar dados'],
        ];

        foreach ($actions as $a) {
            Action::updateOrCreate(['key' => $a['key']], $a);
        }
    }
}
