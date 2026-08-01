import { useTheme } from '@mui/material/styles'
import useMediaQuery from '@mui/material/useMediaQuery'
import { VenueMapEditor } from './MapEditor/VenueMapEditor'
import { SeatsGridFallback } from './MapEditor/SeatsGridFallback'

/**
 * Editor visual drag-and-drop do mapa (`VenueMapEditor`) é exceção deliberada
 * ao mobile-first do projeto: posicionar dezenas/centenas de elementos com
 * precisão é impraticável em touch pequeno e é ação administrativa rara, não
 * de uso diário. Abaixo do breakpoint `md`, cai para a grid/formulário que já
 * existia (`SeatsGridFallback`), somente-leitura/edição por formulário.
 */
export function VenueSeatsPage() {
  const theme = useTheme()
  const isSmallViewport = useMediaQuery(theme.breakpoints.down('md'))

  return isSmallViewport ? <SeatsGridFallback /> : <VenueMapEditor />
}
