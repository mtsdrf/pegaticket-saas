import DarkModeRoundedIcon from '@mui/icons-material/DarkModeRounded'
import LightModeRoundedIcon from '@mui/icons-material/LightModeRounded'
import { Fab, Tooltip } from '@mui/material'
import { useThemeMode } from '../hooks/useThemeMode'

/**
 * Toggle de tema flutuante, fixo no canto inferior direito — diferente do
 * app principal (`web/`, toggle no AppBar): venda explícito do usuário
 * para a landing (ver CLAUDE.md, seção "Tema claro/escuro + toggle
 * flutuante"). Ciclo simples de 2 estados; o estado inicial (antes do
 * primeiro clique) já vem resolvido de `prefers-color-scheme` via
 * `ThemeModeProvider`.
 */
export function ThemeFab() {
  const { resolvedMode, setPreference } = useThemeMode()
  const isDark = resolvedMode === 'dark'
  const label = isDark ? 'Ativar tema claro' : 'Ativar tema escuro'

  return (
    <Tooltip title={label} placement="left">
      <Fab
        onClick={() => setPreference(isDark ? 'light' : 'dark')}
        aria-label={label}
        color="primary"
        size="medium"
        sx={{
          position: 'fixed',
          bottom: { xs: 16, sm: 24 },
          right: { xs: 16, sm: 24 },
          zIndex: 1300,
          transition: 'transform 0.2s ease, background-color 0.2s ease',
          '@media (prefers-reduced-motion: reduce)': {
            transition: 'none',
          },
          '&:hover': {
            transform: 'scale(1.06)',
          },
        }}
      >
        {isDark ? <LightModeRoundedIcon /> : <DarkModeRoundedIcon />}
      </Fab>
    </Tooltip>
  )
}
