export type CrudFieldType = 'text' | 'number' | 'switch' | 'select'

export interface CrudFieldOption {
  value: string
  label: string
}

export interface CrudFieldDef {
  name: string
  label: string
  type: CrudFieldType
  required?: boolean
  /** Só pra type: 'select'. */
  options?: CrudFieldOption[]
  /** Metade da largura numa grade de 2 colunas (desktop); default ocupa a linha toda. */
  half?: boolean
  /** Só pra type: 'text' — espelha `max:N`/`size:N` do FormRequest correspondente no backend. */
  maxLength?: number
  /** Só pra type: 'number' — espelha `min:N` do FormRequest correspondente no backend. */
  min?: number
  /** Só pra type: 'number'. Default '1' quando omitido. */
  step?: number | string
}

export type CrudFormValues = Record<string, string | number | boolean>
