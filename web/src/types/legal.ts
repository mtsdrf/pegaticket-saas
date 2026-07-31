export type LegalDocumentType = 'terms' | 'privacy'

export interface LegalDocument {
  uuid: string
  type: LegalDocumentType
  version: string
  /** Texto do documento (markdown/texto simples) — renderizado pré-formatado. */
  content: string
  published_at: string | null
}
