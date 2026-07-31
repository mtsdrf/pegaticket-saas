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
    audience: 'Para empresas que querem usar toda a plataforma sem bloqueios por modulo ou mudanca de faixa.',
    moduleKeys: ['storefront', 'orders', 'storefront-orders', 'clients', 'reports', 'stock', 'integrations', 'analytics', 'subscription', 'compliance'],
    featureHighlights: [
      'Pedidos internos e operacao da loja online',
      'Clientes, produtos, categorias e tipos de item',
      'Controle de estoque por depósito/filial',
      'Integrações, webhooks e canais externos',
      'Analytics, rotas, financeiro e dashboard',
      'Perfis fiscais e regras tributárias',
      'Acesso dedicado do contador',
      'Assinatura, suporte e configuracoes da empresa',
    ],
  },
]
