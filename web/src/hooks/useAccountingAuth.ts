import { useContext } from 'react'
import { AccountingAuthContext } from '../contexts/accounting-auth-context'

export function useAccountingAuth() {
  const context = useContext(AccountingAuthContext)
  if (!context) {
    throw new Error('useAccountingAuth must be used within an AccountingAuthProvider')
  }
  return context
}
