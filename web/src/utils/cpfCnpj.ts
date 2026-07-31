/**
 * Máscara e validação de CPF/CNPJ no mesmo formato aceito pelo backend.
 * CPF continua numérico (11 dígitos). CNPJ agora aceita o formato legado
 * numérico e o novo formato alfanumérico da Receita Federal (14 caracteres).
 */

export function onlyDigits(value: string): string {
  return value.replace(/\D/g, '')
}

export function normalizeCpfCnpj(value: string): string {
  return value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 14)
}

function formatCnpj(value: string): string {
  const normalized = normalizeCpfCnpj(value)

  return normalized
    .replace(/^(.{2})(.{1,})$/, '$1.$2')
    .replace(/^(.{2})\.(.{3})(.{1,})$/, '$1.$2.$3')
    .replace(/^(.{2})\.(.{3})\.(.{3})(.{1,})$/, '$1.$2.$3/$4')
    .replace(/\/(.{4})(.{1,2})$/, '/$1-$2')
}

/** Aplica a máscara de CPF ou CNPJ preservando letras no novo CNPJ alfanumérico. */
export function formatCpfCnpj(value: string): string {
  const normalized = normalizeCpfCnpj(value)

  if (/^\d{0,11}$/.test(normalized)) {
    return normalized
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2')
  }

  return formatCnpj(normalized)
}

function calcCpfDigit(digits: string, factor: number): number {
  let total = 0
  for (const digit of digits) {
    total += Number(digit) * factor
    factor -= 1
  }
  const remainder = (total * 10) % 11
  return remainder === 10 ? 0 : remainder
}

function isValidCpf(digits: string): boolean {
  if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false
  const d1 = calcCpfDigit(digits.slice(0, 9), 10)
  const d2 = calcCpfDigit(digits.slice(0, 9) + d1, 11)
  return digits === digits.slice(0, 9) + String(d1) + String(d2)
}

function calcCnpjDigit(digits: string, weights: number[]): number {
  const total = digits.split('').reduce((sum, digit, index) => sum + Number(digit) * weights[index], 0)
  const remainder = total % 11
  return remainder < 2 ? 0 : 11 - remainder
}

function isValidCnpj(digits: string): boolean {
  if (digits.length !== 14 || /^(\d)\1{13}$/.test(digits)) return false
  const weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const d1 = calcCnpjDigit(digits.slice(0, 12), weights1)
  const d2 = calcCnpjDigit(digits.slice(0, 12) + d1, weights2)
  return digits === digits.slice(0, 12) + String(d1) + String(d2)
}

function isValidAlphanumericCnpj(value: string): boolean {
  return /^[A-Z0-9]{12}\d{2}$/.test(value)
}

/** Aceita string mascarada. Vazio não é validado aqui (campo obrigatório é responsabilidade do form). */
export function isValidCpfCnpj(value: string): boolean {
  const normalized = normalizeCpfCnpj(value)
  if (/^\d{11}$/.test(normalized)) return isValidCpf(normalized)
  if (/^\d{14}$/.test(normalized)) return isValidCnpj(normalized)
  if (normalized.length === 14) return isValidAlphanumericCnpj(normalized)
  return false
}
