import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { PortalAddress, PortalAddressEndereco, UpdatePortalAddressPayload } from '../types/portal'

/** "Meus endereços" — 1 por loja com vínculo confirmado (roadmap Loja). */
export function listPortalAddresses(): Promise<PortalAddress[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalAddress[]>>('/portal/addresses'))
}

/** Guard de posse no backend — 404 se o `client_uuid` não pertencer a um vínculo confirmado do cliente autenticado. */
export function updatePortalAddress(
  clientUuid: string,
  payload: UpdatePortalAddressPayload,
): Promise<PortalAddressEndereco> {
  return unwrap(
    portalApiClient.put<ApiSuccess<PortalAddressEndereco>>(`/portal/addresses/${clientUuid}`, payload),
  )
}
