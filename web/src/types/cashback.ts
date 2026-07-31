/** Resposta de `GET /portal/cashback` (com `?tenant_slug`) — ver `PortalCashbackController`. */
export interface CashbackBalance {
  enabled: boolean
  /** Nome customizado do programa (ex.: "eCash") ou "Cashback" padrão — fallback resolvido sempre no backend. */
  program_name: string
  available_amount: number
  pending_amount: number
  /** Lote `available` mais próximo de expirar, ou `null` se não houver saldo disponível. */
  next_expiration: { amount: number; expires_at: string } | null
  /** % configurada de ganho — usado no checkout pra calcular o preview "você vai ganhar R$Y". */
  earn_percentage: number | null
  earn_max_per_order: number | null
  /** % máximo do subtotal pagável em cashback num pedido. */
  redeem_max_percentage: number | null
}

/** Item de `GET /portal/cashback` sem `?tenant_slug` — 1 por loja com vínculo confirmado. */
export interface CashbackBalanceByStore extends CashbackBalance {
  tenant_uuid: string
  tenant_name: string
  tenant_slug: string
}
