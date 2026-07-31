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

interface TopNeighborhoodsDataFormProps {
  /** Passar diretamente o setter de estado do pai (identidade estável) — ver mesma nota em `ProductDataForm`. */
  onContentChange: (content: StoryRankingContent | null) => void
}

/** Formulário de dado do conteúdo "Bairros com mais pedidos" — últimos 30 dias por faturamento (`reports/analytics/sales-by-location`, restrito por plano). */
export function TopNeighborhoodsDataForm({ onContentChange }: TopNeighborhoodsDataFormProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getSalesByLocation(PERIOD),
    `${PERIOD.from}|${PERIOD.to}`,
  )

  const [items, setItems] = useState<StoryRankingItem[]>([])

  useEffect(() => {
    if (!data) return
    setItems(
      data.neighborhoods.slice(0, LIMIT).map((neighborhood) => ({
        label: neighborhood.name || '(sem registro)',
        primaryValue: formatCurrency(neighborhood.total_amount),
        secondaryValue: `${neighborhood.count} pedido${neighborhood.count === 1 ? '' : 's'}`,
      })),
    )
  }, [data])

  useEffect(() => {
    onContentChange(
      items.length > 0 ? { kind: 'ranking', eyebrow: 'Ranking do período', title: 'Bairros com mais pedidos', items } : null,
    )
  }, [items, onContentChange])

  if (planLocked) return <PlanUpgradeNotice featureLabel="o ranking de bairros com mais pedidos (baseado em análises)" />

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
        Nenhuma venda com bairro identificado nos últimos 30 dias — tente outro tipo de conteúdo ou volte depois que houver vendas.
      </Alert>
    )
  }

  return <RankingItemsEditor items={items} onChange={setItems} />
}
