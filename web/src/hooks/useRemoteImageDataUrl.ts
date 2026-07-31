import { useEffect, useState } from 'react'
import { remoteImageToDataUrl } from '../utils/remoteImageToDataUrl'

/**
 * Converte uma URL remota (foto de produto, logo da empresa) pra `data:` URL
 * assim que ela muda — usado pela tela "Redes Sociais" pra sempre ter a
 * imagem pronta como data URL antes de renderizar/capturar o story (ver
 * `utils/remoteImageToDataUrl.ts`). `url` nulo/vazio limpa o resultado sem
 * chamar a API.
 */
export function useRemoteImageDataUrl(url: string | null | undefined) {
  const [dataUrl, setDataUrl] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    if (!url) {
      setDataUrl(null)
      setIsLoading(false)
      return
    }

    let cancelled = false
    setIsLoading(true)

    remoteImageToDataUrl(url).then((result) => {
      if (cancelled) return
      setDataUrl(result)
      setIsLoading(false)
    })

    return () => {
      cancelled = true
    }
  }, [url])

  return { dataUrl, isLoading }
}
