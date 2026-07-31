import { Chip } from '@mui/material'
import type { RfmSegment } from '../../types/analytics'

const RFM_CONFIG: Record<RfmSegment, { label: string; token: string }> = {
  vip: { label: 'VIP', token: 'var(--pt-accent)' },
  recorrente: { label: 'Recorrente', token: 'var(--pt-info)' },
  em_risco: { label: 'Em risco', token: 'var(--pt-warning)' },
  inativo: { label: 'Inativo', token: 'var(--pt-danger)' },
}

/** Rótulo RFM de cliente (vip/recorrente/em_risco/inativo) — texto sempre visível, cor é reforço. */
export function RfmChip({ segment }: { segment: RfmSegment }) {
  const config = RFM_CONFIG[segment]

  return (
    <Chip
      label={config.label}
      size="small"
      sx={{
        height: 22,
        fontSize: 11.5,
        fontWeight: 600,
        color: config.token,
        bgcolor: `color-mix(in srgb, ${config.token} 14%, transparent)`,
        border: `1px solid color-mix(in srgb, ${config.token} 35%, transparent)`,
      }}
    />
  )
}
