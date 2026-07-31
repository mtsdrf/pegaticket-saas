import { Box, Button, Chip, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

interface WorkflowBoardColumnProps {
  title: string
  caption: string
  accent: string
  countLabel: string
  isActiveDrop?: boolean
  onDrop?: (event: React.DragEvent<HTMLDivElement>) => void
  onDragOver?: (event: React.DragEvent<HTMLDivElement>) => void
  onDragLeave?: (event: React.DragEvent<HTMLDivElement>) => void
  onOpenQueue?: () => void
  openQueueLabel?: string
  headerAction?: ReactNode
  emptyMessage: string
  children: ReactNode
}

export function WorkflowBoardColumn({
  title,
  caption,
  accent,
  countLabel,
  isActiveDrop = false,
  onDrop,
  onDragOver,
  onDragLeave,
  onOpenQueue,
  openQueueLabel = 'Ver fila completa',
  headerAction,
  emptyMessage,
  children,
}: WorkflowBoardColumnProps) {
  return (
    <Box
      onDrop={onDrop}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      sx={{
        ...ELEVATED_SURFACE_SX,
        p: 1.6,
        minHeight: 360,
        display: 'flex',
        flexDirection: 'column',
        gap: 1.25,
        border: isActiveDrop ? `1px solid ${accent}` : '1px solid var(--mk-border)',
        boxShadow: isActiveDrop ? `0 0 0 1px ${accent}` : ELEVATED_SURFACE_SX.boxShadow,
        bgcolor: isActiveDrop
          ? `color-mix(in srgb, ${accent} 10%, var(--mk-surface))`
          : 'color-mix(in srgb, var(--mk-surface) 96%, white)',
        transition: 'border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease',
      }}
    >
      <Stack spacing={0.6}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 15, fontWeight: 800, color: accent }}>{title}</Typography>
            <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{caption}</Typography>
          </Box>
          <Chip
            size="small"
            label={countLabel}
            sx={{
              fontWeight: 800,
              color: accent,
              bgcolor: `color-mix(in srgb, ${accent} 12%, transparent)`,
              flexShrink: 0,
            }}
          />
        </Stack>

        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 0.75 }}>
          {onOpenQueue ? (
            <Button size="small" variant="text" onClick={onOpenQueue} sx={{ px: 0 }}>
              {openQueueLabel}
            </Button>
          ) : null}
          {headerAction}
        </Stack>
      </Stack>

      <Stack spacing={1} sx={{ flex: 1 }}>
        {children}
      </Stack>

      <Box
        sx={{
          ...SOFT_PANEL_SX,
          mt: 'auto',
          border: isActiveDrop ? `1px dashed ${accent}` : '1px dashed color-mix(in srgb, var(--mk-border) 92%, transparent)',
          px: 1.1,
          py: 1,
          textAlign: 'center',
          color: isActiveDrop ? accent : 'var(--mk-muted)',
          fontSize: 12.5,
          fontWeight: isActiveDrop ? 700 : 500,
        }}
      >
        {isActiveDrop ? 'Solte o card aqui para mover esta etapa.' : emptyMessage}
      </Box>
    </Box>
  )
}
