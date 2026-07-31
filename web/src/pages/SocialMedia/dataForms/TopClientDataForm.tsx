import { Alert, Box, Button, CircularProgress, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { LocalAutocomplete } from '../../../components/crud/LocalAutocomplete'
import { PlanUpgradeNotice } from '../../../components/plan/PlanUpgradeNotice'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import type { TopClient } from '../../../types/analytics'
import type { StorySingleContent } from '../../../types/socialMedia'
import { formatCurrency } from '../../../utils/format'
import { presetRange } from '../../../utils/period'

const PERIOD = presetRange('this_month')
const LIMIT = 5

interface TopClientDataFormProps {
  /** Passar diretamente o setter de estado do pai (identidade estável) — ver mesma nota em `ProductDataForm`. */
  onContentChange: (content: StorySingleContent | null) => void
}

/** Formulário de dado do conteúdo "Cliente do mês" — top clientes do mês atual por valor comprado (`reports/analytics/top-clients`, restrito por plano). */
export function TopClientDataForm({ onContentChange }: TopClientDataFormProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getTopClients({ ...PERIOD, limit: LIMIT }),
    `${PERIOD.from}|${PERIOD.to}`,
  )

  const [selected, setSelected] = useState<TopClient | null>(null)
  const [title, setTitle] = useState('')
  const [valueText, setValueText] = useState('')

  useEffect(() => {
    if (!data || data.length === 0) return
    const first = data[0]
    setSelected(first)
    setTitle(first.name)
    setValueText(formatCurrency(first.total_amount))
  }, [data])

  useEffect(() => {
    if (!title.trim()) {
      onContentChange(null)
      return
    }

    onContentChange({
      kind: 'single',
      eyebrow: 'Cliente do mês',
      title: title.trim(),
      description: selected ? `${selected.order_count} pedido${selected.order_count === 1 ? '' : 's'} no período` : undefined,
      value: valueText.trim() || undefined,
    })
  }, [title, valueText, selected, onContentChange])

  if (planLocked) return <PlanUpgradeNotice featureLabel="o conteúdo de cliente do mês (baseado em análises)" />

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

  if (!data || data.length === 0) {
    return (
      <Alert severity="info" variant="outlined">
        Nenhum cliente com pedidos neste mês ainda — tente outro tipo de conteúdo ou volte depois que houver vendas.
      </Alert>
    )
  }

  return (
    <Stack spacing={2}>
      <LocalAutocomplete
        label="Cliente"
        options={data}
        value={selected}
        onChange={(client) => {
          setSelected(client)
          setTitle(client?.name ?? '')
          setValueText(client ? formatCurrency(client.total_amount) : '')
        }}
        getOptionLabel={(client) => client.name}
        getOptionKey={(client) => client.name}
      />

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
        <TextField label="Nome exibido no story" value={title} onChange={(event) => setTitle(event.target.value)} required />
        <TextField label="Valor exibido" value={valueText} onChange={(event) => setValueText(event.target.value)} />
      </Box>

      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
        Ranking do mês atual — os {Math.min(data.length, LIMIT)} clientes que mais compraram aparecem na busca acima.
      </Typography>
    </Stack>
  )
}
