import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { STORAGE_KEYS } from '../constants/storage'
import { clearSession, storeTokens } from '../services/apiClient'
import * as authService from '../services/authService'
import type { AcceptInvitePayload, AuthTokens, LoginPayload, MyTenant, SelfSignupPayload } from '../types/auth'
import type { AccessProfile, PermissionRequirement } from '../types/access'
import { AuthContext, type AuthContextValue } from './auth-context'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [accessToken, setAccessToken] = useState<string | null>(() =>
    localStorage.getItem(STORAGE_KEYS.accessToken),
  )
  const [activeTenantUuid, setActiveTenantUuid] = useState<string | null>(() =>
    localStorage.getItem(STORAGE_KEYS.activeTenantUuid),
  )
  const [accessProfile, setAccessProfile] = useState<AccessProfile | null>(null)
  const [isAccessProfileLoading, setIsAccessProfileLoading] = useState(Boolean(accessToken))
  const [tenants, setTenants] = useState<MyTenant[] | null>(null)
  const [tenantsError, setTenantsError] = useState<string | null>(null)

  const loadAccessProfile = useCallback(async () => {
    if (!localStorage.getItem(STORAGE_KEYS.accessToken)) {
      setAccessProfile(null)
      setIsAccessProfileLoading(false)
      return
    }

    setIsAccessProfileLoading(true)
    try {
      const profile = await authService.getAccessProfile()
      setAccessProfile(profile)
    } catch {
      if (!localStorage.getItem(STORAGE_KEYS.accessToken)) {
        setAccessProfile(null)
      }
    } finally {
      setIsAccessProfileLoading(false)
    }
  }, [])

  /**
   * Único ponto de carga de `auth/my-tenants` (fonte central, evita repetir o
   * fetch em `TenantMenu`/`TenantUserFormPage` — ambos passaram a ler daqui
   * via `useTenants`). Também é de onde `activeTenant.send_tracking_link_whatsapp`
   * é derivado, sem chamada extra a `/tenant-settings` (ver `useTenants.ts`).
   */
  const loadTenants = useCallback(async () => {
    if (!localStorage.getItem(STORAGE_KEYS.accessToken)) {
      setTenants(null)
      setTenantsError(null)
      return
    }

    try {
      const data = await authService.myTenants()
      setTenants(data)
      setTenantsError(null)
    } catch {
      if (!localStorage.getItem(STORAGE_KEYS.accessToken)) {
        setTenants(null)
      }
      setTenantsError('Não foi possível carregar suas empresas.')
    }
  }, [])

  const applySession = useCallback((tokens: AuthTokens) => {
    storeTokens(tokens)
    setAccessToken(tokens.access_token)

    if (tokens.tenant_uuid) {
      localStorage.setItem(STORAGE_KEYS.activeTenantUuid, tokens.tenant_uuid)
      setActiveTenantUuid(tokens.tenant_uuid)
      return
    }

    localStorage.removeItem(STORAGE_KEYS.activeTenantUuid)
    setActiveTenantUuid(null)
  }, [])

  const hasPermission = useCallback(
    (requirement: PermissionRequirement) => {
      const source = requirement.scope === 'tenant' ? accessProfile?.tenant_permissions : accessProfile?.global_permissions
      if (!source) return false
      return source.includes(`${requirement.functionality}:${requirement.action}`)
    },
    [accessProfile],
  )

  const login = useCallback(async (payload: LoginPayload) => {
    const tokens = await authService.login(payload)
    applySession(tokens)
    await Promise.all([loadAccessProfile(), loadTenants()])
  }, [applySession, loadAccessProfile, loadTenants])

  const register = useCallback(async (payload: SelfSignupPayload) => {
    const tokens = await authService.register(payload)
    applySession(tokens)
    await Promise.all([loadAccessProfile(), loadTenants()])
  }, [applySession, loadAccessProfile, loadTenants])

  const acceptInvite = useCallback(async (payload: AcceptInvitePayload) => {
    const tokens = await authService.acceptInvite(payload)
    applySession(tokens)
    await Promise.all([loadAccessProfile(), loadTenants()])
  }, [applySession, loadAccessProfile, loadTenants])

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      clearSession()
      setAccessToken(null)
      setActiveTenantUuid(null)
      setAccessProfile(null)
      setIsAccessProfileLoading(false)
      setTenants(null)
      setTenantsError(null)
    }
  }, [])

  const selectTenant = useCallback(async (tenantUuid: string) => {
    const tokens = await authService.switchTenant(tenantUuid)
    storeTokens(tokens)
    localStorage.setItem(STORAGE_KEYS.activeTenantUuid, tenantUuid)
    setAccessToken(tokens.access_token)
    setActiveTenantUuid(tenantUuid)
    await loadAccessProfile()
  }, [loadAccessProfile])

  useEffect(() => {
    void loadAccessProfile()
    void loadTenants()
  }, [loadAccessProfile, loadTenants])

  const activeTenant = useMemo(
    () => tenants?.find((item) => item.tenant_uuid === activeTenantUuid) ?? null,
    [tenants, activeTenantUuid],
  )

  const value = useMemo<AuthContextValue>(
    () => ({
      isAuthenticated: Boolean(accessToken),
      activeTenantUuid,
      activeTenant,
      tenants,
      tenantsError,
      accessProfile,
      isAccessProfileLoading,
      login,
      register,
      acceptInvite,
      logout,
      selectTenant,
      hasPermission,
    }),
    [
      accessToken,
      activeTenantUuid,
      activeTenant,
      tenants,
      tenantsError,
      accessProfile,
      isAccessProfileLoading,
      login,
      register,
      acceptInvite,
      logout,
      selectTenant,
      hasPermission,
    ],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
