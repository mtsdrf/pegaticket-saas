/** Espelha o `TenantResource` do backend (campos usados pela tela "Dados da empresa"). */
export interface TenantProfile {
  uuid: string
  name: string
  slug: string
  logo_url: string | null
  cnpj: string | null
}

export interface UpdateTenantProfilePayload {
  name: string
  logo?: File | null
  cnpj?: string | null
}
