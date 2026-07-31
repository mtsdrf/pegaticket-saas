import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'

interface WorkflowReasonDialogProps {
  open: boolean
  title: string
  description: string
  confirmLabel: string
  reasonLabel?: string
  reasonRequired?: boolean
  loading?: boolean
  onClose: () => void
  onConfirm: (reason: string) => void
}

export function WorkflowReasonDialog({
  open,
  title,
  description,
  confirmLabel,
  reasonLabel = 'Motivo',
  reasonRequired = true,
  loading = false,
  onClose,
  onConfirm,
}: WorkflowReasonDialogProps) {
  const [reason, setReason] = useState('')
  const [touched, setTouched] = useState(false)

  useEffect(() => {
    if (!open) {
      setReason('')
      setTouched(false)
    }
  }, [open])

  const trimmedReason = reason.trim()
  const hasError = reasonRequired && touched && trimmedReason.length === 0

  return (
    <Dialog open={open} onClose={() => !loading && onClose()} fullWidth maxWidth="sm">
      <DialogTitle>{title}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>{description}</Typography>
          <TextField
            autoFocus
            label={reasonLabel}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            onBlur={() => setTouched(true)}
            multiline
            minRows={3}
            required={reasonRequired}
            error={hasError}
            helperText={hasError ? 'Informe o motivo para continuar.' : 'Esse motivo ficará registrado no histórico operacional.'}
          />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={loading}>
          Voltar
        </Button>
        <Button
          variant="contained"
          disabled={loading || (reasonRequired && trimmedReason.length === 0)}
          onClick={() => {
            setTouched(true)
            if (reasonRequired && trimmedReason.length === 0) return
            onConfirm(trimmedReason)
          }}
        >
          {loading ? 'Processando...' : confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
