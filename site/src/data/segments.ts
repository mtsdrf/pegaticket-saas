export interface SegmentInfo {
  key: string
  title: string
  description: string
}

/** Público real do produto para a fase atual do PegaTicket. */
export const SEGMENTS: SegmentInfo[] = [
  {
    key: 'shows',
    title: 'Shows e festivais',
    description: 'Venda de ingressos, setores, lotes, assentos e operação de check-in para grandes públicos.',
  },
  {
    key: 'houses',
    title: 'Casas de evento',
    description: 'Bilheteria online integrada à operação local, com vendas manuais, listas e acesso na portaria.',
  },
  {
    key: 'sports',
    title: 'Esportes e arenas',
    description: 'Controle de capacidade, setores, assentos e validação de ingressos em múltiplos acessos.',
  },
  {
    key: 'theaters',
    title: 'Teatros e auditórios',
    description: 'Mapeamento de assentos, sessões e regras de ocupação com experiência de compra guiada.',
  },
  {
    key: 'producers',
    title: 'Produtores independentes',
    description: 'Gestão completa de eventos, ingressos, cupons, pagamentos e acompanhamento financeiro.',
  },
  {
    key: 'experiences',
    title: 'Experiências e eventos especiais',
    description: 'Venda por turma, mesa, vaga ou lote com jornadas flexíveis para diferentes formatos de evento.',
  },
]
