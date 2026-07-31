export interface ApiSuccess<T> {
  success: true
  message: string
  data: T
  meta: Record<string, unknown>
}

export interface ApiError {
  success: false
  message: string
  code: string
  errors: Record<string, string[]>
  meta: Record<string, unknown>
}

export class ApiRequestError extends Error {
  code: string
  errors: Record<string, string[]>
  status: number

  constructor(payload: ApiError, status: number) {
    super(payload.message)
    this.code = payload.code
    this.errors = payload.errors
    this.status = status
  }
}

export const PLAN_UPGRADE_REQUIRED_CODE = 'PLAN_UPGRADE_REQUIRED'

/** Erro de gating de plano (403 com `code: PLAN_UPGRADE_REQUIRED`) — telas gated devem mostrar `PlanUpgradeNotice` em vez de erro genérico. */
export function isPlanUpgradeRequired(error: unknown): boolean {
  return error instanceof ApiRequestError && error.code === PLAN_UPGRADE_REQUIRED_CODE
}

export const SUBSCRIPTION_SUSPENDED_CODE = 'SUBSCRIPTION_SUSPENDED'

/** Assinatura suspensa/cancelada bloqueia acesso operacional (403) — só a tela de assinatura da empresa continua acessível. */
export function isSubscriptionSuspended(error: unknown): boolean {
  return error instanceof ApiRequestError && error.code === SUBSCRIPTION_SUSPENDED_CODE
}

function normalizeApiMessage(message: string | undefined, code: string | undefined, status: number | undefined): string | null {
  const trimmed = message?.trim()
  const lower = trimmed?.toLowerCase()

  if (code === 'PLAN_UPGRADE_REQUIRED') {
    return 'Seu plano atual não libera esta funcionalidade. Atualize o plano da empresa para continuar.'
  }

  if (code === 'SUBSCRIPTION_SUSPENDED') {
    return 'A assinatura da empresa está suspensa. Acesse Configurações > Assinatura da empresa para regularizar e liberar o acesso.'
  }

  if (
    code === 'FORBIDDEN' ||
    status === 403 ||
    trimmed === 'Access denied.' ||
    trimmed === 'Forbidden.' ||
    trimmed === 'Forbidden'
  ) {
    return 'Acesso negado. Você não tem permissão para realizar esta ação.'
  }

  if (
    code === 'INVALID_CREDENTIALS' ||
    trimmed === 'Invalid credentials.' ||
    trimmed === 'Credenciais inválidas.'
  ) {
    return 'E-mail ou senha inválidos. Revise os dados e tente novamente.'
  }

  if (
    trimmed === 'Invalid or expired refresh token.' ||
    trimmed === 'Refresh token inválido ou expirado.'
  ) {
    return 'Sua sessão não pôde ser renovada. Faça login novamente para continuar.'
  }

  if (
    trimmed === 'Token expired.' ||
    trimmed === 'Invalid token.' ||
    trimmed === 'Token revoked.' ||
    trimmed === 'Token not provided.' ||
    trimmed === 'Token not provided' ||
    trimmed === 'Unauthenticated.' ||
    trimmed === 'Unauthorized.' ||
    trimmed === 'Unauthorized'
  ) {
    return 'Sua sessão expirou ou é inválida. Faça login novamente para continuar.'
  }

  if (trimmed === 'Internal server error.' || trimmed === 'Internal server error') {
    return 'O servidor encontrou um erro interno ao processar sua solicitação. Tente novamente em instantes.'
  }

  if (trimmed === 'Resource not found.' || trimmed === 'Resource not found') {
    return 'O registro solicitado não foi encontrado.'
  }

  if (trimmed === 'Bad request.' || trimmed === 'Invalid payload.') {
    return 'Não foi possível processar sua solicitação com os dados enviados.'
  }

  if (lower?.includes('does not belong to this tenant')) {
    return 'Você não faz parte desta empresa.'
  }

  if (lower?.includes('tenant')) {
    return trimmed
      ?.replaceAll(/tenant users/gi, 'usuários da empresa')
      .replaceAll(/tenant user/gi, 'usuário da empresa')
      .replaceAll(/tenant roles/gi, 'perfis da empresa')
      .replaceAll(/tenant role/gi, 'perfil da empresa')
      .replaceAll(/tenants/gi, 'empresas')
      .replaceAll(/tenant/gi, 'empresa')
      .replaceAll(/Tenant/gi, 'Empresa') ?? null
  }

  if (status === 401) {
    return 'Sua sessão expirou ou você não tem autorização para continuar. Faça login novamente.'
  }

  if (trimmed) {
    return trimmed
  }

  if (status && status >= 500) {
    return 'O servidor encontrou um erro ao processar sua solicitação. Tente novamente em instantes.'
  }

  return null
}

/**
 * Só `ApiRequestError` (corpo JSON real da API, sempre traduzido via
 * `__('messages.*')` no backend) pode "vazar" sua própria mensagem através de
 * `normalizeApiMessage`. Qualquer outro erro (network error puro, CORS,
 * timeout, ou um `Error` genérico do axios como "Request failed with status
 * code 401") usa sempre o `fallback` passado pelo chamador — nunca `error.message`
 * bruto, que é texto técnico em inglês gerado pelo axios/navegador, não uma
 * mensagem pensada pro usuário final.
 */
export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiRequestError) {
    return normalizeApiMessage(error.message, error.code, error.status) ?? fallback
  }

  return fallback
}
