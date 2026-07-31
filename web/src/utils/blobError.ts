import type { ApiError } from '../types/api'

/**
 * Requisições com `responseType: 'blob'` (download de PDF binário) fazem o
 * axios entregar o corpo de erro (403/422/500) também como `Blob`, mesmo a
 * API respondendo `application/json` nesses casos — não é JSON já parseado
 * como em qualquer outra chamada. Sem essa conversão, o interceptor central
 * de `apiClient.ts` monta um `ApiRequestError` com `message`/`code`/`errors`
 * vazios pra qualquer falha de download binário (`getApiErrorMessage` cai
 * sempre no fallback genérico). Usado só pelo interceptor de resposta —
 * mantém o desembrulhamento de erro num único lugar central, como o resto
 * do `apiClient`.
 */
export async function parseBlobErrorPayload(data: unknown): Promise<ApiError | null> {
  if (typeof Blob === 'undefined' || !(data instanceof Blob)) return null

  try {
    const text = await data.text()
    return JSON.parse(text) as ApiError
  } catch {
    return null
  }
}
