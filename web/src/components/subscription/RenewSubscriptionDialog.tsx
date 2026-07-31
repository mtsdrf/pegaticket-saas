import { Alert, Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle } from '@mui/material'

interface RenewSubscriptionDialogProps {
  open: boolean
  isSubmitting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

/** Reverte um cancelamento agendado (`cancel_scheduled` -> `active`) — a assinatura volta a renovar normalmente. */
export function RenewSubscriptionDialog({ open, isSubmitting, error, onCancel, onConfirm }: RenewSubscriptionDialogProps) {
  return (
    <Dialog open={open} onClose={isSubmitting ? undefined : onCancel} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Renovar assinatura</DialogTitle>
      <DialogContent>
        <DialogContentText sx={{ color: 'var(--mk-text)' }}>
          O cancelamento agendado será desfeito e a assinatura volta a renovar normalmente ao fim do ciclo atual,
          com cobrança automática mantida.
        </DialogContentText>

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
          onClick={onConfirm}
          disabled={isSubmitting}
          variant="contained"
          sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
        >
          {isSubmitting ? 'Renovando…' : 'Confirmar renovação'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
