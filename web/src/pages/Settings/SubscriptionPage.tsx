import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import ArrowBackRoundedIcon from '@mui/icons-material/ArrowBackRounded'
import AutorenewOutlinedIcon from '@mui/icons-material/AutorenewOutlined'
import CalendarMonthOutlinedIcon from '@mui/icons-material/CalendarMonthOutlined'
import CheckRoundedIcon from '@mui/icons-material/CheckRounded'
import CreditCardOutlinedIcon from '@mui/icons-material/CreditCardOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Divider,
  FormControlLabel,
  IconButton,
  Paper,
  Skeleton,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { PageHeader } from '../../components/layout/PageHeader'
import { BillingPeriodOptionCards } from '../../components/subscription/BillingPeriodOptionCards'
import { CancelSubscriptionDialog } from '../../components/subscription/CancelSubscriptionDialog'
import { ChangeCardDialog } from '../../components/subscription/ChangeCardDialog'
import { ChangePlanDialog } from '../../components/subscription/ChangePlanDialog'
import { PlanPickerCards } from '../../components/subscription/PlanPickerCards'
import { RenewSubscriptionDialog } from '../../components/subscription/RenewSubscriptionDialog'
import { SubscriptionCardTokenDialog } from '../../components/subscription/SubscriptionCardTokenDialog'
import { SubscriptionHistoryList } from '../../components/subscription/SubscriptionHistoryList'
import { SubscriptionInvoiceHistory } from '../../components/subscription/SubscriptionInvoiceHistory'
import { SubscriptionRefundHistory } from '../../components/subscription/SubscriptionRefundHistory'
import { SubscriptionStatusBadge } from '../../components/subscription/SubscriptionStatusBadge'
import { ACCESS } from '../../access/requirements'
import { findPlanSalesInfo } from '../../data/plans'
import { useAuth } from '../../hooks/useAuth'
import * as subscriptionService from '../../services/subscriptionService'
import { CARD_EQUAL_HEIGHT_SX, PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { CreateSubscriptionPayload, PlanPricing, Subscription, SubscriptionStatus } from '../../types/subscription'
import { formatDateBR } from '../../utils/format'
import { isOwnerRole } from '../../utils/tenantRole'

const WITHDRAWAL_WINDOW_DAYS = 7

const BILLING_PERIOD_LABELS: Record<string, string> = {
  monthly: 'Mensal',
  quarterly: 'Trimestral',
  yearly: 'Anual',
  annual: 'Anual',
}

/** Status em que o cancelamento ainda faz sentido (assinatura viva). */
const CANCELABLE_STATUSES: SubscriptionStatus[] = ['pending', 'trialing', 'active', 'past_due']
/** Espelha o guard de `SubscriptionService::changePlan` — `cancel_scheduled`/`suspended`/`canceled` exigem renovar antes. */
const CHANGE_PLAN_STATUSES: SubscriptionStatus[] = ['trialing', 'active', 'past_due']

function billingPeriodLabel(period: string): string {
  return BILLING_PERIOD_LABELS[period] ?? period
}

function formatDateTime(value: string | null): string {
  return value ? formatDateBR(value) : '—'
}

/**
 * Dias decorridos desde a criação da assinatura (base da janela de
 * arrependimento de 7 dias, calculada a partir de `subscriptions.created_at`,
 * o mesmo campo usado pelo backend).
 */
function daysSince(createdAt: string | null): number | null {
  if (!createdAt) return null
  const created = new Date(createdAt).getTime()
  if (Number.isNaN(created)) return null
  return (Date.now() - created) / (1000 * 60 * 60 * 24)
}

/** Dias restantes (arredondado para cima) até `date`, ou `null` se já passou/inválido — base do alerta de carência. */
function daysUntil(date: string | null): number | null {
  if (!date) return null
  const target = new Date(date).getTime()
  if (Number.isNaN(target)) return null
  const diff = Math.ceil((target - Date.now()) / (1000 * 60 * 60 * 24))
  return diff >= 0 ? diff : 0
}

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <Stack
      direction={{ xs: 'column', sm: 'row' }}
      sx={{ py: 1.2, gap: { xs: 0.25, sm: 2 }, alignItems: { sm: 'center' } }}
    >
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', minWidth: { sm: 220 } }}>{label}</Typography>
      <Box sx={{ fontSize: 14.5, fontWeight: 500, color: 'var(--pt-text)' }}>{children}</Box>
    </Stack>
  )
}

