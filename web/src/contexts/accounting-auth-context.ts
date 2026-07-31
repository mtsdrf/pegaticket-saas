import { createContext } from 'react'
import type { AccountingOffice } from '../types/accounting'

export interface AccountingAuthContextValue {
  isAuthenticated: boolean
  office: AccountingOffice | null
  isLoading: boolean
  /** Guarda o token e carrega `/accounting/me`. */
  setSession: (accessToken: string) => Promise<void>
  logout: () => void
  refreshMe: () => Promise<void>
}

export const AccountingAuthContext = createContext<AccountingAuthContextValue | null>(null)
