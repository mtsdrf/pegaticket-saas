import { CssBaseline } from '@mui/material'
import { ThemeProvider } from '@mui/material/styles'
import { useEffect, useMemo } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { ConnectionStatusBanner } from '../components/shared/ConnectionStatusBanner'
import { AuthProvider } from '../contexts/AuthContext'
import { ThemeModeProvider } from '../contexts/ThemeModeProvider'
import { UserProfileProvider } from '../contexts/UserProfileContext'
import { useThemeMode } from '../hooks/useThemeMode'
import { AppRoutes } from '../routes/AppRoutes'
import { buildPegaTicketTheme } from '../theme'
import { captureAppMarketingTrackingFromUrl } from '../utils/appMarketingTracking'

function ThemedApp() {
  const { resolvedMode } = useThemeMode()
  const theme = useMemo(() => buildPegaTicketTheme(resolvedMode), [resolvedMode])

  useEffect(() => {
    captureAppMarketingTrackingFromUrl(window.location.search)
  }, [])

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      {/* Nível mais alto da árvore — precisa aparecer em qualquer tela
          (app autenticado, Portal, Contador, Loja pública), independente
          de qual layout por rota está montado. */}
      <ConnectionStatusBanner />
      <BrowserRouter>
        <AuthProvider>
          <UserProfileProvider>
            <AppRoutes />
          </UserProfileProvider>
        </AuthProvider>
      </BrowserRouter>
    </ThemeProvider>
  )
}

export function App() {
  return (
    <ThemeModeProvider>
      <ThemedApp />
    </ThemeModeProvider>
  )
}
