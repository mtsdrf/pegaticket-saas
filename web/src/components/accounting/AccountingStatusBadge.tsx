import { Chip } from '@mui/material'
import type { AccountingAccessStatus } from '../../types/accounting'

const CONFIG: Record<AccountingAccessStatus, { label: string; color: string }> = {
  pending: { label: 'Pendente', color: 'var(--mk-warning)' },
  approved: { label: 'Aprovado', color: 'var(--mk-success)' },
  revoked: { label: 'Revogado', color: 'var(--mk-danger)' },
}

export function AccountingStatusBadge({ status }: { status: AccountingAccessStatus }) {
  const config = CONFIG[status] ?? { label: status, color: 'var(--mk-muted)' }

  return (
    <Chip
      size="small"
      label={config.label}
      sx={{
        fontWeight: 600,
        bgcolor: `color-mix(in srgb, ${config.color} 14%, transparent)`,
        color: config.color,
      }}
    />
  )
}
