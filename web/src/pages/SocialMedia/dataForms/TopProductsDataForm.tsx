import { Alert, Box, Button, CircularProgress } from '@mui/material'
import { useEffect, useState } from 'react'
import { RankingItemsEditor } from '../../../components/socialMedia/RankingItemsEditor'
import { PlanUpgradeNotice } from '../../../components/plan/PlanUpgradeNotice'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import type { StoryRankingContent, StoryRankingItem } from '../../../types/socialMedia'
import { formatCurrency } from '../../../utils/format'
import { presetRange } from '../../../utils/period'

const PERIOD = presetRange('last_30')
const LIMIT = 5

interface TopProductsDataFormProps {
  /** Passar diretamente o setter de estado do pai (identidade estável) — ver mesma nota em `ProductDataForm`. */
  onContentChange: (content: StoryRankingContent | null) => void
}

/** Formulário de dado do conteúdo "Produtos mais vendidos" — top produtos dos últimos 30 dias por receita (`reports/analytics/top-products`, restrito por plano). */
export function TopProductsDataForm({ onContentChange }: TopProductsDataFormProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getTopProducts({ ...PERIOD, limit: LIMIT }),
    `${PERIOD.from}|${PERIOD.to}`,
  )

  const [items, setItems] = useState<StoryRankingItem[]>([])

  useEffect(() => {
    if (!data) return
    setItems(
      data.map((product) => ({
        label: product.name,
        primaryValue: formatCurrency(product.revenue),
        secondaryValue: `${product.quantity_sold} un. vendidas`,
      })),
    )
  }, [data])

  useEffect(() => {
    onContentChange(items.length > 0 ? { kind: 'ranking', eyebrow: 'Ranking do período', title: 'Produtos mais vendidos', items } : null)
  }, [items, onContentChange])

  if (planLocked) return <PlanUpgradeNotice featureLabel="o ranking de produtos mais vendidos (baseado em análises)" />

  if (error) {
    return (
      <Alert severity="error" variant="outlined" action={<Button onClick={reload}>Tentar de novo</Button>}>
        {error}
      </Alert>
    )
  }

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (items.length === 0) {
    return (
      <Alert severity="info" variant="outlined">
        Nenhuma venda de produto nos últimos 30 dias — tente outro tipo de conteúdo ou volte depois que houver vendas.
      </Alert>
    )
  }

  return <RankingItemsEditor items={items} onChange={setItems} />
}
