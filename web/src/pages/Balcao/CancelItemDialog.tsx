import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useState } from 'react'
import * as balcaoService from '../../services/balcaoService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { ComandaItem } from '../../types/balcao'

interface CancelItemDialogProps {
  comandaUuid: string
  item: ComandaItem
  onClose: () => void
  onCancelled: () => void
}

/** Cancelamento de item da comanda — motivo obrigatório (backend rejeita vazio). */
export function CancelItemDialog({ comandaUuid, item, onClose, onCancelled }: CancelItemDialogProps) {
  const [reason, setReason] = useState('')
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleConfirm() {
    if (!reason.trim()) {
      setError('Informe o motivo do cancelamento.')
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      await balcaoService.updatePrepStatus(comandaUuid, item.uuid, {
        prep_status: 'cancelled',
        cancelled_reason: reason.trim(),
      })
      onCancelled()
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'COMANDA_ERROR') {
        setError(err.message)
      } else {
        setError(getApiErrorMessage(err, 'Não foi possível cancelar o item.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontWeight: 700 }}>Cancelar item</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {item.qty}× {item.product?.name ?? 'item'}. Se já foi enviado à estação, o estoque é devolvido.
          </Typography>
          <TextField
            autoFocus
            fullWidth
            label="Motivo"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            multiline
            minRows={2}
            slotProps={{ htmlInput: { maxLength: 1000 } }}
          />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Voltar
        </Button>
        <Button variant="contained" color="error" onClick={handleConfirm} disabled={isSubmitting}>
          {isSubmitting ? 'Cancelando…' : 'Cancelar item'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
