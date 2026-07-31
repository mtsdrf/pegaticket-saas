import { createContext } from 'react'
import type { PortalCustomer } from '../types/portal'

export interface PortalAuthContextValue {
  isAuthenticated: boolean
  customer: PortalCustomer | null
  isLoading: boolean
  /** Resolve com os minutos de validade do código (ver `portalAuthService.requestOtp`). */
  requestOtp: (email: string) => Promise<number>
  verifyOtp: (email: string, code: string) => Promise<void>
  logout: () => void
  refreshMe: () => Promise<void>
}

export const PortalAuthContext = createContext<PortalAuthContextValue | null>(null)
