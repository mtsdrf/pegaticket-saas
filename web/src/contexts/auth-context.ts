import { createContext } from 'react'
import type { AcceptInvitePayload, LoginPayload, MyTenant, SelfSignupPayload } from '../types/auth'
import type { AccessProfile, PermissionRequirement } from '../types/access'

export interface AuthContextValue {
  isAuthenticated: boolean
  activeTenantUuid: string | null
  /** Empresa ativa dentro de `tenants` (mesmo payload de `auth/my-tenants`). */
  activeTenant: MyTenant | null
  tenants: MyTenant[] | null
  tenantsError: string | null
  accessProfile: AccessProfile | null
  isAccessProfileLoading: boolean
  login: (payload: LoginPayload) => Promise<void>
  register: (payload: SelfSignupPayload) => Promise<void>
  acceptInvite: (payload: AcceptInvitePayload) => Promise<void>
  logout: () => Promise<void>
  selectTenant: (tenantUuid: string) => Promise<void>
  hasPermission: (requirement: PermissionRequirement) => boolean
}

export const AuthContext = createContext<AuthContextValue | null>(null)
