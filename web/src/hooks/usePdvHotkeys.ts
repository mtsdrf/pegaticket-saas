import { useEffect } from 'react'

/**
 * Atalhos de teclado do PDV (tela balcão teclado-first). `keydown` global,
 * sem lib nova. Atalhos:
 * - `F2`  → foca o campo de busca (funciona mesmo com foco num input).
 * - `F4`  → abre o modal de finalização de venda.
 * - `Delete` / `Backspace` → remove o item selecionado do carrinho, MAS só
 *   quando o foco NÃO está num campo editável (senão apagaria texto digitado).
 *
 * Handlers opcionais e estáveis — passe callbacks memoizados (`useCallback`)
 * no chamador para evitar re-registrar o listener a cada render.
 */
export interface PdvHotkeyHandlers {
  onFocusSearch?: () => void
  onFinalize?: () => void
  onRemoveSelected?: () => void
}

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

export function usePdvHotkeys({ onFocusSearch, onFinalize, onRemoveSelected }: PdvHotkeyHandlers): void {
  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      switch (event.key) {
        case 'F2':
          if (onFocusSearch) {
            event.preventDefault()
            onFocusSearch()
          }
          break
        case 'F4':
          if (onFinalize) {
            event.preventDefault()
            onFinalize()
          }
          break
        case 'Delete':
        case 'Backspace':
          if (onRemoveSelected && !isEditableTarget(event.target)) {
            event.preventDefault()
            onRemoveSelected()
          }
          break
        default:
          break
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [onFocusSearch, onFinalize, onRemoveSelected])
}
