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
  confirmed_at: string
}

/** `GET /portal/me`. */
export interface PortalCustomer {
  uuid: string
  name: string | null
  email: string
  linked_tenants: PortalLinkedStore[]
}

/** Item de `GET /portal/sales` — lista agregada entre todas as lojas vinculadas. */
export interface PortalSaleSummary {
  uuid: string
  tenant_name: string
  tenant_slug: string
  is_paid: boolean
  /** `cancellation_requested` (roadmap A4) — cliente já solicitou, aguardando aprovação da loja. */
  status: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  total_amount: string
  is_cancelled: boolean
  latest_payment?: {
    status: string
    provider_status: string | null
    method: string | null
    paid_at: string | null
  } | null
  created_at: string
}

/**
 * Item de `GET /portal/sales/{uuid}/items` ("comprar novamente") — preço/
 * disponibilidade sempre ATUAIS do ingresso/adicional, nunca o valor
 * congelado na venda original. `ticket_type_uuid`/`ticket_type_name`
 * cobrem tanto `TicketType` quanto `EventProduct` (mesmo campo no backend,
 * `PortalCustomerService::getSaleItemsForReorder()`) — podem vir `null` só
 * no caso raro do item ter sido removido permanentemente do banco (nunca
 * deveria acontecer com soft delete, mas o backend permite).
 */
export interface PortalResaleItem {
  ticket_type_uuid: string | null
  ticket_type_name: string | null
  quantity: number
  current_price: number | null
  is_available: boolean
}

/** Item de `GET /portal/coupon-redemptions` — histórico read-only de cupons já usados ("Meus vouchers"). */
export interface PortalCouponRedemption {
  coupon_code: string | null
  tenant_name: string | null
  redeemed_at: string
  sale_uuid: string | null
}

export interface CreatePortalLinkPayload {
  sale_uuid: string
}

/** Resposta de `POST /portal/links` — idempotente, cria ou reaproveita o vínculo. */
export interface PortalLink {
  uuid: string
  tenant_name: string
  confirmed_at: string
}

/** Espelha `PortalTicketResource` (`GET /portal/sales/{uuid}/tickets`) — "Meus ingressos", inclui `qr_token` de propósito para renderizar o QR Code. */
export interface PortalTicket {
  uuid: string
  code: string
  qr_token: string
  status: string
  attendee_name: string | null
  attendee_document: string | null
  issued_at: string | null
  ticket_type?: { uuid: string; name: string }
  event?: { uuid: string; name: string } | null
  session?: { uuid: string; name: string; starts_at: string | null } | null
  seat?: { label: string; sector_name: string | null } | null
}

/** "Titularidade e transferência" (roadmap Fase 4) — só ingresso `status: 'ativo'` aceita, ver `TicketService::transfer()`. */
export interface TransferTicketPayload {
  attendee_name: string
  attendee_document?: string
}
