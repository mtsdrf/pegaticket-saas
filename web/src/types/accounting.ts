/**
 * Contrato de `/api/v1/accounting/*` e `/api/v1/accounting-access-requests/*`
 * (identidade `AccountingOffice`, 3ª identidade do sistema — separada de
 * `User` staff e `FinalCustomer`). Espelha os Resources em
 * `api/app/Http/Resources/Accounting/`.
 */

export interface AccountingOffice {
  uuid: string
  cnpj: string
  company_name: string
  responsible_name: string
  email: string
  totp_enabled: boolean
  totp_enabled_at: string | null
  created_at: string | null
}

export interface RegisterAccountingPayload {
  cnpj: string
  company_name: string
  responsible_name: string
  email: string
  password: string
}

/** Resposta de `POST /accounting/register` — TOTP ainda precisa ser confirmado. */
export interface RegisterAccountingResult {
  office: AccountingOffice
  /** Secret base32 pra entrada manual no app autenticador. */
  totp_secret: string
  /** URI `otpauth://…` pra gerar QR Code client-side. */
  otpauth_uri: string
}

export interface ConfirmTotpPayload {
  email: string
  password: string
  code: string
}

export interface AccountingLoginPayload {
  email: string
  password: string
  code: string
}

/** Sem `refresh_token` de propósito — o contrato do contador não tem `/accounting/refresh`. */
export interface AccountingAuthTokens {
  access_token: string
  token_type: string
  expires_in: number
}

export type AccountingAccessStatus = 'pending' | 'approved' | 'revoked'

/** Escopos concedidos pelo tenant ao aprovar o vínculo (backend: enum `in:...`). */
export type AccountingScope = 'financial.read' | 'fiscal.read' | 'fiscal.write' | 'reports.read'

/**
 * Vínculo contador <-> tenant (`AccountingOfficeTenantResource`). Serve os dois
 * lados: pro contador vem `tenant`; pro tenant vem `accounting_office`.
 */
export interface AccountingOfficeTenantLink {
  uuid: string
  status: AccountingAccessStatus
  scopes: AccountingScope[]
  requested_at: string | null
  approved_at: string | null
  revoked_at: string | null
  accounting_office?: {
    uuid: string
    cnpj: string
    company_name: string
    responsible_name: string
    email: string
  }
  tenant?: {
    uuid: string
    name: string
    cnpj: string | null
  }
}

export interface CreateAccessRequestPayload {
  tenant_cnpj: string
}

export interface ApproveAccessPayload {
  scopes: AccountingScope[]
}

export interface AccountingReportPeriod {
  from?: string
  to?: string
}

export interface AccountingSalesItem {
  order_uuid: string
  client_name: string | null
  created_at: string | null
  total_amount: string
  is_paid: boolean
  is_delivered: boolean
}

export interface AccountingSalesReport {
  from: string
  to: string
  total_orders: number
  total_revenue: string
  items: AccountingSalesItem[]
}

export interface AccountingCashFlowEntry {
  date: string | null
  order_uuid: string
  client_name: string | null
  amount: string
}

export interface AccountingCashFlowReport {
  from: string
  to: string
  total_in: string
  entries: AccountingCashFlowEntry[]
}

export interface AccountingDreReport {
  from: string
  to: string
  revenue: string
  product_cost: string
  gross_profit: string
}

export type AccountingReportKind = 'sales' | 'cash-flow' | 'dre'

export type AccountingMessageSender = 'tenant' | 'accounting_office'
export type AccountingMessageStatus = 'open' | 'answered' | 'closed'

export interface AccountingMessage {
  uuid: string
  sender_type: AccountingMessageSender
  body: string
  due_date: string | null
  status: AccountingMessageStatus
  attachment_name: string | null
  attachment_url: string | null
  created_at: string | null
}

export interface CreateAccountingMessagePayload {
  body: string
  due_date?: string | null
  attachment?: File | null
}
