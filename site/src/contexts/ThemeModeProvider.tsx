import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { STORAGE_KEYS } from '../constants/storage'
import {
  ThemeModeContext,
  type ResolvedThemeMode,
  type ThemeModeContextValue,
  type ThemeModePreference,
} from './theme-mode-context'

function readStoredPreference(): ThemeModePreference {
  const stored = localStorage.getItem(STORAGE_KEYS.themeModePreference)
  return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
}

/**
 * Fonte única do modo de tema: resolve preferência salva (ou `system` =
 * `prefers-color-scheme`) e escreve `data-theme` no `<html>` — os tokens
 * CSS (`--pt-*`, usados direto em vários componentes fora do palette do
 * MUI) e o tema MUI (`buildPegaTicketTheme`) precisam concordar sobre o
 * modo atual, então os dois leem daqui, não de `prefers-color-scheme`
 * isoladamente como antes.
 */
export function ThemeModeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreferenceState] = useState<ThemeModePreference>(readStoredPreference)
  const [systemPrefersDark, setSystemPrefersDark] = useState(
    () => window.matchMedia('(prefers-color-scheme: dark)').matches,
  )

  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const listener = (event: MediaQueryListEvent) => setSystemPrefersDark(event.matches)
    media.addEventListener('change', listener)
    return () => media.removeEventListener('change', listener)
  }, [])

  const resolvedMode: ResolvedThemeMode =
    preference === 'system' ? (systemPrefersDark ? 'dark' : 'light') : preference

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', resolvedMode)
  }, [resolvedMode])

  function setPreference(next: ThemeModePreference) {
    setPreferenceState(next)
    localStorage.setItem(STORAGE_KEYS.themeModePreference, next)
  }

  const value = useMemo<ThemeModeContextValue>(
    () => ({ preference, resolvedMode, setPreference }),
    [preference, resolvedMode],
  )

  return <ThemeModeContext.Provider value={value}>{children}</ThemeModeContext.Provider>
}
