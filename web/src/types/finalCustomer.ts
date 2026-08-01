/** Comprador global (`FinalCustomer`) referenciado dentro de um resultado de busca de `GET /final-customers`. */
export interface FinalCustomerSearchResultRef {
  /** Uuid GLOBAL do comprador — é ESTE que vai em `SalePayload.final_customer_uuid`, nunca o `uuid` do nível acima (que é o vínculo tenant×comprador). */
  uuid: string
  name: string
  last_name?: string | null
  email?: string | null
}

/**
 * Item de `GET /final-customers` (busca staff-facing pós-migração
 * `Client`→`FinalCustomer`). Tem DOIS uuids: `uuid` (vínculo tenant×comprador,
 * só serve como chave de lista/React key) e `final_customer.uuid` (comprador
 * global, o que o backend espera em `POST /orders`).
 */
export interface FinalCustomerSearchResult {
  /** Uuid do vínculo tenant×comprador — NÃO usar no pedido. */
  uuid: string
  cpf_cnpj: string | null
  phone_primary: string | null
  phone_secondary?: string | null
  is_active: boolean
  is_trusted: boolean
  final_customer: FinalCustomerSearchResultRef
}
