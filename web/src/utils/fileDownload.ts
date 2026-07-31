/**
 * Extrai o filename de um header `Content-Disposition` (`attachment; filename=...`
 * ou `filename*=UTF-8''...`) — usado por qualquer download binário (PDF/ZIP)
 * pra nomear o arquivo salvo igual ao que o servidor sugeriu, com fallback
 * quando o header não vem ou não bate no formato esperado.
 */
export function extractFilenameFromContentDisposition(
  contentDisposition: string | null | undefined,
  fallback: string,
): string {
  if (!contentDisposition) return fallback

  const utfMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)
  if (utfMatch?.[1]) return decodeURIComponent(utfMatch[1])

  const plainMatch = contentDisposition.match(/filename="?([^"]+)"?/i)
  return plainMatch?.[1] ?? fallback
}

/** Cria um link temporário e força o download de um `Blob` já em mãos — usado por qualquer fluxo que baixe um binário (PDF etc.) gerado no servidor. */
export function triggerBlobDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
