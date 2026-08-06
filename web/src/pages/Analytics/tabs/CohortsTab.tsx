import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import {
  Box,
  Button,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useMemo, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'

function retentionColor(percentage: number | null): string {
  if (percentage === null) return 'transparent'
  if (percentage >= 60) return 'color-mix(in srgb, var(--pt-success, var(--pt-accent)) 30%, transparent)'
  if (percentage >= 30) return 'color-mix(in srgb, var(--pt-accent) 20%, transparent)'
  if (percentage > 0) return 'color-mix(in srgb, var(--pt-warning) 18%, transparent)'
  return 'color-mix(in srgb, var(--pt-border) 40%, transparent)'
}

/**
 * Coortes de retenção (roadmap Fase A3, parte 2) — agrupa clientes pelo mês
 * da primeira compra paga e mede % de retorno em M0..M6. Tempo real sobre o
 * filtro, sem tabela de snapshot (decisão do roadmap seção 5.3). Filtro
 * obrigatório: sem mês de coorte inicial escolhido, nada é buscado (regra
 * transversal — igual a InventoryTab/CompareEventsTab).
 */
export function CohortsTab() {
  const defaultFrom = useMemo(() => {
    const date = new Date()
    date.setMonth(date.getMonth() - 6)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`
  }, [])

  const [fromInput, setFromInput] = useState('')
  const [appliedFrom, setAppliedFrom] = useState('')

  const { data, isLoading, error, reload } = useAnalyticsData(
    () => (appliedFrom ? analyticsService.getCohortsReport({ from: appliedFrom }) : Promise.resolve(null)),
    appliedFrom,
  )

  const cohorts = data?.cohorts ?? []
  const maxOffset = data?.max_month_offset ?? 6
  const offsets = useMemo(() => Array.from({ length: maxOffset + 1 }, (_, i) => i), [maxOffset])

  function handleApply() {
    setAppliedFrom(fromInput || defaultFrom)
  }

  function handleExportCsv() {
    if (cohorts.length === 0) return
    const headers = ['Coorte', 'Clientes', ...offsets.map((offset) => `M${offset}`)]
    const rows = cohorts.map((cohort) => [
      cohort.cohort_month,
      String(cohort.cohort_size),
      ...offsets.map((offset) => {
        const point = cohort.retention.find((p) => p.month_offset === offset)
        return point?.retention_percentage === null || point?.retention_percentage === undefined
          ? ''
          : `${point.retention_percentage}%`
      }),
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'coortes-retencao.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Coortes e retenção
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Clientes agrupados pelo mês da primeira compra paga — % que voltou a comprar em cada mês seguinte.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={cohorts.length === 0}
          sx={{ minHeight: 40 }}
        >
          Exportar CSV
        </Button>
      </Box>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ alignItems: { xs: 'stretch', sm: 'center' }, mb: 2.5 }}>
        <TextField
          label="Coorte a partir de"
          type="date"
          size="small"
          value={fromInput}
          onChange={(event) => setFromInput(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
          sx={{ minWidth: { xs: '100%', sm: 220 } }}
        />
        <Button variant="contained" size="small" onClick={handleApply} sx={{ minHeight: 40 }}>
          Aplicar filtro
        </Button>
      </Stack>

      {error && <AnalyticsErrorAlert message={error} onRetry={reload} />}

      {!appliedFrom ? (
        <Box
          sx={{
            minHeight: 200,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <GroupsOutlinedIcon sx={{ fontSize: 32, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Escolha o mês de coorte inicial
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>A tabela de retenção aparece aqui após aplicar o filtro.</Typography>
        </Box>
      ) : isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : cohorts.length === 0 ? (
        <Box
          sx={{
            minHeight: 160,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Nenhuma coorte encontrada
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Nenhum cliente teve a primeira compra paga a partir desse mês.</Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 680, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Coorte</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Clientes</TableCell>
                {offsets.map((offset) => (
                  <TableCell key={offset} align="center" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                    M{offset}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {cohorts.map((cohort) => (
                <TableRow key={cohort.cohort_month} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{cohort.cohort_month}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {cohort.cohort_size}
                  </TableCell>
                  {offsets.map((offset) => {
                    const point = cohort.retention.find((p) => p.month_offset === offset)
                    const percentage = point?.retention_percentage ?? null
                    return (
                      <TableCell
                        key={offset}
                        align="center"
                        sx={{
                          fontVariantNumeric: 'tabular-nums',
                          color: 'var(--pt-text)',
                          bgcolor: retentionColor(percentage),
                        }}
                      >
                        {percentage === null ? '—' : `${percentage}%`}
                      </TableCell>
                    )
                  })}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}
    </Paper>
  )
}
