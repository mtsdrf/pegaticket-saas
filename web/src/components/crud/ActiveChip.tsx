import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { Chip } from '@mui/material'

export function ActiveChip({
  isActive,
  activeLabel = 'Ativo',
  inactiveLabel = 'Inativo',
}: {
  isActive: boolean
  activeLabel?: string
  inactiveLabel?: string
}) {
  return (
    <Chip
      size="small"
      icon={isActive ? <CheckCircleOutlineIcon /> : <HighlightOffIcon />}
      label={isActive ? activeLabel : inactiveLabel}
      sx={{
        fontWeight: 600,
        bgcolor: isActive
          ? 'color-mix(in srgb, var(--mk-success) 14%, transparent)'
          : 'color-mix(in srgb, var(--mk-danger) 12%, transparent)',
        color: isActive ? 'var(--mk-success)' : 'var(--mk-danger)',
        '& .MuiChip-icon': { color: 'inherit' },
      }}
    />
  )
}
