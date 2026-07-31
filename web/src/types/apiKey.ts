/** Espelha `ApiKeyResource` — nunca traz a chave em texto puro. */
export interface ApiKey {
  uuid: string
  name: string
  last_used_at: string | null
  revoked_at: string | null
  created_at: string
}

/** Resposta única do `POST /api-keys` — `key` só existe aqui, nunca mais recuperável (ver `ApiKeyController::store`). */
export interface ApiKeyCreateResult extends ApiKey {
  key: string
}

export interface ApiKeyPayload {
  name: string
}
