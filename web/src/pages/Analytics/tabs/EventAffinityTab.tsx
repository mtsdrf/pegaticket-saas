import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import HubOutlinedIcon from '@mui/icons-material/HubOutlined'
import {
  Autocomplete,
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
import { useEffect, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import * as eventService from '../../../services/eventService'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import type { Event } from '../../../types/event'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatPercentage } from '../../../utils/format'

/**
 * Afinidade entre eventos (roadmap Fase A3, parte 2) — para o evento
 * selecionado, ranking dos eventos mais comprados pelos mesmos clientes
 * (cross-sell). Sem matriz completa nem rede/grafo visual (decisão adiada,
 * roadmap seção 5.4) — só tabela. Filtro obrigatório: sem evento
 * selecionado, nada é buscado (mesmo padrão de InventoryTab).
 */
export function EventAffinityTab() {
  const [events, setEvents] = useState<Event[]>([])
  const [eventsLoading, setEventsLoading] = useState(true)
  const [selectedEvent, setSelectedEvent] = useState<Event | null>(null)

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

  const { data, isLoading, error, reload } = useAnalyticsData(
    () => (selectedEvent ? analyticsService.getEventAffinityReport({ event_uuid: selectedEvent.uuid, limit: 10 }) : Promise.resolve(null)),
    selectedEvent?.uuid ?? '',
  )

  const affinities = data?.affinities ?? []

  function handleExportCsv() {
    if (!data) return
    const headers = ['Evento', 'Clientes em comum', 'Afinidade (%)']
    const rows = affinities.map((item) => [item.event_name, String(item.shared_customers_count), String(item.affinity_percentage)])
    downloadTextFile(buildCsvContent(headers, rows), `afinidade-eventos-${data.event_uuid}.csv`, 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Afinidade entre eventos
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Eventos mais comprados pelos mesmos clientes do evento selecionado — use para cross-sell.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={affinities.length === 0}
          sx={{ minHeight: 40 }}
        >
          Exportar CSV
        </Button>
      </Box>

      <Autocomplete
        options={events}
        value={selectedEvent}
        loading={eventsLoading}
        onChange={(_, value) => setSelectedEvent(value)}
        getOptionLabel={(option) => option.name}
        isOptionEqualToValue={(option, value) => option.uuid === value.uuid}
        sx={{ maxWidth: { sm: 420 }, mb: 2.5 }}
        renderInput={(params) => (
          <TextField {...params} label="Evento" placeholder="Selecione um evento" size="small" />
        )}
      />

      {error && <AnalyticsErrorAlert message={error} onRetry={reload} />}

      {!selectedEvent ? (
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
          <HubOutlinedIcon sx={{ fontSize: 32, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Selecione um evento
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Os eventos afins aparecem aqui após a escolha.</Typography>
        </Box>
      ) : isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : (
        <>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 1.5 }}>
            {data ? `${data.base_customers_count} cliente(s) compraram para este evento.` : ''}
          </Typography>

          {affinities.length === 0 ? (
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
                Nenhum evento afim encontrado
              </Typography>
              <Typography sx={{ fontSize: 13.5 }}>Os clientes deste evento não compraram para outros eventos ainda.</Typography>
            </Box>
          ) : (
            <Box sx={{ overflowX: 'auto' }}>
              <Table size="small" sx={{ minWidth: 560, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Evento</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Clientes em comum</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Afinidade</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {affinities.map((item) => (
                    <TableRow key={item.event_uuid} hover>
                      <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.event_name}</TableCell>
                      <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                        {item.shared_customers_count}
                      </TableCell>
                      <TableCell align="right" sx={{ fontWeight: 600, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                        {formatPercentage(item.affinity_percentage)}
                      </TableCell>
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
