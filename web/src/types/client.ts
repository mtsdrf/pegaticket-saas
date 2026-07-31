export interface ClientEndereco {
  uuid: string
  logradouro: string
  numero: string | null
  complemento: string | null
  cep: string | null
  estado_uuid: string
  estado_name: string
  cidade_uuid: string
  cidade_name: string
  bairro_uuid: string
  bairro_name: string
  lat: number | null
  lng: number | null
}

export interface Client {
  uuid: string
  name: string
  phone_primary: string | null
  phone_secondary: string | null
  /** Só dígitos (11 = CPF, 14 = CNPJ). Obrigatório na criação (a partir de 2026-07-24, ver ClientFormPage) — necessário pra gerar cobrança Pix do pedido; clientes antigos podem ter `null`. */
  cpf_cnpj: string | null
  ie: string | null
  ie_indicator: 'contribuinte' | 'isento' | 'nao_contribuinte' | null
  notes: string | null
  is_trusted: boolean
  is_active: boolean
  endereco: ClientEndereco
  created_at: string
}

/** Corpo enviado para POST/PUT — campos opcionais no PUT (update parcial). */
export interface ClientPayload {
  name: string
  phone_primary?: string
  phone_secondary?: string
  /** Só dígitos. Obrigatório no POST (create) a partir de 2026-07-24 — ClientFormPage garante isso no client-side antes de enviar. */
  cpf_cnpj?: string
  ie?: string
  ie_indicator?: 'contribuinte' | 'isento' | 'nao_contribuinte'
  notes?: string
  is_trusted?: boolean
  is_active?: boolean
  logradouro: string
  numero?: string
  complemento?: string
  cep?: string
  estado_uuid: string
  cidade_uuid: string
  bairro_uuid: string
  /** Só enviar junto com `lng` (os dois juntos ou nenhum) — regra espelhada do backend. */
  lat?: number
  lng?: number
}

export interface ClientFilters {
  /** Busca genérica OR-contains (name, phone_primary, cidade_name) — usada pela caixa "Buscar em todos os campos". */
  q?: string
  name?: string
  phone_primary?: string
  cidade_name?: string
  cidade_uuid?: string
  bairro_uuid?: string
  is_trusted?: boolean
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ClientListResult {
  clients: Client[]
  pagination: PaginationMeta
}
