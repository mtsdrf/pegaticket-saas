import ArrowBackRoundedIcon from '@mui/icons-material/ArrowBackRounded'
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  Skeleton,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { BillingPeriodOptionCards } from './BillingPeriodOptionCards'
import { CardTokenFields } from './CardTokenFields'
import { buildCardTokenPayload, EMPTY_CARD_TOKEN_FORM, type CardTokenFormState } from './cardTokenFields.helpers'
import { PlanPickerCards } from './PlanPickerCards'
import { useMercadoPagoSdk } from '../../hooks/useMercadoPagoSdk'
import * as subscriptionService from '../../services/subscriptionService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { PlanPricing, Subscription } from '../../types/subscription'
import type { BillingPeriod } from '../../utils/subscriptionPricing'

interface ChangePlanDialogProps {
  open: boolean
  currentPlanName: string
  onClose: () => void
  onSuccess: (subscription: Subscription) => void
}

/**
 * Upgrade/downgrade de plano de uma assinatura já ativa
 * (`GET /subscription/available-plans` + `POST /subscription/change-plan`).
 * Preço vem sempre pronto do backend (mesmo componente `BillingPeriodOptionCards`
 * já usado na contratação inicial). Ação financeira de alto impacto: exige
 * escolher plano, escolher período e marcar confirmação antes de enviar —
 * nunca 1-clique.
 */
