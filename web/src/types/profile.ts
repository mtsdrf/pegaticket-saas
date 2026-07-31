export interface UserProfile {
  uuid: string
  name: string
  email: string
  phone: string | null
  avatar_url: string | null
  pending_email: string | null
}

export interface UpdateProfilePayload {
  name?: string
  /** Alimenta a cobrança da assinatura (dado do pagador no Mercado Pago) — `null` limpa o campo. */
  phone?: string | null
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

export interface RequestEmailChangePayload {
  new_email: string
  current_password: string
}
