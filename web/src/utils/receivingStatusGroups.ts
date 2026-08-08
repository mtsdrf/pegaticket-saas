import type { ReceivingStatusGroup, TenantPagBankConnectionStatus } from '../types/pagBankConnect'

/**
 * Agrupamento dos 13 status de `TenantPagBankConnection` em 6 grupos
 * visuais (roadmap seção 9.6 — "Estados visuais"). `disabled` cai em
 * `not_configured`: o tenant desconectou a própria conta, pode reiniciar o
 * onboarding do zero, não há "pendência" a resolver.
 */
export function groupConnectionStatus(status: TenantPagBankConnectionStatus | null): ReceivingStatusGroup {
  switch (status) {
    case 'started':
    case 'pending_connection':
    case 'pending_submission':
      return 'in_progress'
    case 'submitted':
    case 'pending_kyc':
    case 'under_review':
      return 'in_review'
    case 'verified':
    case 'enabled':
      return 'enabled'
    case 'restricted':
      return 'restricted'
    case 'rejected':
    case 'error':
      return 'rejected'
    case 'not_configured':
    case 'disabled':
    case null:
    default:
      return 'not_configured'
  }
}