export function ChangePlanDialog({ open, currentPlanName, onClose, onSuccess }: ChangePlanDialogProps) {
  const [plans, setPlans] = useState<PlanPricing[] | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [selectedPlan, setSelectedPlan] = useState<PlanPricing | null>(null)
  const [billingPeriod, setBillingPeriod] = useState<BillingPeriod>('monthly')
  const [confirmed, setConfirmed] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)

  // Passo extra só quando o novo plano é pago: backend recusa a troca sem
  // `card_token` (`SUBSCRIPTION_CARD_TOKEN_REQUIRED`) e o diálogo pede o
  // cartão sem perder o plano/período já escolhidos.
  const [needsCard, setNeedsCard] = useState(false)
  const [cardForm, setCardForm] = useState<CardTokenFormState>(EMPTY_CARD_TOKEN_FORM)
  const { mp, error: sdkError } = useMercadoPagoSdk(open && needsCard)

  useEffect(() => {
    if (!open) return
    setIsLoading(true)
    setLoadError(null)
    subscriptionService
      .getAvailablePlans()
      .then(setPlans)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os planos disponíveis agora.'))
      })
      .finally(() => setIsLoading(false))
  }, [open])

  function reset() {
    setPlans(null)
    setSelectedPlan(null)
    setBillingPeriod('monthly')
    setConfirmed(false)
    setSubmitError(null)
    setNeedsCard(false)
    setCardForm(EMPTY_CARD_TOKEN_FORM)
  }

  function handleClose() {
    if (isSubmitting) return
    reset()
    onClose()
  }

  function handleSelectPlan(plan: PlanPricing) {
    setSelectedPlan(plan)
    setBillingPeriod('monthly')
    setConfirmed(false)
    setSubmitError(null)
    setNeedsCard(false)
  }

  function updateCardField(key: keyof CardTokenFormState, value: string) {
    setCardForm((current) => ({ ...current, [key]: value }))
  }

  async function handleConfirm() {
    if (!selectedPlan) return
    setIsSubmitting(true)
    setSubmitError(null)
    try {
      const updated = await subscriptionService.changePlan({
        plan_id: selectedPlan.plan.uuid,
        billing_period: billingPeriod,
      })
      onSuccess(updated)
      reset()
      onClose()
    } catch (error) {
      // Novo plano é pago e ainda não há cartão informado: mostra o passo de
      // cartão em vez de erro, mantendo o plano/período já escolhidos.
      if (error instanceof ApiRequestError && error.code === 'SUBSCRIPTION_CARD_TOKEN_REQUIRED') {
        setNeedsCard(true)
      } else {
        setSubmitError(getApiErrorMessage(error, 'Não foi possível mudar de plano agora.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleConfirmWithCard() {
    if (!selectedPlan) return

    if (!mp) {
      setSubmitError(sdkError ?? 'Formulário de cartão ainda não está pronto. Aguarde um instante e tente novamente.')
      return
    }

    setIsSubmitting(true)
    setSubmitError(null)
    try {
      const token = await mp.createCardToken(buildCardTokenPayload(cardForm))
      const updated = await subscriptionService.changePlan({
        plan_id: selectedPlan.plan.uuid,
        billing_period: billingPeriod,
        card_token: token.id,
      })
      onSuccess(updated)
      reset()
      onClose()
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setSubmitError(getApiErrorMessage(error, 'Não foi possível confirmar o cartão agora. Tente novamente.'))
      } else {
        setSubmitError('Não foi possível validar os dados do cartão. Confira o número, a validade e o CVV.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
        {selectedPlan && (
          <IconButton
            size="small"
            onClick={() => (needsCard ? setNeedsCard(false) : setSelectedPlan(null))}
            disabled={isSubmitting}
            aria-label={needsCard ? 'Voltar para o período de cobrança' : 'Voltar para a lista de planos'}
          >
            <ArrowBackRoundedIcon fontSize="small" />
          </IconButton>
        )}
        {needsCard
          ? 'Cartão da assinatura'
          : selectedPlan
            ? `Mudar para o plano ${selectedPlan.plan.name}`
            : 'Mudar de plano'}
      </DialogTitle>

      <DialogContent>
        {!selectedPlan && (
          <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
            Plano atual: <strong>{currentPlanName}</strong>. Escolha o novo plano para ver o preço real de cada
            período antes de confirmar.
          </Typography>
        )}

        {isLoading && (
          <Stack spacing={1.5}>
            <Skeleton variant="rounded" height={96} />
            <Skeleton variant="rounded" height={96} />
          </Stack>
        )}

        {!isLoading && loadError && <Alert severity="error">{loadError}</Alert>}

        {!isLoading && !loadError && plans && plans.length === 0 && (
          <Alert severity="info" variant="outlined">
            Não há outro plano disponível para troca no momento.
          </Alert>
        )}

        {!isLoading && !loadError && plans && plans.length > 0 && !selectedPlan && (
          <PlanPickerCards plans={plans} onSelect={handleSelectPlan} />
        )}

        {selectedPlan && needsCard && (
          <Stack spacing={2}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>
              O plano <strong>{selectedPlan.plan.name}</strong> é pago. Informe o cartão que será usado nas
              cobranças automáticas da assinatura. O número e o CVV são enviados direto ao Mercado Pago pelo
              navegador — o PegaTicket nunca tem acesso a esses dados.
            </Typography>

            <CardTokenFields form={cardForm} onChange={updateCardField} disabled={isSubmitting} />

            {(submitError ?? sdkError) && <Alert severity="error">{submitError ?? sdkError}</Alert>}
          </Stack>
        )}

        {selectedPlan && !needsCard && (
          <Stack spacing={2}>
            <BillingPeriodOptionCards
              periods={selectedPlan.billing_periods}
              value={billingPeriod}
              onChange={setBillingPeriod}
            />

            <Alert severity="info" variant="outlined">
              A mudança vale a partir do ciclo atual: o novo plano passa a valer imediatamente e o novo valor é
              cobrado a partir da próxima cobrança, sem cobrança proporcional do período já em curso.
            </Alert>

            <FormControlLabel
              control={
                <Checkbox
                  checked={confirmed}
                  onChange={(event) => setConfirmed(event.target.checked)}
                  disabled={isSubmitting}
                />
              }
              label={`Confirmo a troca do plano ${currentPlanName} para o plano ${selectedPlan.plan.name}.`}
            />

            {submitError && <Alert severity="error">{submitError}</Alert>}
          </Stack>
        )}
      </DialogContent>

      <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
        <Button onClick={handleClose} disabled={isSubmitting} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
          {selectedPlan ? 'Cancelar' : 'Fechar'}
        </Button>
        {selectedPlan && needsCard && (
          <Button
            onClick={() => void handleConfirmWithCard()}
            disabled={isSubmitting || !mp}
            variant="contained"
            sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
          >
            {isSubmitting ? 'Confirmando…' : 'Confirmar cartão e mudar de plano'}
          </Button>
        )}
        {selectedPlan && !needsCard && (
          <Button
            onClick={() => void handleConfirm()}
            disabled={isSubmitting || !confirmed}
            variant="contained"
            sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
          >
            {isSubmitting ? 'Confirmando…' : 'Confirmar mudança de plano'}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  )
}
