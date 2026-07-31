export interface StoreDeliveryFeeBairroRef {
  uuid: string
  name: string
}

/** `GET /store-delivery-fees` / `POST /store-delivery-fees` (upsert). */
export interface StoreDeliveryFee {
  uuid: string
  fee: number
  bairro: StoreDeliveryFeeBairroRef
}
