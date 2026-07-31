import { Box, CircularProgress } from '@mui/material'
import { Navigate, Outlet } from 'react-router-dom'
import { usePortalAuth } from '../hooks/usePortalAuth'

/**
 * Equivalente a `ProtectedRoute.tsx`, mas pra sessão do portal do cliente
 * final — nunca redireciona pra `/login` (staff), sempre pra `/portal/entrar`.
 * Espera `isLoading` (verificação de `GET /portal/me` em andamento) antes de
 * decidir, pra não redirecionar de forma otimista enquanto um token
 * existente ainda está sendo validado.
 */
export function PortalProtectedRoute() {
  const { isAuthenticated, isLoading } = usePortalAuth()

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/portal/entrar" replace />
  }

  return <Outlet />
}
