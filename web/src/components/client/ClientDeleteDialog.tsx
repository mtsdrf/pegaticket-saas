import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@mui/material'
import type { Client } from '../../types/client'

interface ClientDeleteDialogProps {
  client: Client | null
  isDeleting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

export function ClientDeleteDialog({ client, isDeleting, error, onCancel, onConfirm }: ClientDeleteDialogProps) {
  return (
    <Dialog open={client !== null} onClose={isDeleting ? undefined : onCancel} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Excluir cliente</DialogTitle>
      <DialogContent>
        <DialogContentText sx={{ color: 'var(--pt-text)' }}>
          Tem certeza que deseja excluir <strong>{client?.name}</strong>? Essa ação não pode ser
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
