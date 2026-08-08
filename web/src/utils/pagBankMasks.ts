import { onlyDigits } from './cpfCnpj'

/** "00000-000" — mesmo formato aceito por `CreatePagBankSellerAccountRequest.person.address.postal_code`. */
export function formatCep(value: string): string {
  const digits = onlyDigits(value).slice(0, 8)
  if (digits.length <= 5) return digits
  return `${digits.slice(0, 5)}-${digits.slice(5)}`
}

/** "(00) 00000-0000" ou "(00) 0000-0000" — só exibição, o payload envia `country`/`area`/`number` separados. */
export function formatBrazilPhoneDisplay(value: string): string {
  const digits = onlyDigits(value).slice(0, 11)
  if (digits.length <= 2) return digits
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

/** Separa um telefone BR mascarado em `area`/`number` (sem DDI) para o payload de `person.phone`/`company.phone`. */
export function splitBrazilPhone(value: string): { area: string; number: string } {
  const digits = onlyDigits(value).slice(0, 11)
  return { area: digits.slice(0, 2), number: digits.slice(2) }
}
