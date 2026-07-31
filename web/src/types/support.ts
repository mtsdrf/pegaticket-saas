/**
 * Central de chamados nativa (roadmap A4, item 17) — espelha
 * `App\Http\Resources\Support\SupportTicketResource`/`CreateSupportTicketRequest`.
 */

/** Só `'open'` existe hoje no backend (`SupportTicket::STATUS_OPEN`) — tipado como `string` porque outros status podem ser adicionados sem exigir mudança aqui. */
export type SupportTicketStatus = string

export interface SupportTicket {
  uuid: string
  subject: string
  description: string
  status: SupportTicketStatus
  attachment_name: string | null
  attachment_url: string | null
  diagnostics: Record<string, unknown> | null
  created_at: string | null
}

export interface CreateSupportTicketPayload {
  subject: string
  description: string
  include_diagnostics?: boolean
}
