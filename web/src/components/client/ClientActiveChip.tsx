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
          ? 'color-mix(in srgb, var(--pt-success) 14%, transparent)'
          : 'color-mix(in srgb, var(--pt-danger) 12%, transparent)',
        color: isActive ? 'var(--pt-success)' : 'var(--pt-danger)',
        '& .MuiChip-icon': { color: 'inherit' },
      }}
    />
  )
}
