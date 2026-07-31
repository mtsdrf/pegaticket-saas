import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { STORAGE_KEYS } from '../constants/storage'
import { clearAccountingSession, storeAccountingToken } from '../services/accountingApiClient'
import * as accountingAuthService from '../services/accountingAuthService'
import type { AccountingOffice } from '../types/accounting'
import { AccountingAuthContext, type AccountingAuthContextValue } from './accounting-auth-context'

/**
 * Sessão do módulo do contador (`AccountingOffice`) — independente do
 * `AuthContext` de staff e do `PortalAuthContext` de cliente final, sem
 * estado compartilhado. Ver `services/accountingApiClient.ts` pro porquê da
 * instância axios/chave de storage separadas.
 */
export function AccountingAuthProvider({ children }: { children: ReactNode }) {
  const [hasToken, setHasToken] = useState<boolean>(() =>
    Boolean(localStorage.getItem(STORAGE_KEYS.accountingAccessToken)),
  )
  const [office, setOffice] = useState<AccountingOffice | null>(null)
  const [isLoading, setIsLoading] = useState(hasToken)

  const refreshMe = useCallback(async () => {
    if (!localStorage.getItem(STORAGE_KEYS.accountingAccessToken)) {
      setOffice(null)
      setHasToken(false)
      setIsLoading(false)
      return
    }

    setIsLoading(true)
    try {
      const me = await accountingAuthService.me()
      setOffice(me)
      setHasToken(true)
    } catch {
      // Token ausente/expirado/inválido — `accountingApiClient` já limpou o
      // storage em 401; aqui só sincroniza o estado React com isso.
      clearAccountingSession()
      setOffice(null)
      setHasToken(false)
    } finally {
      setIsLoading(false)
    }
  }, [])

  const setSession = useCallback(
    async (accessToken: string) => {
      storeAccountingToken(accessToken)
      setHasToken(true)
      await refreshMe()
    },
    [refreshMe],
  )

  const logout = useCallback(() => {
    clearAccountingSession()
    setOffice(null)
    setHasToken(false)
  }, [])

  useEffect(() => {
    void refreshMe()
  }, [refreshMe])

  const value = useMemo<AccountingAuthContextValue>(
    () => ({
      isAuthenticated: hasToken,
      office,
      isLoading,
      setSession,
      logout,
      refreshMe,
    }),
    [hasToken, office, isLoading, setSession, logout, refreshMe],
  )

  return <AccountingAuthContext.Provider value={value}>{children}</AccountingAuthContext.Provider>
}
