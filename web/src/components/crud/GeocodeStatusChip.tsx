import EditLocationAltOutlinedIcon from '@mui/icons-material/EditLocationAltOutlined'
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmptyOutlined'
import LocationOnOutlinedIcon from '@mui/icons-material/LocationOnOutlined'
import LocationOffOutlinedIcon from '@mui/icons-material/LocationOffOutlined'
import { Chip } from '@mui/material'
import type { ReactElement } from 'react'
import type { GeocodeStatus } from '../../types/location'

const CONFIG: Record<GeocodeStatus, { label: string; icon: ReactElement; color: string; bg: string }> = {
  success: { label: 'Localizado', icon: <LocationOnOutlinedIcon />, color: 'var(--mk-success)', bg: 'color-mix(in srgb, var(--mk-success) 14%, transparent)' },
  pending: { label: 'Pendente', icon: <HourglassEmptyIcon />, color: 'var(--mk-muted)', bg: 'color-mix(in srgb, var(--mk-muted) 14%, transparent)' },
  failed: { label: 'Não localizado', icon: <LocationOffOutlinedIcon />, color: 'var(--mk-danger)', bg: 'color-mix(in srgb, var(--mk-danger) 12%, transparent)' },
  // lat/lng informados manualmente (bypassa o Nominatim) — tem coordenada
  // válida, mas não veio de geocodificação automática, por isso um ícone/
  // tom próprio em vez de reaproveitar "success".
  manual: { label: 'Manual', icon: <EditLocationAltOutlinedIcon />, color: 'var(--mk-info)', bg: 'color-mix(in srgb, var(--mk-info) 14%, transparent)' },
}

export function GeocodeStatusChip({ status }: { status: GeocodeStatus }) {
  const { label, icon, color, bg } = CONFIG[status]

  return (
    <Chip
      size="small"
      icon={icon}
      label={label}
      sx={{ fontWeight: 600, bgcolor: bg, color, '& .MuiChip-icon': { color: 'inherit' } }}
    />
  )
}
