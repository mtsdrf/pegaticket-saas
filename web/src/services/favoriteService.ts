import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginationMeta } from '../types/pagination'
import type { StorefrontEvent } from '../types/storefront'

/** Favoritos de evento (Delivery Fase 4, migrado de produto para evento — roadmap PegaTicket) — identidade `FinalCustomer`, sempre via `portalApiClient` (`customer.jwt`). */

/** Idempotente no backend: favorito existente é removido, inexistente é criado. */
export function toggleFavorite(eventUuid: string): Promise<{ favorited: boolean }> {
  return unwrap(portalApiClient.post<ApiSuccess<{ favorited: boolean }>>(`/portal/favorites/${eventUuid}/toggle`))
}

export interface FavoriteListResult {
  items: StorefrontEvent[]
  pagination: PaginationMeta
}

export function listFavorites(perPage = 15, page = 1): Promise<FavoriteListResult> {
  return portalApiClient
    .get<ApiSuccess<StorefrontEvent[]>>('/portal/favorites', { params: { per_page: perPage, page } })
    .then((response) => {
      const meta = response.data.meta as { pagination?: PaginationMeta }
      return {
        items: response.data.data,
        pagination: meta.pagination ?? {
          current_page: 1,
          per_page: perPage,
          total: response.data.data.length,
          last_page: 1,
        },
      }
    })
}
