import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { Chip } from '@mui/material'

export function ClientActiveChip({ isActive }: { isActive: boolean }) {
  return (
    <Chip
      size="small"
      icon={isActive ? <CheckCircleOutlineIcon /> : <HighlightOffIcon />}
      label={isActive ? 'Ativo' : 'Inativo'}
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
