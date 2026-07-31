import { createContext } from 'react'

export type ThemeModePreference = 'light' | 'dark' | 'system'
export type ResolvedThemeMode = 'light' | 'dark'

export interface ThemeModeContextValue {
  preference: ThemeModePreference
  resolvedMode: ResolvedThemeMode
  setPreference: (preference: ThemeModePreference) => void
}

export const ThemeModeContext = createContext<ThemeModeContextValue | null>(null)