function SummaryCard({
  icon,
  label,
  value,
  caption,
}: {
  icon: ReactNode
  label: string
  value: string
  caption: string
}) {
  return (
    <Paper
      variant="outlined"
      sx={{
        p: 2.25,
        ...ELEVATED_SURFACE_SX,
        minHeight: 148,
        ...CARD_EQUAL_HEIGHT_SX,
      }}
    >
      <Stack spacing={1.25}>
        <Box
          sx={{
            width: 42,
            height: 42,
            borderRadius: UI_RADIUS.md,
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'color-mix(in srgb, var(--pt-primary) 12%, white)',
            color: 'var(--pt-primary)',
          }}
        >
          {icon}
        </Box>
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textTransform: 'uppercase', letterSpacing: 0.4 }}>
          {label}
        </Typography>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 18.5, fontWeight: 700 }}>{value}</Typography>
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>{caption}</Typography>
      </Stack>
    </Paper>
  )
}

export function SubscriptionPage() {
  const { accessProfile, activeTenant, hasPermission } = useAuth()
  const canUpdate = hasPermission(ACCESS.subscriptionUpdate)
  const canReadSettings = hasPermission(ACCESS.tenantSettingsRead)
  const isOwner = Boolean(accessProfile?.is_tenant_owner || isOwnerRole(activeTenant?.role, activeTenant?.role_slug))
  /**
   * Dono da empresa sempre enxerga a tela mesmo sem `subscription:read`
   * atribuída via grupo (o vínculo de dono não passa necessariamente por
   * permissão de grupo — ver `PermissionService::getAccessProfile`). Demais
   * usuários dependem da permissão real.
   */
  const canView = isOwner || hasPermission(ACCESS.subscriptionRead)

  const [subscription, setSubscription] = useState<Subscription | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [availablePlans, setAvailablePlans] = useState<PlanPricing[] | null>(null)
  const [availablePlansError, setAvailablePlansError] = useState<string | null>(null)
  const [selectedPlan, setSelectedPlan] = useState<PlanPricing | null>(null)
  const [createSubmitting, setCreateSubmitting] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
  const [billingPeriod, setBillingPeriod] = useState<CreateSubscriptionPayload['billing_period']>('monthly')
  const [acceptedTerms, setAcceptedTerms] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelSubmitting, setCancelSubmitting] = useState(false)
  const [cancelError, setCancelError] = useState<string | null>(null)
  const [withdrawalSubmitting, setWithdrawalSubmitting] = useState(false)
  const [renewOpen, setRenewOpen] = useState(false)
  const [renewSubmitting, setRenewSubmitting] = useState(false)
  const [renewError, setRenewError] = useState<string | null>(null)
  const [changeCardOpen, setChangeCardOpen] = useState(false)
  const [changePlanOpen, setChangePlanOpen] = useState(false)
  const [cardTokenDialogOpen, setCardTokenDialogOpen] = useState(false)
  const [actionMessage, setActionMessage] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const canStartNewSubscription = !subscription || subscription.status === 'canceled'

  function load() {
    setIsLoading(true)
    setLoadError(null)
    subscriptionService
      .getSubscription()
      .then((current) => {
        setSubscription(current)

        // Lista de planos só é necessária para montar a tela de contratação
        // inicial (sem assinatura ainda) — evita uma chamada extra quando a
        // empresa já tem assinatura. Sem assinatura, `available-plans` já
        // retorna TODOS os planos ativos com preço real (não só o padrão do
        // cadastro).
        if (current === null || current.status === 'canceled') {
          setAvailablePlansError(null)
          subscriptionService
            .getAvailablePlans()
            .then(setAvailablePlans)
            .catch((error: unknown) => {
              setAvailablePlansError(getApiErrorMessage(error, 'Não foi possível carregar os planos disponíveis agora.'))
            })
        }
      })
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a assinatura da empresa agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    if (!canView) {
      setIsLoading(false)
      return
    }

    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canView])

  const withinWithdrawalWindow = useMemo(() => {
    const days = daysSince(subscription?.created_at ?? null)
    return days !== null && days < WITHDRAWAL_WINDOW_DAYS
  }, [subscription])

  const selectedPlanSalesInfo = useMemo(
    () => (selectedPlan ? findPlanSalesInfo(selectedPlan.plan.slug, selectedPlan.plan.name) : null),
    [selectedPlan],
  )

  const canCancel = Boolean(subscription && CANCELABLE_STATUSES.includes(subscription.status))
  const canChangePlan = Boolean(subscription && CHANGE_PLAN_STATUSES.includes(subscription.status))
  const canRenew = subscription?.status === 'cancel_scheduled'
  /** Qualquer assinatura viva pode ter Preapproval — o backend recusa (`SUBSCRIPTION_NO_ACTIVE_PREAPPROVAL`) quando o plano é free/manual. */
  const canChangeCard = Boolean(subscription && subscription.status !== 'canceled')
  const canRequestWithdrawal = Boolean(subscription && subscription.status !== 'canceled' && withinWithdrawalWindow)
  const gracePeriodDaysLeft = useMemo(() => daysUntil(subscription?.grace_period_ends_at ?? null), [subscription])

  const capabilityItems = useMemo(() => {
    const items = [
      'Acompanhar o plano ativo da empresa, o ciclo contratado e o status atual da assinatura.',
      'Consultar datas críticas do contrato: início do período, fim do período, próxima cobrança, fim do teste e cancelamento agendado.',
      'Ver a situação da renovação automática e usar o cancelamento ao fim do ciclo como forma de interromper a renovação futura.',
      'Acompanhar faturas emitidas e o status dos pagamentos recorrentes já processados pelo Mercado Pago.',
      'Iniciar a contratação inicial do plano e autorizar a primeira etapa no Mercado Pago quando a empresa ainda não tiver assinatura ativa.',
    ]

    if (canRequestWithdrawal) {
      items.push('Solicitar arrependimento com protocolo dentro da janela legal de 7 dias, quando elegível.')
    }

    if (canCancel) {
      items.push('Cancelar imediatamente ou agendar o cancelamento para o fim do ciclo vigente.')
    }

    if (canRenew) {
      items.push('Renovar a assinatura desfazendo um cancelamento agendado, sem perder o histórico do contrato.')
    }

    if (canChangeCard) {
      items.push('Trocar o cartão usado na cobrança automática recorrente diretamente pelo PegaTicket.')
    }

    if (canChangePlan) {
      items.push('Mudar para outro plano disponível, com o preço real de cada período antes de confirmar.')
    }

    items.push('Consultar o histórico completo de assinaturas contratadas pela empresa ao longo do tempo.')

    if (subscription?.grace_period_ends_at) {
      items.push('Acompanhar a carência por falha de cobrança e agir antes da suspensão total do acesso.')
    }

    if (canReadSettings) {
      items.push('Entrar em Configurações para atualizar os dados cadastrais e operacionais da empresa quando necessário.')
    }

    if (subscription?.status === 'canceled') {
      items.push('Contratar novamente um plano após cancelamento, escolhendo o pacote e o ciclo mais adequados para a retomada.')
    }

    return items
  }, [canCancel, canChangeCard, canChangePlan, canReadSettings, canRenew, canRequestWithdrawal, subscription?.grace_period_ends_at, subscription?.status])

  async function handleConfirmCancel(immediately: boolean, reason: string) {
    setCancelSubmitting(true)
    setCancelError(null)
    try {
      await subscriptionService.cancelSubscription({ immediately, reason: reason || undefined })
      setCancelOpen(false)
      setActionMessage(
        immediately
          ? 'Assinatura cancelada. O acesso pago foi encerrado.'
          : 'Cancelamento agendado para o fim do ciclo atual.',
      )
      load()
    } catch (error) {
      setCancelError(getApiErrorMessage(error, 'Não foi possível cancelar a assinatura agora.'))
    } finally {
      setCancelSubmitting(false)
    }
  }

  async function handleWithdrawal() {
    setWithdrawalSubmitting(true)
    setActionError(null)
    setActionMessage(null)
    try {
      const result = await subscriptionService.requestWithdrawal()
      setActionMessage(`Solicitação de arrependimento registrada. Protocolo: ${result.protocol}.`)
      load()
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível solicitar o arrependimento agora.'))
    } finally {
      setWithdrawalSubmitting(false)
    }
  }

  async function handleConfirmRenew() {
    setRenewSubmitting(true)
    setRenewError(null)
    try {
      const renewed = await subscriptionService.renewSubscription()
      setSubscription(renewed)
      setRenewOpen(false)
      setActionMessage('Assinatura renovada. O cancelamento agendado foi desfeito.')
    } catch (error) {
      setRenewError(getApiErrorMessage(error, 'Não foi possível renovar a assinatura agora.'))
    } finally {
      setRenewSubmitting(false)
    }
  }

  function handleChangeCardSuccess(updated: Subscription) {
    setSubscription(updated)
    setActionMessage('Cartão da assinatura atualizado com sucesso.')
  }

  function handleChangePlanSuccess(updated: Subscription) {
    setSubscription(updated)
    setActionMessage(`Plano alterado com sucesso para ${updated.plan?.name ?? 'o novo plano'}.`)
  }

  function handleSelectPlan(plan: PlanPricing) {
    setSelectedPlan(plan)
    setBillingPeriod('monthly')
    setAcceptedTerms(false)
    setCreateError(null)
  }

  function handleBackToPlanList() {
    setSelectedPlan(null)
    setAcceptedTerms(false)
    setCreateError(null)
  }

  async function handleCreateSubscription() {
    if (!selectedPlan) return
    setCreateSubmitting(true)
    setCreateError(null)
    setActionError(null)
    setActionMessage(null)

    try {
      const created = await subscriptionService.createSubscription({
        billing_period: billingPeriod,
        accepted_terms: true,
        plan_id: selectedPlan.plan.uuid,
      })

      setSubscription(created)
      setActionMessage('Assinatura iniciada com sucesso.')
      setSelectedPlan(null)
      setAcceptedTerms(false)
    } catch (error) {
      // Plano pago sem cartão informado ainda: abre o passo de cartão em vez
      // de mostrar erro — a contratação continua com o mesmo plano/período já
      // escolhidos.
      if (error instanceof ApiRequestError && error.code === 'SUBSCRIPTION_CARD_TOKEN_REQUIRED') {
        setCardTokenDialogOpen(true)
      } else {
        setCreateError(getApiErrorMessage(error, 'Não foi possível iniciar a assinatura agora.'))
      }
    } finally {
      setCreateSubmitting(false)
    }
  }

  function handleCardTokenSuccess(created: Subscription) {
    setSubscription(created)
    setActionMessage('Assinatura iniciada com sucesso.')
    setSelectedPlan(null)
    setAcceptedTerms(false)
  }

  if (!canView) {
    return (
      <Box sx={{ width: '100%', minWidth: 0, flex: 1 }}>
        <PageHeader title="Assinatura da empresa" subtitle="Plano, cobrança e situação da assinatura da empresa ativa." />
        <Alert severity="info" variant="outlined">
          Esta área concentra decisões de plano, assinatura e cobrança. Fale com o proprietário da empresa ou com quem
          administra os acessos para liberar a permissão de assinatura, se você precisar dela.
        </Alert>
      </Box>
    )
  }

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, width: '100%', minWidth: 0, flex: 1 }}>
      <PageHeader title="Assinatura da empresa" subtitle="Plano, cobrança, trial, renovação e decisões de assinatura da sua empresa." />

      <Paper
        variant="outlined"
        sx={{
          p: { xs: 2.5, sm: 3 },
          ...ELEVATED_SURFACE_SX,
          mb: 2.5,
          background: 'color-mix(in srgb, var(--pt-primary) 6%, var(--pt-surface))',
        }}
      >
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2.5} sx={{ justifyContent: 'space-between', alignItems: { md: 'center' } }}>
          <Box>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textTransform: 'uppercase', letterSpacing: 0.4 }}>
              Empresa ativa
            </Typography>
            <Typography sx={{ fontSize: { xs: 22, sm: 28 }, fontWeight: 800, mt: 0.5 }}>
              {activeTenant?.tenant_name ?? 'Empresa'}
            </Typography>
            <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', mt: 0.75, maxWidth: 620 }}>
              Acompanhe tudo o que impacta contratação, vigência, renovação e cobrança da empresa, sem precisar
              navegar por telas dispersas.
            </Typography>
          </Box>

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            {activeTenant?.plan_name && <Chip label={`Plano ${activeTenant.plan_name}`} color="primary" variant="outlined" />}
            {isOwner && <Chip label="Perfil proprietário" color="success" variant="outlined" />}
          </Stack>
        </Stack>
      </Paper>

      {loadError && !subscription && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {actionMessage && (
        <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setActionMessage(null)}>
          {actionMessage}
        </Alert>
      )}
      {actionError && (
        <Alert severity="error" sx={{ mb: 2.5 }} onClose={() => setActionError(null)}>
          {actionError}
        </Alert>
      )}

      {isLoading && !subscription ? (
        <Skeleton variant="rounded" height={280} />
      ) : !loadError && !subscription ? (
        <Stack spacing={2.5}>
          <Paper
            variant="outlined"
            sx={{ p: { xs: 3, sm: 5 }, ...ELEVATED_SURFACE_SX }}
          >
            <Stack spacing={2}>
              <Box sx={{ textAlign: 'center' }}>
                <ReceiptLongOutlinedIcon sx={{ fontSize: 44, color: 'var(--pt-muted)', mb: 1 }} />
                <Typography sx={{ fontWeight: 700, fontSize: 18, mb: 0.5 }}>Nenhuma assinatura ativa</Typography>
                <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', maxWidth: 620, mx: 'auto' }}>
                  Sua empresa ainda não possui uma assinatura registrada. Escolha o plano e o período de cobrança
                  para iniciar a contratação e concluir a autorização no Mercado Pago.
                </Typography>
              </Box>

              {createError && <Alert severity="error">{createError}</Alert>}

              <Paper
                variant="outlined"
                sx={{ p: { xs: 2, sm: 3 }, ...ELEVATED_SURFACE_SX }}
              >
                <Box sx={{ mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  {selectedPlan && (
                    <IconButton
                      size="small"
                      onClick={handleBackToPlanList}
                      disabled={createSubmitting}
                      aria-label="Voltar para a lista de planos"
                      sx={{ borderRadius: UI_RADIUS.md, width: UI_SIZE.iconButton, height: UI_SIZE.iconButton }}
                    >
                      <ArrowBackRoundedIcon fontSize="small" />
                    </IconButton>
                  )}
                  <Box>
                    <Typography sx={{ fontWeight: 700, fontSize: 15.5 }}>
                      {selectedPlan ? `Plano ${selectedPlan.plan.name}` : 'Iniciar contratação'}
                    </Typography>
                    <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mt: 0.25 }}>
                      {selectedPlan
                        ? 'Escolha o período de cobrança. O valor mostrado já é o preço final por mês em cada período.'
                        : 'Escolha o plano que sua empresa vai contratar para ver o preço real de cada período.'}
                    </Typography>
                  </Box>
                </Box>

                {isLoading && availablePlans === null && (
                  <Stack spacing={1.5}>
                    <Skeleton variant="rounded" height={96} />
                    <Skeleton variant="rounded" height={96} />
                  </Stack>
                )}

                {!isLoading && availablePlansError && (
                  <Alert
                    severity="error"
                    variant="outlined"
                    action={
                      <Button color="inherit" size="small" onClick={load}>
                        Tentar novamente
                      </Button>
                    }
                  >
                    {availablePlansError}
                  </Alert>
                )}

                {!availablePlansError && availablePlans && availablePlans.length === 0 && (
                  <Alert severity="warning" variant="outlined">
                    Nenhum plano disponível para contratação no momento. Fale com o suporte.
                  </Alert>
                )}

                {!availablePlansError && availablePlans && availablePlans.length > 0 && !selectedPlan && (
                  <PlanPickerCards plans={availablePlans} onSelect={handleSelectPlan} />
                )}

                {selectedPlan && (
                  <>
                    <BillingPeriodOptionCards
                      periods={selectedPlan.billing_periods}
                      value={billingPeriod}
                      onChange={(period) => setBillingPeriod(period as CreateSubscriptionPayload['billing_period'])}
                    />

                    {selectedPlanSalesInfo && selectedPlanSalesInfo.featureHighlights.length > 0 && (
                      <Box
                        sx={{
                          mt: 2,
                          p: 2,
                          ...SOFT_PANEL_SX,
                        }}
                      >
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
                          O que está incluído no plano {selectedPlanSalesInfo.name}
                        </Typography>
                        <Stack spacing={0.75}>
                          {selectedPlanSalesInfo.featureHighlights.map((feature) => (
                            <Stack key={feature} direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
                              <CheckRoundedIcon sx={{ fontSize: 17, color: 'var(--pt-success)', mt: 0.2 }} />
                              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>{feature}</Typography>
                            </Stack>
                          ))}
                        </Stack>
                      </Box>
                    )}

                    <FormControlLabel
                      sx={{ mt: 2 }}
                      control={<Checkbox checked={acceptedTerms} onChange={(event) => setAcceptedTerms(event.target.checked)} />}
                      label="Li e concordo com os termos vigentes para iniciar a assinatura."
                    />

                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mt: 1 }}>
                      <Button variant="contained" disabled={createSubmitting || !acceptedTerms} onClick={() => void handleCreateSubscription()}>
                        {createSubmitting ? 'Iniciando…' : 'Iniciar assinatura'}
                      </Button>
                      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', alignSelf: 'center' }}>
                        Se o plano escolhido for pago, você confirma o cartão da cobrança automática na etapa
                        seguinte, direto pelo PegaTicket.
                      </Typography>
                    </Stack>
                  </>
                )}
              </Paper>
            </Stack>
          </Paper>

          <Paper
            variant="outlined"
            sx={{ p: { xs: 2.5, sm: 3 }, ...ELEVATED_SURFACE_SX }}
          >
            <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.75 }}>O que você poderá gerenciar aqui</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1.5 }}>
              Este menu foi pensado para centralizar decisões de plano e cobrança sem misturar com a operação diária.
            </Typography>
            <Stack spacing={1.1}>
              {capabilityItems.map((item) => (
                <Typography key={item} sx={{ fontSize: 14.2, color: 'var(--pt-text)' }}>
                  • {item}
                </Typography>
              ))}
            </Stack>
          </Paper>
        </Stack>
      ) : (
        subscription && (
          <Stack spacing={2.5}>
            {subscription.grace_period_ends_at && (
              <Alert severity="warning" variant="filled">
                Regularize a situação até {formatDateBR(subscription.grace_period_ends_at)} para não perder o acesso
                {gracePeriodDaysLeft !== null &&
                  (gracePeriodDaysLeft > 0
                    ? ` (${gracePeriodDaysLeft} ${gracePeriodDaysLeft === 1 ? 'dia restante' : 'dias restantes'})`
                    : ' (prazo vence hoje)')}
                .
              </Alert>
            )}

            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))', lg: 'repeat(4, minmax(0, 1fr))' },
                gap: 2,
              }}
            >
              <SummaryCard
                icon={<ApartmentOutlinedIcon fontSize="small" />}
                label="Plano atual"
                value={subscription.plan?.name ?? activeTenant?.plan_name ?? 'Plano'}
                caption={`Ciclo ${billingPeriodLabel(subscription.billing_period).toLowerCase()}.`}
              />
              <SummaryCard
                icon={<CreditCardOutlinedIcon fontSize="small" />}
                label="Situação"
                value={subscription.status === 'cancel_scheduled' ? 'Cancelamento agendado' : (subscription.status === 'trialing' ? 'Em teste' : subscription.status === 'active' ? 'Ativa' : subscription.status === 'past_due' ? 'Pagamento pendente' : subscription.status === 'suspended' ? 'Suspensa' : subscription.status === 'pending' ? 'Pendente' : subscription.status === 'canceled' ? 'Cancelada' : subscription.status)}
                caption="Status operacional da assinatura da empresa."
              />
              <SummaryCard
                icon={<CalendarMonthOutlinedIcon fontSize="small" />}
                label={subscription.status === 'trialing' ? 'Fim do teste' : 'Próxima cobrança'}
                value={formatDateTime(subscription.status === 'trialing' ? subscription.trial_ends_at : subscription.next_charge_at)}
                caption={
                  subscription.is_trial
                    ? subscription.trial_days_remaining !== null
                      ? `${subscription.trial_days_remaining} ${subscription.trial_days_remaining === 1 ? 'dia restante' : 'dias restantes'} de teste.`
                      : 'Período experimental encerrado.'
                    : 'Data prevista para a próxima cobrança.'
                }
              />
              <SummaryCard
                icon={<AutorenewOutlinedIcon fontSize="small" />}
                label="Renovação"
                value={subscription.auto_renew ? 'Automática' : 'Encerrando ao fim do ciclo'}
                caption={subscription.cancel_at ? `Cancelamento previsto para ${formatDateBR(subscription.cancel_at)}.` : 'Sem cancelamento agendado no momento.'}
              />
            </Box>

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 1.5, mb: 1 }}>
                <Box>
                  <Typography sx={{ fontWeight: 700, fontSize: 16 }}>Visão contratual</Typography>
                  <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5 }}>
                    Tudo o que impacta datas, vigência, renovação e elegibilidade de arrependimento.
                  </Typography>
                </Box>
                <SubscriptionStatusBadge status={subscription.status} />
              </Stack>

              <Divider sx={{ my: 1 }} />

              <InfoRow label="Plano">{subscription.plan?.name ?? activeTenant?.plan_name ?? '—'}</InfoRow>
              <InfoRow label="Ciclo de cobrança">{billingPeriodLabel(subscription.billing_period)}</InfoRow>
              <InfoRow label="Renovação automática">{subscription.auto_renew ? 'Ativa' : 'Desligada / encerrando ao fim do ciclo'}</InfoRow>
              {subscription.status === 'trialing' && <InfoRow label="Teste até">{formatDateTime(subscription.trial_ends_at)}</InfoRow>}
              <InfoRow label="Período atual">
                {formatDateTime(subscription.current_period_start)} até {formatDateTime(subscription.current_period_end)}
              </InfoRow>
              <InfoRow label="Próxima cobrança">{formatDateTime(subscription.next_charge_at)}</InfoRow>
              {subscription.grace_period_ends_at && <InfoRow label="Carência até">{formatDateTime(subscription.grace_period_ends_at)}</InfoRow>}
              {subscription.cancel_at && <InfoRow label="Cancelamento agendado">{formatDateTime(subscription.cancel_at)}</InfoRow>}
              {subscription.canceled_at && <InfoRow label="Cancelada em">{formatDateTime(subscription.canceled_at)}</InfoRow>}
              <InfoRow label="Aceite dos termos">{subscription.accepted_at ? `${formatDateTime(subscription.accepted_at)}${subscription.accepted_terms_version ? ` · versão ${subscription.accepted_terms_version}` : ''}` : '—'}</InfoRow>
            </Paper>

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.5 }}>Pagamento e faturas</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
                Planos pagos pedem o cartão da cobrança recorrente direto pelo PegaTicket na contratação. Depois disso,
                o sistema acompanha por aqui as faturas emitidas e o resultado dos pagamentos recorrentes.
              </Typography>

              <SubscriptionInvoiceHistory />
            </Paper>

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.5 }}>Histórico de assinaturas</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
                Todas as assinaturas contratadas pela empresa ao longo do tempo, não só a atual.
              </Typography>

              <SubscriptionHistoryList />
            </Paper>

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.5 }}>Reembolsos</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
                Vendas canceladas com pagamento já feito, arrependimento dentro do prazo e contestações de cobrança
                aparecem juntos aqui.
              </Typography>

              <SubscriptionRefundHistory />
            </Paper>

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.5 }}>Ações da assinatura</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
                Este bloco concentra as ações críticas do contrato e da cobrança. Recursos administrativos da empresa,
                como logo e dados cadastrais, continuam em Configurações.
              </Typography>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25} useFlexGap sx={{ flexWrap: 'wrap' }}>
                {canUpdate && canRenew && (
                  <Button
                    variant="contained"
                    onClick={() => {
                      setRenewError(null)
                      setRenewOpen(true)
                    }}
                  >
                    Renovar assinatura
                  </Button>
                )}
                {canUpdate && canChangePlan && (
                  <Button variant="outlined" onClick={() => setChangePlanOpen(true)}>
                    Mudar de plano
                  </Button>
                )}
                {canUpdate && canChangeCard && (
                  <Button variant="outlined" onClick={() => setChangeCardOpen(true)}>
                    Trocar cartão
                  </Button>
                )}
                {canUpdate && canCancel && (
                  <Button
                    variant="outlined"
                    color="error"
                    onClick={() => {
                      setCancelError(null)
                      setCancelOpen(true)
                    }}
                  >
                    Gerenciar cancelamento
                  </Button>
                )}
                {canUpdate && canRequestWithdrawal && (
                  <Button
                    variant="contained"
                    color="error"
                    disabled={withdrawalSubmitting}
                    onClick={() => void handleWithdrawal()}
                  >
                    {withdrawalSubmitting ? 'Enviando…' : 'Solicitar arrependimento'}
                  </Button>
                )}
                {canReadSettings && (
                  <Button component={RouterLink} to="/configuracoes" variant="text">
                    Abrir configurações da empresa
                  </Button>
                )}
              </Stack>
            </Paper>

            {canUpdate && canStartNewSubscription && (
              <Paper
                variant="outlined"
                sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
              >
                <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.5 }}>Contratar novo plano</Typography>
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>
                  Esta assinatura já foi encerrada. Escolha um novo plano para reativar a empresa com uma nova contratação.
                </Typography>

                {createError && <Alert severity="error" sx={{ mb: 2 }}>{createError}</Alert>}

                <Box sx={{ mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  {selectedPlan && (
                    <IconButton
                      size="small"
                      onClick={handleBackToPlanList}
                      disabled={createSubmitting}
                      aria-label="Voltar para a lista de planos"
                    >
                      <ArrowBackRoundedIcon fontSize="small" />
                    </IconButton>
                  )}
                  <Box>
                    <Typography sx={{ fontWeight: 700, fontSize: 15.5 }}>
                      {selectedPlan ? `Plano ${selectedPlan.plan.name}` : 'Escolher plano para nova contratação'}
                    </Typography>
                    <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mt: 0.25 }}>
                      {selectedPlan
                        ? 'Escolha o período de cobrança. O valor mostrado já é o preço final por mês em cada período.'
                        : 'Selecione o plano que a empresa vai contratar novamente.'}
                    </Typography>
                  </Box>
                </Box>

                {isLoading && availablePlans === null && (
                  <Stack spacing={1.5}>
                    <Skeleton variant="rounded" height={96} />
                    <Skeleton variant="rounded" height={96} />
                  </Stack>
                )}

                {!isLoading && availablePlansError && (
                  <Alert
                    severity="error"
                    variant="outlined"
                    action={
                      <Button color="inherit" size="small" onClick={load}>
                        Tentar novamente
                      </Button>
                    }
                  >
                    {availablePlansError}
                  </Alert>
                )}

                {!availablePlansError && availablePlans && availablePlans.length === 0 && (
                  <Alert severity="warning" variant="outlined">
                    Nenhum plano disponível para contratação no momento. Fale com o suporte.
                  </Alert>
                )}

                {!availablePlansError && availablePlans && availablePlans.length > 0 && !selectedPlan && (
                  <PlanPickerCards plans={availablePlans} onSelect={handleSelectPlan} />
                )}

                {selectedPlan && (
                  <>
                    <BillingPeriodOptionCards
                      periods={selectedPlan.billing_periods}
                      value={billingPeriod}
                      onChange={(period) => setBillingPeriod(period as CreateSubscriptionPayload['billing_period'])}
                    />

                    {selectedPlanSalesInfo && selectedPlanSalesInfo.featureHighlights.length > 0 && (
                      <Box
                        sx={{
                          mt: 2,
                          p: 2,
                          ...SOFT_PANEL_SX,
                        }}
                      >
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
                          O que está incluído no plano {selectedPlanSalesInfo.name}
                        </Typography>
                        <Stack spacing={0.75}>
                          {selectedPlanSalesInfo.featureHighlights.map((feature) => (
                            <Stack key={feature} direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
                              <CheckRoundedIcon sx={{ fontSize: 17, color: 'var(--pt-success)', mt: 0.2 }} />
                              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>{feature}</Typography>
                            </Stack>
                          ))}
                        </Stack>
                      </Box>
                    )}

                    <FormControlLabel
                      sx={{ mt: 2 }}
                      control={<Checkbox checked={acceptedTerms} onChange={(event) => setAcceptedTerms(event.target.checked)} />}
                      label="Li e concordo com os termos vigentes para iniciar a nova assinatura."
                    />

                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mt: 1 }}>
                      <Button variant="contained" disabled={createSubmitting || !acceptedTerms} onClick={() => void handleCreateSubscription()}>
                        {createSubmitting ? 'Iniciando…' : 'Contratar novo plano'}
                      </Button>
                      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', alignSelf: 'center' }}>
                        Se o plano escolhido for pago, você confirma o cartão da cobrança automática na etapa
                        seguinte, direto pelo PegaTicket.
                      </Typography>
                    </Stack>
                  </>
                )}
              </Paper>
            )}

            <Paper
              variant="outlined"
              sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
            >
              <Typography sx={{ fontWeight: 700, fontSize: 16, mb: 0.75 }}>Mapa completo deste menu</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1.5 }}>
                Tudo abaixo já está centralizado ou contextualizado dentro desta área.
              </Typography>
              <Stack spacing={1.1}>
                {capabilityItems.map((item) => (
                  <Typography key={item} sx={{ fontSize: 14.2 }}>
                    • {item}
                  </Typography>
                ))}
              </Stack>
            </Paper>
          </Stack>
        )
      )}

      <CancelSubscriptionDialog
        open={cancelOpen}
        isSubmitting={cancelSubmitting}
        error={cancelError}
        onCancel={() => setCancelOpen(false)}
        onConfirm={(immediately, reason) => void handleConfirmCancel(immediately, reason)}
      />

      <RenewSubscriptionDialog
        open={renewOpen}
        isSubmitting={renewSubmitting}
        error={renewError}
        onCancel={() => setRenewOpen(false)}
        onConfirm={() => void handleConfirmRenew()}
      />

      <ChangeCardDialog
        open={changeCardOpen}
        onClose={() => setChangeCardOpen(false)}
        onSuccess={handleChangeCardSuccess}
      />

      <ChangePlanDialog
        open={changePlanOpen}
        currentPlanName={subscription?.plan?.name ?? activeTenant?.plan_name ?? 'atual'}
        onClose={() => setChangePlanOpen(false)}
        onSuccess={handleChangePlanSuccess}
      />

      {selectedPlan && (
        <SubscriptionCardTokenDialog
          open={cardTokenDialogOpen}
          planName={selectedPlan.plan.name}
          onClose={() => setCardTokenDialogOpen(false)}
          onSuccess={handleCardTokenSuccess}
          onSubmitToken={(cardToken) =>
            subscriptionService.createSubscription({
              billing_period: billingPeriod,
              accepted_terms: true,
              plan_id: selectedPlan.plan.uuid,
              card_token: cardToken,
            })
          }
        />
      )}
    </Box>
  )
}
