export type PrivacyRequesterRole = 'empresa' | 'usuario_interno' | 'titular_final' | 'outro'
export type PrivacyRequestType = 'acesso' | 'correcao' | 'exclusao' | 'anonimizacao' | 'oposicao' | 'outro'
export type PrivacyRequestChannel = 'email' | 'whatsapp' | 'telefone' | 'atendimento_interno' | 'outro'
export type PrivacyRequestStatus = 'open' | 'in_progress' | 'completed' | 'rejected'

export interface PrivacyRequest {
  uuid: string
  requester_name: string
  requester_email: string | null
  requester_role: PrivacyRequesterRole
  request_type: PrivacyRequestType
  channel: PrivacyRequestChannel | null
  status: PrivacyRequestStatus
  subject: string
  description: string
  resolution_notes: string | null
  requested_at: string | null
  resolved_at: string | null
  created_at: string | null
  updated_at: string | null
  requested_by_user: {
    uuid: string
    name: string
    email: string
  } | null
}

export interface CreatePrivacyRequestPayload {
  requester_name: string
  requester_email?: string | null
  requester_role: PrivacyRequesterRole
  request_type: PrivacyRequestType
  channel?: PrivacyRequestChannel | null
  subject: string
  description: string
}

export interface UpdatePrivacyRequestPayload {
  status: PrivacyRequestStatus
  resolution_notes?: string | null
}
