import { useCallback, useEffect, useState } from 'react'
import type { Operator } from '../types/pdv'

/**
 * Operador resolvido via PIN (`POST /pdv/operator-session`, roadmap A4 item
 * 15) — guardado só em memória da aba (`sessionStorage`, nunca `localStorage`:
 * some ao fechar a aba, igual à "sessão de venda" pedida), compartilhado
 * entre PDV e Balcão via um pubsub simples de `window` (evita subir um
 * Context Provider por cima das duas árvores de rota, que não têm um
 * ancestral comum além do `AppLayout`). `sessionStorage` é por aba (não por
 * origem como `localStorage`), então trocar de tenant numa mesma aba também
 * limpa o operador — ver `clearOperatorSession` chamado no logout/troca de
 * tenant se necessário no futuro.
 */
const STORAGE_KEY = 'maskats.pdv.operator_session'
const EVENT_NAME = 'maskats:operator-session-changed'

function readStoredOperator(): Operator | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as Operator) : null
  } catch {
    return null
  }
}

function writeStoredOperator(operator: Operator | null): void {
  try {
    if (operator) {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(operator))
    } else {
      sessionStorage.removeItem(STORAGE_KEY)
    }
  } catch {
    // sessionStorage indisponível (modo privado restrito) — operador só
    // funciona em memória durante o ciclo de vida do componente, sem persistir.
  }
  window.dispatchEvent(new CustomEvent(EVENT_NAME))
}

export interface OperatorSessionResult {
  operator: Operator | null
  setOperator: (operator: Operator) => void
  clearOperator: () => void
}

/** Compartilha o mesmo operador resolvido entre todos os componentes montados (PDV, Balcão) sem precisar de um Context Provider comum. */
export function useOperatorSession(): OperatorSessionResult {
  const [operator, setOperatorState] = useState<Operator | null>(() => readStoredOperator())

  useEffect(() => {
    function handleChange() {
      setOperatorState(readStoredOperator())
    }
    window.addEventListener(EVENT_NAME, handleChange)
    return () => window.removeEventListener(EVENT_NAME, handleChange)
  }, [])

  const setOperator = useCallback((next: Operator) => {
    writeStoredOperator(next)
  }, [])

  const clearOperator = useCallback(() => {
    writeStoredOperator(null)
  }, [])

  return { operator, setOperator, clearOperator }
}
