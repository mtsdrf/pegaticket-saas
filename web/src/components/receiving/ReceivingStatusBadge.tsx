import { Chip } from '@mui/material'
import { groupConnectionStatus } from '../../utils/receivingStatusGroups'
import type { TenantPagBankConnectionStatus } from '../../types/pagBankConnect'

const GROUP_LABELS: Record<ReturnType<typeof groupConnectionStatus>, string> = {
  not_configured: 'Não configurado',
  in_progress: 'Em andamento',
  in_review: 'Em análise',
  enabled: 'Habilitado',
  restricted: 'Pendência',
  rejected: 'Recebimentos indisponíveis',
}

const GROUP_COLORS: Record<ReturnType<typeof groupConnectionStatus>, 'default' | 'info' | 'success' | 'warning' | 'error'> = {
  not_configured: 'default',
  in_progress: 'info',
  in_review: 'info',
  enabled: 'success',
  restricted: 'warning',
  rejected: 'error',
}

/**
 * Badge visual dos 13 status de `TenantPagBankConnection`, sempre via o
 * agrupamento de 6 grupos (`groupConnectionStatus`) — nunca expõe o valor
 * técnico do status ao usuário final.
 */
export function ReceivingStatusBadge({ status }: { status: TenantPagBankConnectionStatus | null }) {
  const group = groupConnectionStatus(status)

  return <Chip size="small" label={GROUP_LABELS[group]} color={GROUP_COLORS[group]} variant={group === 'not_configured' ? 'outlined' : 'filled'} />
}
