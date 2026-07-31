import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@mui/material'
import { useState, type FormEvent } from 'react'
import { CardTokenFields } from './CardTokenFields'
import { buildCardTokenPayload, EMPTY_CARD_TOKEN_FORM, type CardTokenFormState } from './cardTokenFields.helpers'
import { useMercadoPagoSdk } from '../../hooks/useMercadoPagoSdk'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Subscription } from '../../types/subscription'

interface SubscriptionCardTokenDialogProps {
  open: boolean
  /** Nome do plano escolhido, só para dar contexto na mensagem do diálogo. */
  planName: string
  onClose: () => void
  onSuccess: (subscription: Subscription) => void
  /**
   * Recebe o token já gerado pelo MP.js e faz a chamada real (criação ou
   * troca de plano) com `card_token`. Se a API recusar (cartão negado pelo
   * Mercado Pago, token expirado etc.), deve lançar `ApiRequestError` — o
   * diálogo mantém os dados digitados para o usuário corrigir e tentar de
   * novo, sem perder a seleção de plano/período já feita na tela de trás.
   */
  onSubmitToken: (cardToken: string) => Promise<Subscription>
}

/**
 * Passo de cartão da contratação/troca de plano quando o plano escolhido é
 * pago (backend responde 422 `SUBSCRIPTION_CARD_TOKEN_REQUIRED` sem
 * `card_token`). Mesmo padrão do `ChangeCardDialog`: tokenização 100%
 * embutida via MP.js, número/CVV nunca chegam ao backend Maskats — só o
 * token. Substitui o antigo redirecionamento a checkout do Mercado Pago.
 */
export function SubscriptionCardTokenDialog({
  open,
  planName,
  onClose,
  onSuccess,
  onSubmitToken,
}: SubscriptionCardTokenDialogProps) {
  const { mp, error: sdkError } = useMercadoPagoSdk(open)
  const [form, setForm] = useState<CardTokenFormState>(EMPTY_CARD_TOKEN_FORM)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function updateField(key: keyof CardTokenFormState, value: string) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function reset() {
    setForm(EMPTY_CARD_TOKEN_FORM)
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
      const subscription = await onSubmitToken(token.id)
      onSuccess(subscription)
      reset()
      onClose()
    } catch (submitError) {
      if (submitError instanceof ApiRequestError) {
        setError(getApiErrorMessage(submitError, 'Não foi possível confirmar o cartão agora. Tente novamente.'))
      } else {
        setError('Não foi possível validar os dados do cartão. Confira o número, a validade e o CVV.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Cartão da assinatura</DialogTitle>
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <DialogContent>
          <DialogContentText sx={{ color: 'var(--mk-text)', mb: 2 }}>
            O plano <strong>{planName}</strong> é pago. Informe o cartão que será usado nas cobranças automáticas
            da assinatura. O número e o CVV são enviados direto ao Mercado Pago pelo navegador — o Maskats nunca
            tem acesso a esses dados.
          </DialogContentText>

          <CardTokenFields form={form} onChange={updateField} disabled={isSubmitting} />

          {(error ?? sdkError) && (
            <Alert severity="error" sx={{ mt: 2 }}>
              {error ?? sdkError}
            </Alert>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={handleClose} disabled={isSubmitting} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
            Voltar
          </Button>
          <Button
            type="submit"
            disabled={isSubmitting || !mp}
            variant="contained"
            sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
          >
            {isSubmitting ? 'Confirmando…' : 'Confirmar cartão e assinar'}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  )
}
