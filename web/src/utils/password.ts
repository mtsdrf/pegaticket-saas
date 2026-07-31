/**
 * Política de senha forte (criação/troca de senha) — replica localmente,
 * só para feedback instantâneo, as mesmas 5 regras aplicadas pelo backend
 * via `Password::defaults()` (`AppServiceProvider::boot`): mínimo 12
 * caracteres, maiúscula, minúscula, número e símbolo. Não é fonte de
 * verdade — o backend sempre revalida e é quem decide de fato.
 */
export const PASSWORD_POLICY_HINT = 'Mínimo de 12 caracteres, com maiúscula, minúscula, número e símbolo.'

const MIN_LENGTH = 12
const UPPERCASE_RE = /[A-ZÀ-Ý]/
const LOWERCASE_RE = /[a-zà-ÿ]/
const NUMBER_RE = /[0-9]/
const SYMBOL_RE = /[^A-Za-zÀ-ÿ0-9]/

const UPPERCASE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
const LOWERCASE_CHARS = 'abcdefghijkmnpqrstuvwxyz'
const NUMBER_CHARS = '23456789'
const SYMBOL_CHARS = '!@#$%^&*()-_=+[]{}?'
const ALL_CHARS = UPPERCASE_CHARS + LOWERCASE_CHARS + NUMBER_CHARS + SYMBOL_CHARS

export function getPasswordStrengthIssues(password: string): string[] {
  const issues: string[] = []

  if (password.length < MIN_LENGTH) issues.push(`Mínimo de ${MIN_LENGTH} caracteres`)
  if (!UPPERCASE_RE.test(password)) issues.push('Uma letra maiúscula')
  if (!LOWERCASE_RE.test(password)) issues.push('Uma letra minúscula')
  if (!NUMBER_RE.test(password)) issues.push('Um número')
  if (!SYMBOL_RE.test(password)) issues.push('Um símbolo')

  return issues
}

function randomInt(max: number): number {
  const array = new Uint32Array(1)
  window.crypto.getRandomValues(array)
  return array[0] % max
}

function randomChar(chars: string): string {
  return chars[randomInt(chars.length)]
}

function shuffle(chars: string[]): string[] {
  const result = [...chars]
  for (let i = result.length - 1; i > 0; i -= 1) {
    const j = randomInt(i + 1)
    ;[result[i], result[j]] = [result[j], result[i]]
  }
  return result
}

/**
 * Gera senha que sempre passa na política do backend (min 12, mixedCase,
 * número, símbolo). Usa `window.crypto.getRandomValues` — nunca
 * `Math.random`, que não é criptograficamente seguro.
 */
export function generateStrongPassword(length = 16): string {
  const size = Math.max(length, MIN_LENGTH)

  const required = [
    randomChar(UPPERCASE_CHARS),
    randomChar(LOWERCASE_CHARS),
    randomChar(NUMBER_CHARS),
    randomChar(SYMBOL_CHARS),
  ]

  const rest = Array.from({ length: size - required.length }, () => randomChar(ALL_CHARS))

  return shuffle([...required, ...rest]).join('')
}
