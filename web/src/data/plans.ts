/**
 * Discurso de venda (texto de marketing, não número sensível a divergência)
 * por plano — mesmo conteúdo já mantido em `site/src/data/plans.ts` para a
 * página pública de preços. Preço real NÃO vive mais aqui: vem sempre de
 * `GET /subscription/plan-pricing` (`subscriptionService.getPlanPricing`),
 * que lê `plan_prices` no banco — evita a divergência que já existiu entre
 * este arquivo e o preço de fato cobrado.
 */
export interface PlanSalesInfo {
  slug: 'prata' | 'ouro' | 'diamante'
  name: string
  featureHighlights: string[]
}

export const PLAN_SALES_INFO: PlanSalesInfo[] = [
  {
    slug: 'prata',
    name: 'Prata',
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
    featureHighlights: [
      'Tudo do plano Ouro',
      'Balcão: mesas, comandas, cozinha e bar',
      'Assinatura self-service',
      'Acesso dedicado do contador',
      'Regras fiscais aplicadas aos pedidos',
    ],
  },
]

export function findPlanSalesInfo(planSlug: string | null | undefined, planName: string | null | undefined): PlanSalesInfo | null {
  const bySlug = planSlug ? PLAN_SALES_INFO.find((plan) => plan.slug === planSlug.toLowerCase()) : undefined
  if (bySlug) return bySlug

  const normalizedName = planName?.trim().toLowerCase()
  if (!normalizedName) return null

  return PLAN_SALES_INFO.find((plan) => plan.name.toLowerCase() === normalizedName) ?? null
}
