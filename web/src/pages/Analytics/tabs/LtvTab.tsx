import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import {
  Box,
  Button,
  MenuItem,
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
import { useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatCurrency } from '../../../utils/format'
import type { LtvGroupBy } from '../../../types/analytics'

const GROUP_BY_OPTIONS: { value: LtvGroupBy; label: string }[] = [
  { value: 'segment', label: 'Segmento RFM' },
  { value: 'cohort', label: 'Coorte de aquisição' },
]

/**
 * LTV histórico (roadmap Fase A3, parte 2) — valor já gasto (realizado, não
 * projeção) agregado por segmento RFM de 8 níveis ou coorte de aquisição.
 * Sem período: LTV histórico é vitalício por natureza — o `group_by` já é
 * um filtro explícito com default, mesmo espírito de churnClients().
 */
export function LtvTab() {
  const [groupBy, setGroupBy] = useState<LtvGroupBy>('segment')

  const { data, isLoading, error, reload } = useAnalyticsData(
    () => analyticsService.getLtvReport({ group_by: groupBy }),
    groupBy,
  )

  const groups = data?.groups ?? []

  function handleExportCsv() {
    if (groups.length === 0) return
    const headers = [groupBy === 'segment' ? 'Segmento' : 'Coorte', 'Clientes', 'LTV médio', 'LTV total']
    const rows = groups.map((group) => [
      group.label,
      String(group.customers_count),
      formatCurrency(group.average_ltv),
      formatCurrency(group.total_ltv),
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'ltv-historico.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            LTV histórico
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Valor já gasto por cliente até hoje (realizado, não projeção) — LTV previsto fica para uma fase futura.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={groups.length === 0}
          sx={{ minHeight: 40 }}
        >
          Exportar CSV
        </Button>
      </Box>

      <TextField
        select
        label="Agrupar por"
        size="small"
        value={groupBy}
        onChange={(event) => setGroupBy(event.target.value as LtvGroupBy)}
        sx={{ minWidth: { xs: '100%', sm: 220 }, mb: 2.5 }}
      >
        {GROUP_BY_OPTIONS.map((option) => (
          <MenuItem key={option.value} value={option.value}>
            {option.label}
          </MenuItem>
        ))}
      </TextField>

      {error && <AnalyticsErrorAlert message={error} onRetry={reload} />}

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)' },
          gap: 1.5,
          mb: 2.5,
        }}
      >
        <MetricCard
          icon={GroupsOutlinedIcon}
          label="Clientes com compra ativa"
          value={data ? String(data.overall.customers_count) : null}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="LTV médio geral"
          value={data ? formatCurrency(data.overall.average_ltv) : null}
          tone="accent"
          isLoading={isLoading}
          index={1}
        />
      </Box>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : groups.length === 0 ? (
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
            Nenhum cliente com compra ativa
          </Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 560, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                  {groupBy === 'segment' ? 'Segmento' : 'Coorte'}
                </TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Clientes</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>LTV médio</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>LTV total</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {groups.map((group) => (
                <TableRow key={group.key} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{group.label}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {group.customers_count}
                  </TableCell>
                  <TableCell align="right" sx={{ fontWeight: 600, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(group.average_ltv)}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(group.total_ltv)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}
    </Paper>
  )
}
