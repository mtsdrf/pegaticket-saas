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
  gate_name?: string
  device_info?: string
}

export type CheckinResult =
  | 'valido'
  | 'ja_utilizado'
  | 'cancelado'
  | 'estornado'
  | 'bloqueado'
  | 'evento_incorreto'
  | 'sessao_incorreta'
  | 'nao_encontrado'

export const CHECKIN_RESULT_LABELS: Record<CheckinResult, string> = {
  valido: 'Entrada liberada',
  ja_utilizado: 'Este ingresso já foi utilizado',
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
  if (result === 'valido') return 'success'
  if (result === 'ja_utilizado') return 'warning'
  return 'error'
}

export interface TicketCheckinOperatorRef {
  uuid: string
  name: string
}

export interface TicketCheckin {
  uuid: string
  gate_name: string | null
  result: CheckinResult
  checked_in_at: string
  device_info: string | null
  operator?: TicketCheckinOperatorRef | null
}

export interface CheckinResponse {
  result: CheckinResult
  ticket: Ticket | null
  checkin: TicketCheckin | null
}
