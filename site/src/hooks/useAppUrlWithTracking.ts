import { APP_URL } from '../constants/app'
import { withMarketingTracking } from '../utils/marketingTracking'

/**
 * `APP_URL` (destino dos CTAs de conversão) enriquecido com o UTM salvo em
 * `localStorage` (se houver e não tiver expirado) — ver
 * `utils/marketingTracking.ts`. Site multi-página com navegação por reload
 * completo (ver `vite.config.ts`), então não precisa de memoização: cada
 * carga de página já é um novo mount.
 */
export function useAppUrlWithTracking(): string {
  return withMarketingTracking(APP_URL)
}
