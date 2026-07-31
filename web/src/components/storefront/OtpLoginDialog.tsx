import CloseIcon from '@mui/icons-material/Close'
import { Dialog, DialogContent, IconButton } from '@mui/material'
import { OtpIdentifyForm } from './OtpIdentifyForm'

/**
 * Wrapper de `OtpIdentifyForm` em modal — usado quando uma ação do catálogo
 * (favoritar produto, Delivery Fase 4) exige identidade do `FinalCustomer`
 * fora do fluxo de checkout. Mesmo componente de OTP, sem duplicar lógica.
 */
export function OtpLoginDialog({
  open,
  onClose,
  onAuthenticated,
}: {
  open: boolean
  onClose: () => void
  onAuthenticated: () => void
}) {
  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="xs">
      <IconButton
        onClick={onClose}
        aria-label="Fechar"
        size="small"
        sx={{ position: 'absolute', top: 8, right: 8, color: 'var(--pt-muted)' }}
      >
        <CloseIcon fontSize="small" />
      </IconButton>
      <DialogContent sx={{ pt: 4 }}>
        <OtpIdentifyForm onAuthenticated={onAuthenticated} />
      </DialogContent>
    </Dialog>
  )
}
