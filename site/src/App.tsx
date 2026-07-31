import { CssBaseline } from '@mui/material'
import { ThemeProvider } from '@mui/material/styles'
import { useMemo, type ReactNode } from 'react'
import { ThemeFab } from './components/ThemeFab'
import { ThemeModeProvider } from './contexts/ThemeModeProvider'
import { useThemeMode } from './hooks/useThemeMode'
import { buildMaskatsTheme } from './theme'

interface AppProps {
  children: ReactNode
}

function ThemedApp({ children }: AppProps) {
  const { resolvedMode } = useThemeMode()
  const theme = useMemo(() => buildMaskatsTheme(resolvedMode), [resolvedMode])

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <a href="#conteudo" className="mk-skip-link">
        Pular para o conteúdo
      </a>
      {children}
      <ThemeFab />
    </ThemeProvider>
  )
}

/**
 * Shell compartilhado pelos dois entry points do site (multi-page Vite,
 * ver vite.config.ts): `main.tsx` (home) e `precos-main.tsx` (/precos).
 * Cada página injeta seu próprio conteúdo via `children`.
 */
export function App({ children }: AppProps) {
  return (
    <ThemeModeProvider>
      <ThemedApp>{children}</ThemedApp>
    </ThemeModeProvider>
  )
}
