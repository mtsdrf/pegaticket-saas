export interface GuestListEntry {
  uuid: string
  name: string
  email: string
  document: string | null
  redeemed_at: string | null
  invite_url: string
}

export interface GuestList {
  uuid: string
  name: string
  quantity_per_entry: number
  notes: string | null
  entries_count: number | null
  redeemed_entries_count: number | null
  event: { uuid: string; name: string }
  session: { uuid: string; name: string } | null
  ticket_type: { uuid: string; name: string }
  entries?: GuestListEntry[]
  created_at: string
}

export interface CreateGuestListPayload {
  event_uuid: string
  event_session_uuid?: string
  ticket_type_uuid: string
  name: string
  quantity_per_entry?: number
  notes?: string
}

export interface AddGuestListEntryPayload {
  name: string
  email: string
  document?: string
}

export interface GuestInvite {
  uuid: string
  name: string
  email: string
  is_redeemed: boolean
  redeemed_at: string | null
  quantity: number
  event: { uuid: string; name: string }
  session: { uuid: string; name: string; starts_at: string | null } | null
  ticket_type: { uuid: string; name: string }
}

export interface RedeemGuestInvitePayload {
  name: string
  email: string
  document?: string
  /** Honeypot anti-bot (roadmap Fase 7) — deve permanecer vazio; nunca visível para um usuário real. */
  website?: string
  /** Timestamp de quando o formulário carregou, para checagem de tempo mínimo de preenchimento (roadmap Fase 7). */
  form_rendered_at?: string
  /** Token do widget Cloudflare Turnstile (ver `components/security/TurnstileWidget`) — ausente quando `VITE_TURNSTILE_SITE_KEY` não está configurada. */
  turnstile_token?: string
}

export interface RedeemGuestInviteResult {
  sale_uuid: string
  tracking_url: string
}
