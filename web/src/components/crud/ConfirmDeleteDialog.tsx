import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@mui/material'

interface ConfirmDeleteDialogProps {
  open: boolean
  title: string
  itemLabel: string | null
  isDeleting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

export function ConfirmDeleteDialog({
  open,
  title,
  itemLabel,
  isDeleting,
  error,
  onCancel,
  onConfirm,
}: ConfirmDeleteDialogProps) {
  return (
    <Dialog open={open} onClose={isDeleting ? undefined : onCancel} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>{title}</DialogTitle>
      <DialogContent>
        <DialogContentText sx={{ color: 'var(--mk-text)' }}>
          Tem certeza que deseja excluir <strong>{itemLabel}</strong>? Essa ação não pode ser
          desfeita.
        </DialogContentText>
        {error && (
          <Alert severity="error" sx={{ mt: 2 }}>
            {error}
          </Alert>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
        <Button onClick={onCancel} disabled={isDeleting} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
          Cancelar
        </Button>
        <Button
          onClick={onConfirm}
          disabled={isDeleting}
          color="error"
          variant="contained"
          sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
        >
          {isDeleting ? 'Excluindo…' : 'Excluir'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
