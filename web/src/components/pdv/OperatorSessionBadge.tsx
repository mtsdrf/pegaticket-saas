import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import {
  Alert,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Stack,
  TextField,
} from '@mui/material'
import { useState, type FormEvent } from 'react'
import * as pdvService from '../../services/pdvService'
import { useOperatorSession } from '../../hooks/useOperatorSession'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

/**
 * Chip "Quem está operando?" / "Operando: {nome}" (roadmap A4, item 15) —
 * usado no topo da venda de PDV e no mapa de mesas do Balcão. Ao clicar,
 * pede o PIN e resolve o operador via `POST /pdv/operator-session`, guardado
 * em `useOperatorSession` (memória da aba, não é re-autenticação). Sem PIN
 * cadastrado ainda, o operador simplesmente segue sem identificação — nunca
 * bloqueia a venda (é uma camada opcional de rastreio, não um gate).
 */
export function OperatorSessionBadge() {
  const { operator, setOperator } = useOperatorSession()
  const [isDialogOpen, setDialogOpen] = useState(false)

  return (
    <>
      <Chip
        icon={<PersonOutlineOutlinedIcon />}
        label={operator ? `Operando: ${operator.name}` : 'Quem está operando?'}
        onClick={() => setDialogOpen(true)}
        variant={operator ? 'filled' : 'outlined'}
        color={operator ? 'primary' : 'default'}
        sx={{ fontWeight: 600, cursor: 'pointer', minHeight: 36 }}
      />

      {isDialogOpen ? (
        <OperatorPinDialog
          onClose={() => setDialogOpen(false)}
          onResolved={(resolved) => {
            setOperator(resolved)
            setDialogOpen(false)
          }}
        />
      ) : null}
    </>
  )
}

interface OperatorPinDialogProps {
  onClose: () => void
  onResolved: (operator: { uuid: string; name: string }) => void
}

function OperatorPinDialog({ onClose, onResolved }: OperatorPinDialogProps) {
  const [pin, setPin] = useState('')
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      const operator = await pdvService.resolveOperator({ pin })
      onResolved(operator)
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'INVALID_PIN') {
        setError('PIN inválido. Confira os dígitos e tente novamente.')
      } else {
        setError(getApiErrorMessage(err, 'Não foi possível identificar o operador agora.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Quem está operando?</DialogTitle>
      <Stack component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <DialogContent dividers>
          <DialogContentText sx={{ mb: 2 }}>
            Digite o PIN do operador. Ele será anexado às vendas seguintes até trocar de operador.
          </DialogContentText>
          <TextField
            autoFocus
            fullWidth
            label="PIN"
            value={pin}
            onChange={(event) => setPin(event.target.value.replace(/\D/g, '').slice(0, 6))}
            slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 6, style: { letterSpacing: 4, fontSize: 20, textAlign: 'center' } } }}
            required
          />
          {error ? (
            <Alert severity="error" sx={{ mt: 2 }}>
              {error}
            </Alert>
          ) : null}
        </DialogContent>
        <DialogActions sx={{ px: 3, py: 2 }}>
          <Button onClick={onClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || pin.length < 4}>
            {isSubmitting ? 'Verificando…' : 'Confirmar'}
          </Button>
        </DialogActions>
      </Stack>
    </Dialog>
  )
}
