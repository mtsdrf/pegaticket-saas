/** Espelha o contrato de `/api/v1/portal/*` (identidade `FinalCustomer`, separada do `User` staff). */

export interface RequestOtpPayload {
  email: string
}

export interface VerifyOtpPayload {
  email: string
  code: string
}

/** Sem `refresh_token` de propósito — o contrato do portal não tem `/portal/auth/refresh`. */
export interface PortalAuthTokens {
  access_token: string
  token_type: string
  expires_in: number
}

export interface PortalLinkedStore {
  tenant_name: string
  client_name: string
  confirmed_at: string
}

/** `GET /portal/me`. */
export interface PortalCustomer {
  uuid: string
  name: string | null
  email: string
  linked_stores: PortalLinkedStore[]
}

/** Item de `GET /portal/orders` — lista agregada entre todas as lojas vinculadas. */
export interface PortalOrderSummary {
  uuid: string
  tenant_name: string
  tenant_slug: string
  is_paid: boolean
  is_delivered: boolean
  is_out_for_delivery: boolean
  /** `cancellation_requested` (roadmap A4) — cliente já solicitou, aguardando aprovação da loja. */
  status: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  total_amount: string
  expected_delivery_date: string | null
  is_cancelled: boolean
  created_at: string
}

/**
 * Item de `GET /portal/orders/{uuid}/items` ("pedir de novo", Delivery Fase
 * 4) — preço/disponibilidade sempre ATUAIS do produto, nunca o valor
 * congelado no pedido original. `product_uuid`/`product_name` podem vir
 * `null` só no caso raro do produto ter sido removido permanentemente do
 * banco (nunca deveria acontecer com soft delete, mas o backend permite).
 */
export interface PortalReorderItem {
  product_uuid: string | null
  product_name: string | null
  quantity: number
  current_price: number | null
  is_available: boolean
}

/** Endereço serializado do perfil do cliente final. */
export interface PortalAddressEndereco {
  logradouro: string | null
  numero: string | null
  complemento: string | null
  cep: string | null
  estado_uuid: string | null
  estado_name: string | null
  cidade_uuid: string | null
  cidade_name: string | null
  bairro_uuid: string | null
  bairro_name: string | null
}

/** Item de `GET /portal/addresses` — 1 por loja com vínculo confirmado (edita `Client.endereco`, não um book global). */
export interface PortalAddress {
  client_uuid: string
  tenant_name: string
  tenant_slug: string
  /** Nome/telefone já cadastrados nessa loja — usados pelo checkout público pra pré-preencher no 2º pedido. */
  client_name: string | null
  client_phone: string | null
  endereco: PortalAddressEndereco | null
}

/** `PUT /portal/addresses/{client_uuid}` — cidade/estado são derivados do bairro no backend, só o bairro é enviado. */
export interface UpdatePortalAddressPayload {
  logradouro: string
  numero?: string | null
  complemento?: string | null
  cep?: string | null
  bairro_uuid: string
}

/** Item de `GET /portal/coupon-redemptions` — histórico read-only de cupons já usados ("Meus vouchers"). */
export interface PortalCouponRedemption {
  coupon_code: string | null
  tenant_name: string | null
  redeemed_at: string
  order_uuid: string | null
}

export interface CreatePortalLinkPayload {
  order_uuid: string
}

/** Resposta de `POST /portal/links` — idempotente, cria ou reaproveita o vínculo. */
export interface PortalLink {
  uuid: string
  tenant_name: string
  client_name: string
  confirmed_at: string
}
