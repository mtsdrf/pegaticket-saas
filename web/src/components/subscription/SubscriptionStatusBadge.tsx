import { Chip } from '@mui/material'
import type { SubscriptionStatus } from '../../types/subscription'

const CONFIG: Record<SubscriptionStatus, { label: string; color: string }> = {
  pending: { label: 'Pendente', color: 'var(--pt-muted)' },
  trialing: { label: 'Em teste', color: 'var(--pt-info)' },
  active: { label: 'Ativa', color: 'var(--pt-success)' },
  past_due: { label: 'Pagamento atrasado', color: 'var(--pt-warning)' },
  suspended: { label: 'Suspensa', color: 'var(--pt-danger)' },
  canceled: { label: 'Cancelada', color: 'var(--pt-danger)' },
  cancel_scheduled: { label: 'Cancelamento agendado', color: 'var(--pt-warning)' },
}

export function SubscriptionStatusBadge({ status }: { status: SubscriptionStatus }) {
  const config = CONFIG[status] ?? { label: status, color: 'var(--pt-muted)' }

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
