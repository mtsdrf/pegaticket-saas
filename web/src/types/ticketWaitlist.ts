/** Lista de espera de TicketType esgotado (roadmap inventário) — cadastro público sem login. */
export interface JoinTicketTypeWaitlistPayload {
  ticket_type_uuid: string
  name: string
  email: string
  quantity_desired?: number
  /** Honeypot anti-bot — deve permanecer vazio no formulário real. */
  website?: string
  /** Timestamp de quando o formulário carregou, para checagem de tempo mínimo de preenchimento. */
  form_rendered_at?: string
  /** Token do widget Cloudflare Turnstile (ver `components/security/TurnstileWidget`) — ausente quando `VITE_TURNSTILE_SITE_KEY` não está configurada. */
  turnstile_token?: string
}

/** Entrada listada pelo staff (GET /ticket-types/{ticketType}/lista-espera). */
export interface TicketTypeWaitlistEntry {
  uuid: string
  name: string
  email: string
  quantity_desired: number
  notified_at: string | null
  created_at: string
  ticket_type?: {
    uuid: string
    name: string
  }
}
