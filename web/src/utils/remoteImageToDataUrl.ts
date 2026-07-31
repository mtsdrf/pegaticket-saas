import { apiClient } from '../services/apiClient'

function blobToDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result as string)
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(blob)
  })
}

/**
 * Busca uma imagem remota (foto de produto, logo da empresa — servidas pelo
 * backend via `Storage::disk('public')`, potencialmente em outro host em
 * produção) e converte para `data:` URL antes de usar como `src` no story.
 * Evita canvas "tainted"/falha silenciosa do `html-to-image` com imagem
 * cross-origin sem CORS (ver `hooks/useStoryExport.ts`) — a imagem final no
 * template SEMPRE usa a data URL, nunca a URL remota direta, tanto no
 * preview quanto na captura, pra não ter dois caminhos de renderização.
 * `apiClient` é reaproveitado só pelo `baseURL`/interceptor de request já
 * configurado — `url` aqui é absoluta (retorno de `image_url`/`logo_url`),
 * e URL absoluta faz o axios ignorar o `baseURL`.
 * Retorna `null` em qualquer falha (rede, CORS, 404) — chamador trata como
 * "sem imagem", nunca deixa a falha travar a geração do restante do story.
 */
export async function remoteImageToDataUrl(url: string): Promise<string | null> {
  try {
    const response = await apiClient.get<Blob>(url, { responseType: 'blob' })
    return await blobToDataUrl(response.data)
  } catch {
    return null
  }
}

/** Conversão de `File` (upload local, ex. imagem do comunicado) — sem rede, mesmo mecanismo de leitura. */
export function fileToDataUrl(file: File): Promise<string> {
  return blobToDataUrl(file)
}
