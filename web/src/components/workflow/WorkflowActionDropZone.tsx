import { Box, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'

interface WorkflowActionDropZoneProps {
  title: string
  description: string
  accent: string
  icon?: ReactNode
  isActiveDrop?: boolean
  isDisabled?: boolean
  onDrop?: (event: React.DragEvent<HTMLDivElement>) => void
  onDragOver?: (event: React.DragEvent<HTMLDivElement>) => void
  onDragLeave?: (event: React.DragEvent<HTMLDivElement>) => void
}

export function WorkflowActionDropZone({
  title,
  description,
  accent,
  icon,
  isActiveDrop = false,
  isDisabled = false,
  onDrop,
  onDragOver,
  onDragLeave,
}: WorkflowActionDropZoneProps) {
  return (
    <Box
      onDrop={onDrop}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      sx={{
        ...SOFT_PANEL_SX,
        p: 1.5,
        border: `1px dashed ${isActiveDrop ? accent : 'var(--mk-border)'}`,
        bgcolor: isActiveDrop
          ? `color-mix(in srgb, ${accent} 10%, var(--mk-surface))`
          : 'color-mix(in srgb, var(--mk-surface) 94%, white)',
        color: isDisabled ? 'var(--mk-muted)' : accent,
        opacity: isDisabled ? 0.58 : 1,
        transition: 'border-color 140ms ease, background-color 140ms ease, opacity 140ms ease',
        minHeight: 122,
      }}
    >
      <Stack spacing={1} sx={{ height: '100%', justifyContent: 'center' }}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          {icon ? <Box sx={{ display: 'inline-flex', alignItems: 'center' }}>{icon}</Box> : null}
          <Typography sx={{ fontSize: 14, fontWeight: 800 }}>{title}</Typography>
        </Stack>
        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{description}</Typography>
        <Typography sx={{ fontSize: 12, fontWeight: 700 }}>
          {isActiveDrop ? 'Solte aqui para executar a ação.' : 'Arraste um card compatível para esta área.'}
        </Typography>
      </Stack>
    </Box>
  )
}
