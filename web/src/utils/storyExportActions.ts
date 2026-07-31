const STORY_FILENAME = 'story-maskats.png'

/** Download via link `<a download>` a partir do blob — sempre disponível, mesmo sem suporte a Web Share. */
export function downloadStoryBlob(blob: Blob, filename = STORY_FILENAME): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/** Feature detection da Web Share API nível 2 (`files`) — testa com um arquivo mínimo, sem depender do blob real já existir. */
export function canShareStoryFile(): boolean {
  try {
    const testFile = new File([''], STORY_FILENAME, { type: 'image/png' })
    return Boolean(navigator.canShare?.({ files: [testFile] }))
  } catch {
    return false
  }
}

/**
 * Abre o share sheet nativo do dispositivo. Cancelamento do usuário rejeita
 * a Promise (`AbortError`) — não é erro de aplicação, o chamador não deve
 * tratar como falha de geração de imagem.
 */
export async function shareStoryBlob(blob: Blob, filename = STORY_FILENAME): Promise<void> {
  const file = new File([blob], filename, { type: 'image/png' })
  await navigator.share({ files: [file], title: 'Maskats' })
}
