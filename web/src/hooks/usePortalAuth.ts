import { useContext } from 'react'
import { PortalAuthContext } from '../contexts/portal-auth-context'

export function usePortalAuth() {
  const context = useContext(PortalAuthContext)
  if (!context) {
    throw new Error('usePortalAuth must be used within a PortalAuthProvider')
  }
  return context
}
