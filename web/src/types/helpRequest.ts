/**
 * Central de chamados nativa (roadmap A4, item 17) — espelha
 * `App\Http\Resources\Support\HelpRequestResource`/`CreateHelpRequestRequest`.
 */

/** Só `'open'` existe hoje no backend (`HelpRequest::STATUS_OPEN`) — tipado como `string` porque outros status podem ser adicionados sem exigir mudança aqui. */
export type HelpRequestStatus = string

export interface HelpRequest {
  uuid: string
  subject: string
  description: string
  status: HelpRequestStatus
  attachment_name: string | null
  attachment_url: string | null
  diagnostics: Record<string, unknown> | null
  created_at: string | null
}

export interface CreateHelpRequestPayload {
  subject: string
  description: string
  include_diagnostics?: boolean
}
