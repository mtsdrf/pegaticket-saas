import { Box } from '@mui/material'
import { useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { STORY_HEIGHT, STORY_WIDTH } from './storyDimensions'

/**
 * Envolve o `StoryCanvas` (tamanho real 1080×1920) num wrapper `transform:
 * scale(...)` calculado pela largura disponível — pra caber na tela em
 * mobile sem escalar o CSS do template em si (a captura em `useStoryExport`
 * continua apontando pro nó real, não escalado). O container externo já
 * nasce na altura final escalada, então nunca sobra espaço em branco abaixo.
 */
interface StoryPreviewStageProps {
  children: ReactNode
  /** Largura máxima do preview escalado — default é o tamanho de preview real da tela; miniaturas de seleção de template usam um valor bem menor. */
  maxWidth?: number
}

export function StoryPreviewStage({ children, maxWidth = 420 }: StoryPreviewStageProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(0.3)

  useLayoutEffect(() => {
    const element = containerRef.current
    if (!element) return

    const updateScale = () => {
      const width = element.clientWidth
      if (width > 0) setScale(Math.min(1, width / STORY_WIDTH))
    }

    updateScale()
    const observer = new ResizeObserver(updateScale)
    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  return (
    <Box
      ref={containerRef}
      sx={{
        width: '100%',
        maxWidth,
        mx: 'auto',
        height: STORY_HEIGHT * scale,
        overflow: 'hidden',
        ...ELEVATED_SURFACE_SX,
        position: 'relative',
      }}
    >
      <Box sx={{ position: 'absolute', top: 0, left: 0, transform: `scale(${scale})`, transformOrigin: 'top left' }}>
        {children}
      </Box>
    </Box>
  )
}
