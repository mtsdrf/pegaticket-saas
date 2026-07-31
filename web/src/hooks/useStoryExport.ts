import { toBlob } from 'html-to-image'
import { useState } from 'react'
import { STORY_HEIGHT, STORY_WIDTH } from '../components/socialMedia/storyDimensions'

interface UseStoryExportResult {
  isExporting: boolean
  error: string | null
  captureBlob: (node: HTMLElement) => Promise<Blob | null>
}

/**
 * Captura o nó do story (`StoryCanvas`, sempre em tamanho real 1080×1920,
 * nunca o wrapper escalado de `StoryPreviewStage`) como PNG via
 * `html-to-image`. Aguarda `document.fonts.ready` antes de capturar — sem
 * isso a fonte Inter pode não estar 100% carregada e o texto cai pra fonte
 * padrão do sistema na imagem gerada. Qualquer imagem embutida no template
 * (foto de produto, logo da empresa) já deve chegar aqui como `data:` URL
 * (ver `utils/remoteImageToDataUrl.ts`) — nunca URL remota direta, que
 * deixaria o canvas "tainted" e faria `toBlob` falhar silenciosamente.
 */
export function useStoryExport(): UseStoryExportResult {
  const [isExporting, setIsExporting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function captureBlob(node: HTMLElement): Promise<Blob | null> {
    setIsExporting(true)
    setError(null)

    try {
      await document.fonts.ready

      const blob = await toBlob(node, {
        width: STORY_WIDTH,
        height: STORY_HEIGHT,
        pixelRatio: 1,
        cacheBust: true,
        backgroundColor: '#FFFFFF',
      })

      if (!blob) {
        setError('Não foi possível gerar a imagem agora. Tente novamente.')
        return null
      }

      return blob
    } catch {
      setError('Não foi possível gerar a imagem agora. Tente novamente.')
      return null
    } finally {
      setIsExporting(false)
    }
  }

  return { isExporting, error, captureBlob }
}
