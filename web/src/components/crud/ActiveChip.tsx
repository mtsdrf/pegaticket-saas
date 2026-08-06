import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { StatusChip } from './StatusChip'

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
    <StatusChip
      status={isActive ? 'active' : 'inactive'}
      label={isActive ? activeLabel : inactiveLabel}
      tone={isActive ? 'success' : 'danger'}
      icon={isActive ? <CheckCircleOutlineIcon /> : <HighlightOffIcon />}
    />
  )
}
