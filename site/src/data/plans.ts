/**
 * Planos e preços reais — espelha `api/database/seeders/InitialPlansSeeder.php`
 * (functionalities por plano) e `PlanPricesSeeder.php` (preço-base mensal e
 * desconto por período). Não alterar sem atualizar os dois lados.
 */
export interface PlanInfo {
  slug: 'prata' | 'ouro' | 'diamante'
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
    slug: 'prata',
    name: 'Prata',
    tagline: 'Operação completa da empresa',
    monthlyBase: 99.9,
    highlighted: false,
    audience: 'Para quem está começando a organizar pedidos, clientes e catálogo em um sistema só.',
    moduleKeys: ['storefront', 'orders', 'clients', 'reports'],
    featureHighlights: [
      'Loja online com cardápio/catálogo digital',
      'Gestão de pedidos da loja',
      'Cadastro de clientes e categorias',
      'Cadastro de produtos e categorias',
      'Relatórios e dashboard',
      'Usuários, papéis e permissões',
      'Configurações da empresa e redes sociais',
    ],
  },
  {
    slug: 'ouro',
    name: 'Ouro',
    tagline: 'Operação completa + estoque e analytics',
    monthlyBase: 199.9,
    highlighted: true,
    audience: 'Para negócios com volume constante que precisam de controle de estoque, PDV e indicadores avançados.',
    moduleKeys: ['storefront', 'orders', 'clients', 'reports', 'stock', 'pdv', 'cashback', 'analytics'],
    featureHighlights: [
      'Tudo do plano Prata',
      'Controle de estoque por depósito/filial',
      'PDV (frente de caixa)',
      'Cashback e fidelidade',
      'Analytics avançado',
      'Planejamento de rotas de entrega',
    ],
  },
  {
    slug: 'diamante',
    name: 'Diamante',
    tagline: 'Operação avançada com módulos premium',
    monthlyBase: 349.9,
    highlighted: false,
    audience: 'Para operações maiores, com várias unidades, atendimento de salão/bar e integração com o contador.',
    moduleKeys: ['storefront', 'orders', 'clients', 'reports', 'stock', 'pdv', 'cashback', 'analytics', 'balcao', 'subscription', 'accounting-access', 'tax-rules'],
    featureHighlights: [
      'Tudo do plano Ouro',
      'Balcão: mesas, comandas, cozinha e bar',
      'Assinatura self-service',
      'Acesso dedicado do contador',
      'Regras fiscais aplicadas aos pedidos',
    ],
  },
]
