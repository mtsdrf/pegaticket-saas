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
    description: 'Loja online integrada ao PDV, com o mesmo catálogo e estoque na venda física e digital.',
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
    description: 'Módulo Balcão com comandas por mesa e comunicação direta com o bar.',
  },
  {
    key: 'casas-noturnas',
    title: 'Casas noturnas',
    description: 'Comandas, PDV e controle de estoque para operação de alto volume em poucas horas.',
  },
]
