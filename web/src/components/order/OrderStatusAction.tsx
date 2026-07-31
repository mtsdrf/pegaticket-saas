import UndoOutlinedIcon from '@mui/icons-material/UndoOutlined'
import { Button, Chip, CircularProgress, Stack } from '@mui/material'
import type { ReactElement, ReactNode } from 'react'

export interface OrderStatusActionProps {
  isDone: boolean
  isProcessing: boolean
  doneLabel: string
  doneIcon: ReactElement
  actionLabel: string
  actionIcon: ReactNode
  onMark: () => void
  onUndo: () => void
}

/**
 * Controle reversível de status (entregue/pago): botão de ação quando
 * pendente, chip + "Desfazer" quando concluído. Extraído de
 * `RouteResultStopCard.tsx` (era `StatusAction` local) para ser reaproveitado
 * também em `ClientOrdersPage.tsx` — comportamento e visual inalterados.
 */
export function OrderStatusAction({
  isDone,
  isProcessing,
  doneLabel,
  doneIcon,
  actionLabel,
  actionIcon,
  onMark,
  onUndo,
}: OrderStatusActionProps) {
  if (!isDone) {
    return (
      <Button
        size="small"
        variant="outlined"
        startIcon={isProcessing ? <CircularProgress size={14} /> : actionIcon}
        disabled={isProcessing}
        onClick={onMark}
        fullWidth
        sx={{ minHeight: 44, minWidth: 44, width: { xs: '100%', sm: 'auto' } }}
      >
        {actionLabel}
      </Button>
    )
  }

  return (
    <Stack
      direction="row"
      spacing={0.5}
      sx={{ alignItems: 'center', justifyContent: 'center', width: { xs: '100%', sm: 'auto' } }}
    >
      <Chip
        size="small"
        icon={doneIcon}
        label={doneLabel}
        sx={{
          flex: 1,
          minWidth: 0,
          height: 'auto',
          minHeight: 44,
          fontWeight: 600,
          bgcolor: 'color-mix(in srgb, var(--pt-success) 14%, transparent)',
          color: 'var(--pt-success)',
          '& .MuiChip-icon': { color: 'inherit' },
        }}
      />
      <Button
        size="small"
        variant="text"
        color="inherit"
        startIcon={isProcessing ? <CircularProgress size={14} /> : <UndoOutlinedIcon fontSize="small" />}
        disabled={isProcessing}
        onClick={onUndo}
        sx={{ flex: 1, minWidth: 0, minHeight: 44, color: 'var(--pt-muted)' }}
      >
        Desfazer
      </Button>
    </Stack>
  )
}
