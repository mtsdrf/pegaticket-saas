import { Box, CircularProgress } from '@mui/material'
import { Navigate, Outlet } from 'react-router-dom'
import { useAccountingAuth } from '../hooks/useAccountingAuth'

/**
 * Equivalente a `PortalProtectedRoute`, mas pra sessão do módulo do contador —
 * nunca redireciona pra `/login` (staff) nem `/portal/entrar` (cliente),
 * sempre pra `/contador/entrar`. Espera `isLoading` (verificação de
 * `GET /accounting/me`) antes de decidir, pra não redirecionar de forma
 * otimista enquanto um token existente ainda está sendo validado.
 */
export function AccountingProtectedRoute() {
  const { isAuthenticated, isLoading } = useAccountingAuth()

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/contador/entrar" replace />
  }

  return <Outlet />
}
