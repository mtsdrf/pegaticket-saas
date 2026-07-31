import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { StoreDeliveryFee } from '../types/storeDeliveryFee'

export function listDeliveryFees(): Promise<StoreDeliveryFee[]> {
  return unwrap(apiClient.get<ApiSuccess<StoreDeliveryFee[]>>('/store-delivery-fees'))
}

/** Upsert — um bairro só pode ter 1 taxa por tenant, backend atualiza se já existir. */
export function upsertDeliveryFee(bairroUuid: string, fee: number): Promise<StoreDeliveryFee> {
  return unwrap(
    apiClient.post<ApiSuccess<StoreDeliveryFee>>('/store-delivery-fees', { bairro_uuid: bairroUuid, fee }),
  )
}

export function deleteDeliveryFee(uuid: string): Promise<void> {
  return apiClient.delete(`/store-delivery-fees/${uuid}`).then(() => undefined)
}
