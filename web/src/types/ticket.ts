/** Espelha `TicketResource` (staff, tenant-scoped, `GET /tickets` e `GET /tickets/{ticket}`). */

export type TicketStatus = 'pendente' | 'ativo' | 'utilizado' | 'cancelado' | 'estornado' | 'bloqueado' | 'expirado'

export const TICKET_STATUS_OPTIONS: { value: TicketStatus; label: string }[] = [
  { value: 'pendente', label: 'Pendente' },
  { value: 'ativo', label: 'Ativo' },
  { value: 'utilizado', label: 'Utilizado' },
  { value: 'cancelado', label: 'Cancelado' },
  { value: 'estornado', label: 'Estornado' },
  { value: 'bloqueado', label: 'Bloqueado' },
  { value: 'expirado', label: 'Expirado' },
]

export const TICKET_STATUS_LABELS: Record<TicketStatus, string> = Object.fromEntries(
  TICKET_STATUS_OPTIONS.map((option) => [option.value, option.label]),
) as Record<TicketStatus, string>

export interface TicketTypeRef {
  uuid: string
  name: string
}

export interface TicketEventRef {
  uuid: string
  name: string
}

export interface TicketSessionRef {
  uuid: string
  name: string
}

export interface TicketSeatRef {
  uuid: string
  label: string
  sector_name: string | null
}

export interface TicketSaleRef {
  uuid: string
  codigo: string
}

export interface Ticket {
  uuid: string
  code: string
  qr_token: string
  status: TicketStatus
  attendee_name: string | null
  attendee_document: string | null
  issued_at: string | null
  ticket_type?: TicketTypeRef
  event?: TicketEventRef | null
  session?: TicketSessionRef | null
  seat?: TicketSeatRef | null
  sale?: TicketSaleRef
  created_at: string
}

export interface TicketFilters {
  status?: TicketStatus
  ticket_type_uuid?: string
  event_uuid?: string
  event_session_uuid?: string
  sale_uuid?: string
  search?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

/** Espelha `App\DTOs\Ticket\CheckinTicketDTO`/`CheckinTicketRequest` — ao menos um identificador é obrigatório (validado no backend). */
export interface CheckinTicketPayload {
  qr_token?: string
  code?: string
  sale_uuid?: string
  attendee_name?: string
  attendee_document?: string
  event_uuid?: string
  event_session_uuid?: string
  allow_reentry?: boolean
  reason?: string
  gate_name?: string
  device_info?: string
}

export interface CheckinSummaryFilters {
  event_uuid?: string
  event_session_uuid?: string
  gate_name?: string
  limit?: number
}

export type CheckinResult =
  | 'valido'
  | 'reentrada_autorizada'
  | 'ja_utilizado'
  | 'reentrada_nao_permitida'
  | 'reentrada_limite_excedido'
  | 'reentrada_intervalo_nao_atingido'
  | 'cancelado'
  | 'estornado'
  | 'bloqueado'
  | 'evento_incorreto'
  | 'sessao_incorreta'
  | 'nao_encontrado'

export const CHECKIN_RESULT_LABELS: Record<CheckinResult, string> = {
  valido: 'Entrada liberada',
  reentrada_autorizada: 'Reentrada autorizada',
  ja_utilizado: 'Este ingresso já foi utilizado',
  reentrada_nao_permitida: 'Este ingresso não permite reentrada',
  reentrada_limite_excedido: 'O limite de reentradas foi atingido',
  reentrada_intervalo_nao_atingido: 'Ainda não é possível reentrar',
  cancelado: 'Este ingresso foi cancelado',
  estornado: 'Este ingresso foi estornado',
  bloqueado: 'Este ingresso está bloqueado',
  evento_incorreto: 'Ingresso não pertence a este evento',
  sessao_incorreta: 'Ingresso não pertence a esta sessão',
  nao_encontrado: 'Ingresso não encontrado',
}

/** `valido` = verde, `ja_utilizado` = amarelo, qualquer outro = vermelho (regra pedida para a tela de portaria). */
export type CheckinResultTone = 'success' | 'warning' | 'error'

export function checkinResultTone(result: CheckinResult): CheckinResultTone {
  if (result === 'valido' || result === 'reentrada_autorizada') return 'success'
  if (
    result === 'ja_utilizado'
    || result === 'reentrada_limite_excedido'
    || result === 'reentrada_intervalo_nao_atingido'
  ) return 'warning'
  return 'error'
}

export type TicketCheckinAccessType = 'entrada' | 'reentrada' | 'tentativa'

export const TICKET_CHECKIN_ACCESS_TYPE_LABELS: Record<TicketCheckinAccessType, string> = {
  entrada: 'Entrada',
  reentrada: 'Reentrada',
  tentativa: 'Tentativa',
}

export interface TicketCheckinOperatorRef {
  uuid: string
  name: string
}

export interface TicketCheckin {
  uuid: string
  gate_name: string | null
  result: CheckinResult
  access_type: TicketCheckinAccessType
  reason: string | null
  checked_in_at: string
  device_info: string | null
  operator?: TicketCheckinOperatorRef | null
}

export interface CheckinResponse {
  result: CheckinResult
  ticket: Ticket | null
  checkin: TicketCheckin | null
}

export interface CheckinSummaryEntry {
  uuid: string
  gate_name: string | null
  result: CheckinResult
  access_type: TicketCheckinAccessType
  reason: string | null
  checked_in_at: string
  operator?: TicketCheckinOperatorRef | null
  ticket?: {
    uuid: string
    code: string
    attendee_name: string | null
    event?: TicketEventRef | null
    session?: TicketSessionRef | null
  } | null
}

export interface CheckinSummary {
  filters: {
    event_uuid: string | null
    event_session_uuid: string | null
    gate_name: string | null
  }
  counters: {
    total: number
    granted: number
    warning: number
    blocked: number
  }
  recent: CheckinSummaryEntry[]
}
