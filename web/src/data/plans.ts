/**
 * Discurso de venda (texto de marketing, não número sensível a divergência)
 * por plano — mesmo conteúdo já mantido em `site/src/data/plans.ts` para a
 * página pública de preços. Preço real NÃO vive mais aqui: vem sempre de
 * `GET /subscription/plan-pricing` (`subscriptionService.getPlanPricing`),
 * que lê `plan_prices` no banco — evita a divergência que já existiu entre
 * este arquivo e o preço de fato cobrado.
 */
export interface PlanSalesInfo {
  slug: 'pegaticket'
  name: string
  featureHighlights: string[]
}

export const PLAN_SALES_INFO: PlanSalesInfo[] = [
  {
    slug: 'pegaticket',
    name: 'PegaTicket',
    featureHighlights: [
      'Pedidos internos e fila da bilheteria online',
      'Clientes, produtos, categorias e tipos de item',
      'Estoque, integracoes e planejamento de rotas',
      'Analytics, financeiro e dashboard operacional',
      'Contador, suporte e assinatura',
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
