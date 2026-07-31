import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControlLabel,
  Stack,
} from '@mui/material'
import { useState, type FormEvent } from 'react'
import { CardTokenFields } from './CardTokenFields'
import { buildCardTokenPayload, EMPTY_CARD_TOKEN_FORM, type CardTokenFormState } from './cardTokenFields.helpers'
import { useMercadoPagoSdk } from '../../hooks/useMercadoPagoSdk'
import * as subscriptionService from '../../services/subscriptionService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Subscription } from '../../types/subscription'

interface ChangeCardDialogProps {
  open: boolean
  onClose: () => void
  onSuccess: (subscription: Subscription) => void
}

/**
 * Cartão da cobrança recorrente (Preapproval). A tokenização acontece
 * inteiramente no navegador via MP.js (`useMercadoPagoSdk`) — o backend só
 * recebe `card_token`, nunca número/CVV crus (PCI). Ação financeira de alto
 * impacto: exige confirmação explícita antes de enviar.
 */
export function ChangeCardDialog({ open, onClose, onSuccess }: ChangeCardDialogProps) {
  const { mp, error: sdkError } = useMercadoPagoSdk(open)
  const [form, setForm] = useState<CardTokenFormState>(EMPTY_CARD_TOKEN_FORM)
  const [confirmed, setConfirmed] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function updateField(key: keyof CardTokenFormState, value: string) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function reset() {
    setForm(EMPTY_CARD_TOKEN_FORM)
    setConfirmed(false)
    setError(null)
  }

  function handleClose() {
    if (isSubmitting) return
    reset()
    onClose()
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    if (!mp) {
      setError(sdkError ?? 'Formulário de cartão ainda não está pronto. Aguarde um instante e tente novamente.')
      return
    }

    setIsSubmitting(true)
    try {
      const token = await mp.createCardToken(buildCardTokenPayload(form))

      const updated = await subscriptionService.updatePaymentMethod({ card_token: token.id })
      onSuccess(updated)
      reset()
      onClose()
    } catch (submitError) {
      if (submitError instanceof ApiRequestError) {
        setError(getApiErrorMessage(submitError, 'Não foi possível trocar o cartão agora.'))
      } else {
        // Erro do MP.js (createCardToken) — não é `ApiRequestError`, mensagem própria do SDK.
        setError('Não foi possível validar os dados do cartão. Confira o número, a validade e o CVV.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Trocar cartão da assinatura</DialogTitle>
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <DialogContent>
          <DialogContentText sx={{ color: 'var(--pt-text)', mb: 2 }}>
            Este cartão passa a ser usado nas próximas cobranças automáticas da assinatura. O número e o CVV são
            enviados direto ao Mercado Pago pelo navegador — o PegaTicket nunca tem acesso a esses dados.
          </DialogContentText>

          <Stack spacing={2}>
            <CardTokenFields form={form} onChange={updateField} disabled={isSubmitting} />

            <FormControlLabel
              control={
                <Checkbox
                  checked={confirmed}
                  onChange={(event) => setConfirmed(event.target.checked)}
                  disabled={isSubmitting}
                />
              }
              label="Confirmo a troca do cartão usado na cobrança automática desta assinatura."
            />

            {(error ?? sdkError) && <Alert severity="error">{error ?? sdkError}</Alert>}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={handleClose} disabled={isSubmitting} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
            Voltar
          </Button>
          <Button
            type="submit"
            disabled={isSubmitting || !confirmed || !mp}
            variant="contained"
            sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
          >
            {isSubmitting ? 'Trocando…' : 'Confirmar troca de cartão'}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  )
}
