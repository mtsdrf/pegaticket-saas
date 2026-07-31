import LocalPhoneOutlinedIcon from '@mui/icons-material/LocalPhoneOutlined'
import TodayOutlinedIcon from '@mui/icons-material/TodayOutlined'
import { Box, Chip, Paper, Stack, Typography } from '@mui/material'
import { GeocodeStatusChip } from '../crud/GeocodeStatusChip'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency } from '../../utils/format'
import { stopValue } from '../../utils/routeValue'
import type { RouteStop, RouteType } from '../../types/route'

function addressSummary(stop: RouteStop): string {
  const { logradouro, numero, bairro_name, cidade_name } = stop.endereco
  const line = [logradouro, numero].filter(Boolean).join(', ')
  const locality = [bairro_name, cidade_name].filter(Boolean).join(' — ')
  return [line, locality].filter(Boolean).join(' · ')
}

interface RouteCandidateCardProps {
  stop: RouteStop
  type: RouteType
}

/** Card de parada na lista pré-rota (antes de montar o itinerário) — usado tanto para roteirizáveis quanto para "sem localização". */
export function RouteCandidateCard({ stop, type }: RouteCandidateCardProps) {
  const value = stopValue(stop, type)

  return (
    <Paper
      variant="outlined"
      sx={{ p: 2.1, ...ELEVATED_SURFACE_SX }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1 }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 15, color: 'var(--mk-text)' }}>{stop.client_name}</Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mt: 0.25 }}>{addressSummary(stop)}</Typography>
        </Box>

        {stop.endereco.geocode_status !== 'success' && <GeocodeStatusChip status={stop.endereco.geocode_status} />}
      </Stack>

      <Stack direction="row" spacing={1} sx={{ mt: 1.25, flexWrap: 'wrap', rowGap: 1, alignItems: 'center' }}>
        <Chip
          size="small"
          label={formatCurrency(value)}
          sx={{ fontWeight: 600, color: 'var(--mk-text)', ...SOFT_PANEL_SX }}
        />

        {(stop.dia_ideal_name || stop.periodo_ideal_name) && (
          <Chip
            size="small"
            icon={<TodayOutlinedIcon />}
            label={[stop.dia_ideal_name, stop.periodo_ideal_name].filter(Boolean).join(' · ')}
            sx={{ color: 'var(--mk-muted)', '& .MuiChip-icon': { color: 'inherit' }, ...SOFT_PANEL_SX }}
          />
        )}

        {stop.phone_primary && (
          <Chip
            size="small"
            icon={<LocalPhoneOutlinedIcon />}
            label={stop.phone_primary}
            component="a"
            href={`tel:${stop.phone_primary}`}
            clickable
            sx={{ color: 'var(--mk-muted)', '& .MuiChip-icon': { color: 'inherit' }, ...SOFT_PANEL_SX }}
          />
        )}
      </Stack>
    </Paper>
  )
}
