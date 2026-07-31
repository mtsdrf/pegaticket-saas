import { useMemo } from 'react'
import type { PermissionRequirement } from '../types/access'
import { useAuth } from './useAuth'

export function useAccessControl() {
  const { accessProfile, activeTenantUuid, hasPermission } = useAuth()

  const canAccessTenantContext = Boolean(activeTenantUuid && accessProfile?.has_tenant_context)

  return useMemo(
    () => ({
      accessProfile,
      canAccessTenantContext,
      can: (requirement: PermissionRequirement) => hasPermission(requirement),
      canAny: (requirements: PermissionRequirement[]) => requirements.some((requirement) => hasPermission(requirement)),
    }),
    [accessProfile, canAccessTenantContext, hasPermission],
  )
}
