import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import PercentOutlinedIcon from '@mui/icons-material/PercentOutlined'
import {
  Autocomplete,
  Box,
  Button,
  LinearProgress,
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
import { useEffect, useMemo, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import * as eventService from '../../../services/eventService'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import type { Event } from '../../../types/event'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatPercentage } from '../../../utils/format'

/**
 * Relatório de inventário/assentos avançado (roadmap Fase A2) — ocupação
 * agregada por setor. NÃO usa o PeriodFilter compartilhado da página
 * (ocupação é snapshot estrutural do mapa de assentos, não recorte de
 * data) — o filtro obrigatório aqui é a escolha do evento (regra
 * transversal: nenhuma tela carrega sem filtro ativo por padrão), então
 * nada é buscado até o usuário selecionar um evento.
 */
export function InventoryTab() {
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
    () => (selectedEvent ? analyticsService.getInventoryReport({ event_uuid: selectedEvent.uuid }) : Promise.resolve(null)),
    selectedEvent?.uuid ?? '',
  )

  const sectors = useMemo(() => data?.sectors ?? [], [data])

  function handleExportCsv() {
    if (!data) return
    const headers = ['Setor', 'Total', 'Vendidos', 'Reservados agora', 'Bloqueados', 'Disponíveis', 'Ocupação (%)']
    const rows = sectors.map((sector) => [
      sector.sector_name,
      String(sector.total_seats),
      String(sector.sold_seats),
      String(sector.held_seats_now),
      String(sector.blocked_seats),
      String(sector.available_seats),
      String(sector.occupancy_percentage),
    ])
    downloadTextFile(buildCsvContent(headers, rows), `inventario-assentos-${data.event_uuid}.csv`, 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Inventário e assentos
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Ocupação agregada por setor de um evento específico — escolha o evento para carregar.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={sectors.length === 0}
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
          <EventSeatOutlinedIcon sx={{ fontSize: 32, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Selecione um evento
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>A ocupação por setor aparece aqui após a escolha.</Typography>
        </Box>
      ) : (
        <>
          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', lg: 'repeat(4,1fr)' },
              gap: 1.5,
              mb: 2.5,
            }}
          >
            <MetricCard
              icon={EventSeatOutlinedIcon}
              label="Assentos totais"
              value={data ? String(data.totals.total_seats) : null}
              tone="primary"
              isLoading={isLoading}
              index={0}
            />
            <MetricCard
              icon={EventSeatOutlinedIcon}
              label="Vendidos"
              value={data ? String(data.totals.sold_seats) : null}
              tone="accent"
              isLoading={isLoading}
              index={1}
            />
            <MetricCard
              icon={LockOutlinedIcon}
              label="Reservados agora"
              value={data ? String(data.totals.held_seats_now) : null}
              caption={data ? `${data.totals.blocked_seats} bloqueados` : null}
              tone="primary"
              isLoading={isLoading}
              index={2}
            />
            <MetricCard
              icon={PercentOutlinedIcon}
              label="Ocupação geral"
              value={data ? formatPercentage(data.totals.occupancy_percentage) : null}
              tone={data && data.totals.occupancy_percentage >= 80 ? 'warning' : 'accent'}
              isLoading={isLoading}
              index={3}
            />
          </Box>

          {isLoading ? (
            <Stack spacing={1}>
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
              ))}
            </Stack>
          ) : !data?.has_seat_map ? (
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
                Evento sem mapa de assentos
              </Typography>
              <Typography sx={{ fontSize: 13.5 }}>
                Este evento vende por lote/tipo de ingresso, não por assento — veja a ocupação comercial no Início.
              </Typography>
            </Box>
          ) : sectors.length === 0 ? (
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
                Mapa sem setores cadastrados
              </Typography>
            </Box>
          ) : (
            <Box sx={{ overflowX: 'auto' }}>
              <Table size="small" sx={{ minWidth: 640, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Setor</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Total</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Vendidos</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Disponíveis</TableCell>
                    <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600, minWidth: 160 }}>Ocupação</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {sectors.map((sector) => (
                    <TableRow key={sector.sector_name} hover>
                      <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{sector.sector_name}</TableCell>
                      <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                        {sector.total_seats}
                      </TableCell>
                      <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                        {sector.sold_seats}
                      </TableCell>
                      <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                        {sector.available_seats}
                      </TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                          <LinearProgress
                            variant="determinate"
                            value={Math.min(100, sector.occupancy_percentage)}
                            sx={{
                              flex: 1,
                              height: 6,
                              borderRadius: 3,
                              bgcolor: 'color-mix(in srgb, var(--pt-border) 60%, transparent)',
                              '& .MuiLinearProgress-bar': {
                                borderRadius: 3,
                                bgcolor: sector.occupancy_percentage >= 80 ? 'var(--pt-warning)' : 'var(--pt-accent)',
                              },
                            }}
                          />
                          <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-text)', minWidth: 42, textAlign: 'right' }}>
                            {formatPercentage(sector.occupancy_percentage)}
                          </Typography>
                        </Stack>
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
