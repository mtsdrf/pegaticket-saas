import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { STORAGE_KEYS } from '../constants/storage'
import { usePushSubscription } from '../hooks/usePushSubscription'
import { clearPortalSession, storePortalToken } from '../services/portalApiClient'
import * as portalAuthService from '../services/portalAuthService'
import type { PortalCustomer } from '../types/portal'
import { PortalAuthContext, type PortalAuthContextValue } from './portal-auth-context'

/**
 * Contexto de sessão do portal do cliente final — independente do
 * `AuthContext` de staff (`contexts/AuthContext.tsx`), sem estado
 * compartilhado. Ver `services/portalApiClient.ts` pro porquê da instância
 * axios/chave de storage separadas.
 */
export function PortalAuthProvider({ children }: { children: ReactNode }) {
  const [hasToken, setHasToken] = useState<boolean>(() =>
    Boolean(localStorage.getItem(STORAGE_KEYS.portalAccessToken)),
  )
  const [customer, setCustomer] = useState<PortalCustomer | null>(null)
  const [isLoading, setIsLoading] = useState(hasToken)
  const { subscribeToPush } = usePushSubscription()

  const refreshMe = useCallback(async () => {
    if (!localStorage.getItem(STORAGE_KEYS.portalAccessToken)) {
      setCustomer(null)
      setHasToken(false)
      setIsLoading(false)
      return
    }

    setIsLoading(true)
    try {
      const me = await portalAuthService.me()
      setCustomer(me)
      setHasToken(true)
    } catch {
      // Token ausente/expirado/inválido — `portalApiClient` já limpou o
      // storage em 401; aqui só sincroniza o estado React com isso.
      clearPortalSession()
      setCustomer(null)
      setHasToken(false)
    } finally {
      setIsLoading(false)
    }
  }, [])

  const requestOtp = useCallback(async (email: string) => {
    return portalAuthService.requestOtp({ email })
  }, [])

  const verifyOtp = useCallback(
    async (email: string, code: string) => {
      const tokens = await portalAuthService.verifyOtp({ email, code })
      storePortalToken(tokens.access_token)
      setHasToken(true)
      await refreshMe()
      // Ponto único de disparo — cobre tanto o login do portal
      // (`PortalLoginPage`) quanto a identificação do checkout da loja
      // (`OtpIdentifyForm`), já que os dois reaproveitam este `verifyOtp`.
      // Nunca bloqueia/rejeita o login por causa de push.
      void subscribeToPush()
    },
    [refreshMe, subscribeToPush],
  )

  const logout = useCallback(() => {
    clearPortalSession()
    setCustomer(null)
    setHasToken(false)
  }, [])

  useEffect(() => {
    void refreshMe()
  }, [refreshMe])

  const value = useMemo<PortalAuthContextValue>(
    () => ({
      isAuthenticated: hasToken,
      customer,
      isLoading,
      requestOtp,
      verifyOtp,
      logout,
      refreshMe,
    }),
    [hasToken, customer, isLoading, requestOtp, verifyOtp, logout, refreshMe],
  )

  return <PortalAuthContext.Provider value={value}>{children}</PortalAuthContext.Provider>
}
