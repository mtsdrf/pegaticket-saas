import { Box, CircularProgress } from '@mui/material'
import { Navigate } from 'react-router-dom'
import type { ReactElement } from 'react'
import type { PermissionRequirement } from '../types/access'
import { useAuth } from '../hooks/useAuth'
import { isOwnerRole } from '../utils/tenantRole'

interface PermissionRouteProps {
  requirement: PermissionRequirement
  children: ReactElement
  requireOwner?: boolean
  ownerBypassesAccess?: boolean
}

export function PermissionRoute({
  requirement,
  children,
  requireOwner = false,
  ownerBypassesAccess = false,
}: PermissionRouteProps) {
  const { isAccessProfileLoading, hasPermission, accessProfile, activeTenant } = useAuth()
  const isTenantOwner = Boolean(accessProfile?.is_tenant_owner || isOwnerRole(activeTenant?.role, activeTenant?.role_slug))

  if (isAccessProfileLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (!hasPermission(requirement) && !(ownerBypassesAccess && isTenantOwner)) {
    return <Navigate to="/" replace />
  }

  if (requireOwner && !isTenantOwner) {
    return <Navigate to="/" replace />
  }

  return children
}
