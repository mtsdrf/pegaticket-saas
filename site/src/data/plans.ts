/**
 * Planos e preços reais — espelha `api/database/seeders/InitialPlansSeeder.php`
 * (gating efetivo) e `PlanPricesSeeder.php` (preço-base mensal e desconto por
 * período). A home usa um recorte comercial dos módulos ativos, agrupando
 * algumas functionalities técnicas sob um mesmo rótulo.
 */
export interface PlanInfo {
  slug: 'pegaticket'
  name: string
  tagline: string
  monthlyBase: number
  highlighted: boolean
  audience: string
  /** module keys de data/modules.ts incluídos neste plano */
  moduleKeys: string[]
  featureHighlights: string[]
}

export const PLANS: PlanInfo[] = [
  {
    slug: 'pegaticket',
    name: 'PegaTicket',
    tagline: 'Plano unico com toda a operacao liberada',
    monthlyBase: 349.9,
    highlighted: true,
    audience: 'Para organizadores que querem usar toda a plataforma sem bloqueios por modulo ou mudanca de faixa.',
    moduleKeys: ['storefront', 'sales', 'operations', 'clients', 'reports', 'inventory', 'integrations', 'analytics', 'subscription', 'compliance'],
    featureHighlights: [
      'Vendas manuais e operacao da bilheteria online',
      'Compradores, eventos, lotes, setores e tipos de ingresso',
      'Controle de lotes, assentos, mesas e capacidade',
      'Integrações, webhooks e meios de pagamento',
      'Analytics, financeiro, check-in e dashboard',
      'Perfis fiscais e regras tributárias',
      'Acesso dedicado do contador',
      'Assinatura, suporte e configuracoes da empresa',
    ],
  },
]
