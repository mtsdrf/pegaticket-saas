/**
 * Espelha `TenantPagBankConnection::STATUS_*` (backend, R2.1-R2.3) — 13
 * valores no total, 7 do caminho Connect + 6 do caminho Account/Cadastro
 * (roadmap seção 9.4). Ver `TenantPagBankConnectionResource` para o formato
 * exato de resposta.
 */
export type TenantPagBankConnectionStatus =
  | 'not_configured'
  | 'started'
  | 'pending_connection'
  | 'pending_submission'
  | 'submitted'
  | 'pending_kyc'
  | 'under_review'
  | 'verified'
  | 'enabled'
  | 'restricted'
  | 'rejected'
  | 'disabled'
  | 'error'

export type TenantPagBankConnectionType = 'connected_existing' | 'created_by_platform'

export type PagBankAccountPersonType = 'pf' | 'pj'

/** Resposta de `GET /tenant-tools/pagbank-connect/status` — `null` quando o tenant nunca iniciou onboarding. */
export interface TenantPagBankConnection {
  uuid: string
  provider: string
  connection_type: TenantPagBankConnectionType | null
  account_person_type: PagBankAccountPersonType | null
  status: TenantPagBankConnectionStatus
  provider_status: string | null
  account_id: string | null
  document_masked: string | null
  environment: string | null
  connected_at: string | null
  verified_at: string | null
  submitted_at: string | null
  disconnected_at: string | null
  token_expires_at: string | null
}

/**
 * Agrupamento de UI dos 13 status em 6 grupos visuais (decisão de
 * implementação R2.4 — roadmap seção 9.6 documenta os grupos, não define
 * nome de tipo). `disabled` cai em `not_configured` (tenant desconectou,
 * pode recomeçar do zero) — ver `groupConnectionStatus` em
 * `receivingStatusGroups.ts`.
 */
export type ReceivingStatusGroup =
  | 'not_configured'
  | 'in_progress'
  | 'in_review'
  | 'enabled'
  | 'restricted'
  | 'rejected'

export interface PagBankAddressPayload {
  street: string
  number: string
  complement?: string | null
  locality: string
  city: string
  region_code: string
  postal_code: string
  country?: string
}

export interface PagBankPhonePayload {
  country: string
  area: string
  number: string
}

export interface PagBankPersonPayload {
  name: string
  birth_date: string
  mother_name: string
  tax_id: string
  address: PagBankAddressPayload
  phone: PagBankPhonePayload
}

export interface PagBankCompanyPayload {
  name: string
  tax_id: string
  address: PagBankAddressPayload
  phone: PagBankPhonePayload
}

/** Payload de `POST /tenant-tools/pagbank-connect/create-account` — espelha `CreatePagBankSellerAccountRequest`. `company` só é enviado quando `person_type=pj`. */
export interface CreatePagBankSellerAccountPayload {
  person_type: PagBankAccountPersonType
  email: string
  terms_accepted_at: string
  person: PagBankPersonPayload
  company?: PagBankCompanyPayload
}
