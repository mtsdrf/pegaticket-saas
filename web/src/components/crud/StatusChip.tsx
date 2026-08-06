import { Chip } from '@mui/material'
import type { ReactElement } from 'react'

export type StatusChipTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

const TONE_STYLES: Record<StatusChipTone, { bg: string; color: string }> = {
  neutral: {
    bg: 'color-mix(in srgb, var(--pt-muted) 12%, transparent)',
    color: 'var(--pt-muted)',
  },
  info: {
    bg: 'color-mix(in srgb, var(--pt-info) 14%, transparent)',
    color: 'var(--pt-info)',
  },
  success: {
    bg: 'color-mix(in srgb, var(--pt-success) 14%, transparent)',
    color: 'var(--pt-success)',
  },
  warning: {
    bg: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
    color: 'var(--pt-warning)',
  },
  danger: {
    bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)',
    color: 'var(--pt-danger)',
  },
}

function normalizeLabel(status: string, label?: string): string {
  const base = (label ?? status.replaceAll('_', ' ')).trim()
  if (!base) return ''
  return `${base.charAt(0).toUpperCase()}${base.slice(1)}`
}

export function StatusChip({
  status,
  label,
  tone = 'neutral',
  icon,
}: {
  status: string
  label?: string
  tone?: StatusChipTone
  icon?: ReactElement
}) {
  const styles = TONE_STYLES[tone]

  return (
    <Chip
      size="small"
      icon={icon}
      label={normalizeLabel(status, label)}
      sx={{
        fontWeight: 600,
        bgcolor: styles.bg,
        color: styles.color,
        '& .MuiChip-icon': {
          color: 'inherit',
        },
      }}
    />
  )
}
