import { useMediaQuery } from '@mui/material'

/**
 * Breakpoint próprio do hub de Configurações (≥1024px, conforme a proposta
 * de reorganização) — mestre-detalhe lado a lado acima disso, navegação de
 * página cheia (com "Voltar") abaixo. Não usa `theme.breakpoints.up('lg')`
 * porque o `lg` padrão do MUI é 1200px, não 1024px.
 */
export function useSettingsDesktopLayout(): boolean {
  return useMediaQuery('(min-width:1024px)')
}
