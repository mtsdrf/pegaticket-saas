export interface AuthTokens {
  access_token: string
  token_type: string
  expires_in: number
  refresh_token?: string
  tenant_uuid?: string | null
}

export interface MyTenant {
  tenant_uuid: string
  tenant_name: string
  role: string
  role_slug?: string | null
  plan_slug?: string | null
  plan_name?: string | null
  /** Default `false` quando a empresa nunca configurou nada — não exige a permissão `tenant_settings` (ver `services/tenantSettingsService.ts` para a tela dedicada). */
  send_tracking_link_whatsapp: boolean
  /** `null` quando a empresa não tem logo cadastrada — usado pela tela "Redes Sociais" para o toggle "Incluir logo da empresa". */
  logo_url: string | null
}

export interface LoginPayload {
  email: string
  password: string
}

export interface PublicPlan {
  uuid: string
  name: string
  slug: string
  description: string
  sort_order: number
  trial_days: number
  is_trial_default: boolean
}

export interface SelfSignupPayload {
  owner_name: string
  owner_email: string
  password: string
  password_confirmation: string
  tenant_name: string
  tenant_slug: string
  /** Aceite obrigatório e separado dos Termos de Uso. */
  terms_accepted: true
  /** Aceite obrigatório e separado da Política de Privacidade. */
  privacy_accepted: true
}

export interface AcceptInvitePayload {
  token: string
  password: string
  password_confirmation: string
}
