import CompareArrowsOutlinedIcon from '@mui/icons-material/CompareArrowsOutlined'
import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import {
  Autocomplete,
  Box,
  Button,
  Chip,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { ChartCard } from '../../../components/analytics/ChartCard'
import { CompareEventsChart } from '../../../components/analytics/CompareEventsChart'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import * as eventService from '../../../services/eventService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import type { Event as EventEntity } from '../../../types/event'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatCurrency, formatPercentage } from '../../../utils/format'

const MAX_EVENTS = 5
const MIN_EVENTS = 2

/**
 * Comparação entre eventos (roadmap Fase A2, último item) — sem período
 * (regra transversal: nenhuma aba carrega sem filtro ativo por padrão,
 * aqui o filtro é "2 ou mais eventos selecionados", não período). A
 * curva compara "dia N desde abertura de vendas" de cada evento, NÃO
 * data de calendário — dois eventos que abriram vendas em datas
 * completamente diferentes ficam alinhados pelo mesmo ponto de partida.
 */
export function CompareEventsTab() {
  const [events, setEvents] = useState<EventEntity[]>([])
  const [eventsLoading, setEventsLoading] = useState(true)
  const [selectedEvents, setSelectedEvents] = useState<EventEntity[]>([])

  useEffect(() => {
    let cancelled = false
    eventService
      .listEvents({ page: 1, per_page: 50, sort_by: 'starts_at', sort_dir: 'desc' })
      .then((result) => {
        if (!cancelled) setEvents(result.items)
      })
      .finally(() => {
        if (!cancelled) setEventsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  const canQuery = selectedEvents.length >= MIN_EVENTS
  const eventUuids = useMemo(() => selectedEvents.map((event) => event.uuid).sort(), [selectedEvents])

  const { data, isLoading, error, reload } = useAnalyticsData(
    () => (canQuery ? analyticsService.getCompareEventsReport({ event_uuids: eventUuids }) : Promise.resolve(null)),
    eventUuids.join(','),
  )

  const compareEvents = data?.events ?? []

  function handleExportCsv() {
    if (!data || compareEvents.length === 0) return
    const headers = ['Evento', 'Dia desde abertura', 'Pedidos', 'Ingressos vendidos', 'Receita']
    const rows = compareEvents.flatMap((event) =>
      event.series.map((point) => [
        event.event_name,
        String(point.day),
        String(point.order_count),
        String(point.quantity_sold),
        String(point.revenue),
      ]),
    )
    downloadTextFile(buildCsvContent(headers, rows), `comparacao-eventos-${Date.now()}.csv`, 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Comparação entre eventos
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Escolha de {MIN_EVENTS} a {MAX_EVENTS} eventos — a curva compara o mesmo "dia desde abertura de vendas" de cada um, não a mesma data de calendário.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={compareEvents.length === 0}
          sx={{ minHeight: 40 }}
        >
          Exportar CSV
        </Button>
      </Box>

      <Autocomplete
        multiple
        options={events}
        value={selectedEvents}
        loading={eventsLoading}
        onChange={(_, value) => setSelectedEvents(value.slice(0, MAX_EVENTS))}
        getOptionLabel={(option) => option.name}
        isOptionEqualToValue={(option, value) => option.uuid === value.uuid}
        getOptionDisabled={(option) => selectedEvents.length >= MAX_EVENTS && !selectedEvents.some((event) => event.uuid === option.uuid)}
        renderValue={(value, getItemProps) =>
          value.map((option, index) => <Chip label={option.name} size="small" {...getItemProps({ index })} key={option.uuid} />)
        }
        sx={{ maxWidth: { sm: 640 }, mb: 2.5 }}
        renderInput={(params) => (
          <TextField
            {...params}
            label="Eventos"
            placeholder={selectedEvents.length === 0 ? `Selecione ${MIN_EVENTS} ou mais eventos` : ''}
            size="small"
          />
        )}
      />

      {error && <AnalyticsErrorAlert message={error} onRetry={reload} />}

      {!canQuery ? (
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
          <CompareArrowsOutlinedIcon sx={{ fontSize: 32, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Selecione ao menos {MIN_EVENTS} eventos
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>A comparação aparece aqui depois da escolha.</Typography>
        </Box>
      ) : (
        <>
          <ChartCard
            title="Curva de vendas acumuladas"
            subtitle="Receita acumulada por dia desde a abertura de vendas de cada evento."
            isLoading={isLoading}
            isEmpty={compareEvents.length === 0}
            emptyTitle="Sem vendas ainda"
            emptyDescription="Nenhum dos eventos selecionados teve venda registrada."
            minHeight={340}
          >
            <CompareEventsChart events={compareEvents} />
          </ChartCard>

          {compareEvents.length > 0 && (
            <Box sx={{ mt: 2.5, overflowX: 'auto' }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Evento</TableCell>
                    <TableCell>Abertura de vendas</TableCell>
                    <TableCell align="right">Ingressos vendidos</TableCell>
                    <TableCell align="right">Receita</TableCell>
                    <TableCell align="right">Ticket médio</TableCell>
                    <TableCell align="right">Ocupação</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {compareEvents.map((event) => (
                    <TableRow key={event.event_uuid}>
                      <TableCell sx={{ fontWeight: 600 }}>{event.event_name}</TableCell>
                      <TableCell>{event.sales_opened_at}</TableCell>
                      <TableCell align="right">{event.totals.total_quantity_sold}</TableCell>
                      <TableCell align="right">{formatCurrency(event.totals.total_revenue)}</TableCell>
                      <TableCell align="right">{formatCurrency(event.totals.average_ticket)}</TableCell>
                      <TableCell align="right">{formatPercentage(event.totals.occupancy_percentage)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </Box>
          )}
        </>
      )}
    </Paper>
  )
}
