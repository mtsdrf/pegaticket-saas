import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  FormControlLabel,
  Radio,
  RadioGroup,
  TextField,
} from '@mui/material'
import { useState } from 'react'

interface CancelSubscriptionDialogProps {
  open: boolean
  isSubmitting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: (immediately: boolean, reason: string) => void
}

/**
 * O endpoint aceita cancelamento imediato (`immediately: true`) ou ao fim do
 * ciclo (`false`) — a escolha é exposta aqui. `reason` opcional (max 500 no
 * backend).
 */
export function CancelSubscriptionDialog({
  open,
  isSubmitting,
  error,
  onCancel,
  onConfirm,
}: CancelSubscriptionDialogProps) {
  const [mode, setMode] = useState<'end_of_cycle' | 'immediately'>('end_of_cycle')
  const [reason, setReason] = useState('')

  return (
    <Dialog open={open} onClose={isSubmitting ? undefined : onCancel} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Cancelar assinatura</DialogTitle>
      <DialogContent>
        <DialogContentText sx={{ color: 'var(--pt-text)', mb: 2 }}>
          Escolha quando o cancelamento deve valer. Esta ação encerra o acesso pago da empresa.
        </DialogContentText>

        <FormControl sx={{ width: '100%' }}>
          <RadioGroup value={mode} onChange={(event) => setMode(event.target.value as typeof mode)}>
            <FormControlLabel
              value="end_of_cycle"
              control={<Radio />}
              label="Ao fim do ciclo atual (continua com acesso até lá)"
              disabled={isSubmitting}
            />
            <FormControlLabel
              value="immediately"
              control={<Radio />}
              label="Imediatamente (encerra o acesso agora)"
              disabled={isSubmitting}
            />
          </RadioGroup>
        </FormControl>

        <TextField
          label="Motivo (opcional)"
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          multiline
          minRows={2}
          fullWidth
          disabled={isSubmitting}
          sx={{ mt: 2 }}
          slotProps={{ htmlInput: { maxLength: 500 } }}
        />

        {error && (
          <Alert severity="error" sx={{ mt: 2 }}>
            {error}
          </Alert>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
        <Button onClick={onCancel} disabled={isSubmitting} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
          Voltar
        </Button>
        <Button
          onClick={() => onConfirm(mode === 'immediately', reason.trim())}
          disabled={isSubmitting}
          color="error"
          variant="contained"
          sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
        >
          {isSubmitting ? 'Cancelando…' : 'Confirmar cancelamento'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
