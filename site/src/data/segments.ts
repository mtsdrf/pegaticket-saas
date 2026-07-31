export interface SegmentInfo {
  key: string
  title: string
  description: string
}

/** Público real do produto (ver CLAUDE.md) — nenhum segmento genérico "para qualquer empresa". */
export const SEGMENTS: SegmentInfo[] = [
  {
    key: 'atacado',
    title: 'Atacado',
    description: 'Pedidos por categoria e cliente, com controle de estoque por depósito e rotas de entrega.',
  },
  {
    key: 'varejo',
    title: 'Varejo',
    description: 'Loja online integrada ao mesmo catálogo, estoque e operação de pedidos da empresa.',
  },
  {
    key: 'laticinios',
    title: 'Laticínios',
    description: 'Catálogo de produtos por categoria, clientes recorrentes e relatórios de vendas por período.',
  },
  {
    key: 'distribuidoras-bebidas',
    title: 'Distribuidoras de bebidas',
    description: 'Pedidos de grandes volumes, controle de estoque e planejamento de rotas de entrega.',
  },
  {
    key: 'bares',
    title: 'Bares',
    description: 'Operação centralizada de pedidos, estoque e campanhas de fidelização para atendimento recorrente.',
  },
  {
    key: 'casas-noturnas',
    title: 'Casas noturnas',
    description: 'Controle de estoque, relatórios e gestão operacional para picos intensos de venda em poucas horas.',
  },
]
