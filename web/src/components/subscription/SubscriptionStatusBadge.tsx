import { Chip } from '@mui/material'
import type { SubscriptionStatus } from '../../types/subscription'

const CONFIG: Record<SubscriptionStatus, { label: string; color: string }> = {
  pending: { label: 'Pendente', color: 'var(--mk-muted)' },
  trialing: { label: 'Em teste', color: 'var(--mk-info)' },
  active: { label: 'Ativa', color: 'var(--mk-success)' },
  past_due: { label: 'Pagamento atrasado', color: 'var(--mk-warning)' },
  suspended: { label: 'Suspensa', color: 'var(--mk-danger)' },
  canceled: { label: 'Cancelada', color: 'var(--mk-danger)' },
  cancel_scheduled: { label: 'Cancelamento agendado', color: 'var(--mk-warning)' },
}

export function SubscriptionStatusBadge({ status }: { status: SubscriptionStatus }) {
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
