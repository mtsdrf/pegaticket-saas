import { createContext, useContext } from 'react'
import type { CashSession } from '../../types/pdv'

export interface PdvSessionContextValue {
  /** Sessão de caixa aberta atual (o gate garante que nunca é `null` aqui dentro). */
  session: CashSession
  /** `true` quando o gate entrou usando o snapshot offline salvo localmente. */
  isOfflineSnapshot: boolean
  /** Re-consulta `GET /pdv/cash-sessions/current` no gate — usado após fechar o caixa. */
  reload: () => Promise<void>
}

export const PdvSessionContext = createContext<PdvSessionContextValue | null>(null)

export function usePdvSession(): PdvSessionContextValue {
  const context = useContext(PdvSessionContext)
  if (!context) {
    throw new Error('usePdvSession deve ser usado dentro do PdvCashSessionGatePage')
  }
  return context
}
