import type { ReactNode } from 'react'
import { useSectionReveal } from '../hooks/useSectionReveal'

interface RevealSectionProps {
  id?: string
  children: ReactNode
  className?: string
}

export function RevealSection({ id, children, className }: RevealSectionProps) {
  const { ref, visible } = useSectionReveal<HTMLDivElement>()

  return (
    <div
      id={id}
      ref={ref}
      className={['mk-section-fade', visible ? 'mk-section-fade-visible' : '', className].filter(Boolean).join(' ')}
    >
      {children}
    </div>
  )
}
