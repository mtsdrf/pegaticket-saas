import { useEffect, useRef, useState } from 'react'

/**
 * Revelação de seção ao entrar no viewport — `IntersectionObserver` simples,
 * sem lib de animação extra. Respeita `prefers-reduced-motion` via CSS
 * (`.mk-section-fade`, ver index.css), então aqui só controla a classe.
 */
export function useSectionReveal<T extends HTMLElement>() {
  const ref = useRef<T | null>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const node = ref.current
    if (!node) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true)
          observer.disconnect()
        }
      },
      { threshold: 0.15 },
    )

    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  return { ref, visible }
}
