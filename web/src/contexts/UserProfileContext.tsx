import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useAuth } from '../hooks/useAuth'
import * as profileService from '../services/profileService'
import { getApiErrorMessage } from '../types/api'
import type { UserProfile } from '../types/profile'
import { UserProfileContext, type UserProfileContextValue } from './user-profile-context'

/**
 * Dados de perfil (nome/e-mail/avatar) não vêm do `AccessProfile` (só
 * permissões) — carregados à parte via `GET /auth/profile` e compartilhados
 * entre `UserMenu` (header) e `MyAccountPage` pra que uma edição de nome/foto
 * reflita no header sem precisar recarregar a página.
 */
export function UserProfileProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  const [profile, setProfileState] = useState<UserProfile | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    if (!isAuthenticated) {
      setProfileState(null)
      setError(null)
      return
    }

    setIsLoading(true)
    setError(null)
    try {
      const data = await profileService.getProfile()
      setProfileState(data)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar seus dados agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [isAuthenticated])

  useEffect(() => {
    void refresh()
  }, [refresh])

  const setProfile = useCallback((next: UserProfile) => {
    setProfileState(next)
  }, [])

  const value = useMemo<UserProfileContextValue>(
    () => ({ profile, isLoading, error, refresh, setProfile }),
    [profile, isLoading, error, refresh, setProfile],
  )

  return <UserProfileContext.Provider value={value}>{children}</UserProfileContext.Provider>
}
