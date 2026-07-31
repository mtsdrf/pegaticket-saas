import DarkModeRoundedIcon from '@mui/icons-material/DarkModeRounded'
import LightModeRoundedIcon from '@mui/icons-material/LightModeRounded'
import { Fab, Tooltip } from '@mui/material'
import { useThemeMode } from '../hooks/useThemeMode'

type ThemeFabProps = {
  /** Offset vertical extra (px) — usado onde já existe barra fixa no rodapé
   * (ex. `StorefrontBottomNav`), pra não sobrepor o toque. Somado ao `bottom`
   * responsivo padrão (16px/24px). Ignorado quando `bottomFixed` é passado. */
  bottomOffset?: number
  /**
   * `bottom` fixo em px, substituindo o padrão responsivo — usado quando o
   * espaçamento precisa ser um valor exato (ex. "16px acima da barra fixa do
   * rodapé"), igual em qualquer breakpoint, em vez de somar a um `bottom`
   * base que já varia entre mobile/desktop.
   */
  bottomFixed?: number
}

/**
 * Toggle de tema flutuante, fixo no canto inferior direito — usado nas áreas
 * públicas/cliente final que não têm o `ThemeModeSwitch` do `UserMenu`
 * (staff autenticado, `AppLayout`). Ciclo simples de 2 estados; o estado
 * inicial (antes do primeiro clique) já vem resolvido de
 * `prefers-color-scheme` via `ThemeModeProvider`.
 */
export function ThemeFab({ bottomOffset = 0, bottomFixed }: ThemeFabProps) {
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
          bottom: bottomFixed !== undefined ? bottomFixed : { xs: 16 + bottomOffset, sm: 24 + bottomOffset },
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
