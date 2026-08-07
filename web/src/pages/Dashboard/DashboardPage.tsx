import AddBusinessOutlinedIcon from '@mui/icons-material/AddBusinessOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import AccountBalanceWalletOutlinedIcon from '@mui/icons-material/AccountBalanceWalletOutlined'
import SellOutlinedIcon from '@mui/icons-material/SellOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import ShoppingCartCheckoutOutlinedIcon from '@mui/icons-material/ShoppingCartCheckoutOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import SavingsOutlinedIcon from '@mui/icons-material/SavingsOutlined'
import QueryStatsOutlinedIcon from '@mui/icons-material/QueryStatsOutlined'
import TimelineOutlinedIcon from '@mui/icons-material/TimelineOutlined'
import PercentOutlinedIcon from '@mui/icons-material/PercentOutlined'
import TrendingDownOutlinedIcon from '@mui/icons-material/TrendingDownOutlined'
import TodayOutlinedIcon from '@mui/icons-material/TodayOutlined'
import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import LightbulbOutlinedIcon from '@mui/icons-material/LightbulbOutlined'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { PageHeader } from '../../components/layout/PageHeader'
import { OnboardingChecklistCard } from '../../components/dashboard/OnboardingChecklistCard'
import { OperationSnapshotCard } from '../../components/dashboard/OperationSnapshotCard'
import { SalesByMonthChart } from '../../components/dashboard/SalesByMonthChart'
import { QuickActionCard } from '../../components/dashboard/QuickActionCard'
import { SeasonalityMatrixCard } from '../../components/dashboard/SeasonalityMatrixCard'
import { useDashboardReport } from '../../hooks/useDashboardReport'
import { useOnboardingChecklist } from '../../hooks/useOnboardingChecklist'
import { useReportAlerts } from '../../hooks/useReportAlerts'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { useOperationSnapshot } from '../../hooks/useOperationSnapshot'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { daysBetween, presetRange } from '../../utils/period'
import { formatCurrency, formatDateBR, formatPercentage } from '../../utils/format'
import { ACCESS } from '../../access/requirements'
import * as financeService from '../../services/financeService'
import type { FinanceDashboard } from '../../types/finance'

const QUICK_ACTIONS = [
  { icon: ReceiptLongOutlinedIcon, label: 'Nova venda', to: '/vendas-manuais/nova' },
  { icon: Inventory2OutlinedIcon, label: 'Cadastrar evento', to: '/eventos/novo' },
]

const DEFAULT_RANGE = presetRange('this_month')
const HOME_TARGET_OCCUPANCY_PERCENTAGE = 80

function formatDecimal(value: number, maximumFractionDigits: number = 1): string {
  const safe = Number.isFinite(value) ? value : 0
  return safe.toLocaleString('pt-BR', {
    minimumFractionDigits: safe % 1 === 0 ? 0 : 1,
    maximumFractionDigits,
  })
}

function formatSignedPercentage(value: number | null): string | null {
  if (value === null || !Number.isFinite(value)) return null
  const prefix = value > 0 ? '+' : ''
  return `${prefix}${value.toFixed(2).replace('.', ',')}% vs período anterior`
}

function toMoneyNumber(value: string | number | null | undefined): number {
  const numeric = typeof value === 'string' ? Number(value) : value
  return Number.isFinite(numeric) ? Number(numeric) : 0
}

function pluralize(value: number, singular: string, plural: string): string {
  return `${value} ${value === 1 ? singular : plural}`
}

export function DashboardPage() {
  const { can } = useAccessControl()
  const { activeTenantUuid, isAccessProfileLoading } = useAuth()
  const canViewStats = can(ACCESS.dashboard)
  const canViewFinance = can(ACCESS.financeRead)
  const [from, setFrom] = useState(DEFAULT_RANGE.from)
  const [to, setTo] = useState(DEFAULT_RANGE.to)
  const [financeDashboard, setFinanceDashboard] = useState<FinanceDashboard | null>(null)

  const { indicators, charts, isLoading, error } = useDashboardReport(from, to, canViewStats)
  const { alerts } = useReportAlerts(canViewStats)
  const { snapshot, isLoading: isLoadingSnapshot } = useOperationSnapshot(canViewStats)
  const { checklist, dismiss: dismissChecklist } = useOnboardingChecklist()

  const showOnboardingChecklist = checklist !== null && checklist.completed < checklist.total && !checklist.is_dismissed
  const isFirstOrderEmptyState = !isLoading && !error && indicators !== null && indicators.total_sales === 0

  const quickActions = QUICK_ACTIONS.filter((action) => {
    if (action.to === '/vendas-manuais/nova') return can(ACCESS.salesCreate)
    if (action.to === '/eventos/novo') return can(ACCESS.eventsCreate)
    return true
  })

  useEffect(() => {
    if (!canViewStats || !canViewFinance || !activeTenantUuid) {
      setFinanceDashboard(null)
      return
    }

    let cancelled = false

    financeService
      .getFinanceDashboard()
      .then((data) => {
        if (!cancelled) setFinanceDashboard(data)
      })
      .catch(() => {
        if (!cancelled) setFinanceDashboard(null)
      })

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, canViewFinance, canViewStats])

  const periodDays = Math.max(1, daysBetween(from, to) + 1)
  const ticketsIssued = indicators?.tickets_issued ?? 0
  const commercialCapacity = indicators?.commercial_capacity ?? 0
  const occupancyPercentage = indicators?.occupancy_percentage ?? 0
  const averageTicketAmount = toMoneyNumber(indicators?.average_ticket)
  const salesPacePerDay = periodDays > 0 ? ticketsIssued / periodDays : 0
  const targetTickets = commercialCapacity > 0 ? commercialCapacity * (HOME_TARGET_OCCUPANCY_PERCENTAGE / 100) : 0
  const requiredPacePerDay = periodDays > 0 ? Math.max(0, targetTickets - ticketsIssued) / periodDays : 0
  const projectedTickets = commercialCapacity > 0 ? Math.min(commercialCapacity, ticketsIssued + (salesPacePerDay * periodDays)) : 0
  const projectedOccupancyPercentage = commercialCapacity > 0 ? (projectedTickets / commercialCapacity) * 100 : 0
  const conversionPercentage =
    snapshot && snapshot.checkout.started > 0
      ? (snapshot.checkout.completed / snapshot.checkout.started) * 100
      : null
  const incompleteCheckouts = snapshot ? Math.max(0, snapshot.checkout.started - snapshot.checkout.completed) : 0
  const estimatedLostRevenue = incompleteCheckouts * averageTicketAmount
  const receivableBalance = financeDashboard
    ? financeDashboard.balances.future_amount + financeDashboard.balances.in_custody_amount
    : toMoneyNumber(indicators?.amount_receivable)
  const availableNowAmount = financeDashboard?.balances.available_now_amount ?? null
  const nextSettlementDate = financeDashboard?.upcoming_settlement?.scheduled_for ?? null

  const homeInsights: string[] = []

  if (commercialCapacity > 0 && projectedOccupancyPercentage >= HOME_TARGET_OCCUPANCY_PERCENTAGE) {
    homeInsights.push(
      `Mantido o ritmo atual, a ocupação projetada supera ${HOME_TARGET_OCCUPANCY_PERCENTAGE}% nos próximos ${pluralize(periodDays, 'dia', 'dias')}.`,
    )
  } else if (commercialCapacity > 0 && requiredPacePerDay > salesPacePerDay) {
    homeInsights.push(
      `O ritmo atual está abaixo do necessário para buscar ${HOME_TARGET_OCCUPANCY_PERCENTAGE}% de ocupação na próxima janela comparável.`,
    )
  }

  if (conversionPercentage !== null && conversionPercentage < 50 && snapshot && snapshot.checkout.started > 0) {
    homeInsights.push(
      `A conversão do checkout está em ${formatPercentage(conversionPercentage)} nas últimas ${snapshot.checkout.window_hours}h, com espaço claro para recuperar receita.`,
    )
  } else if (conversionPercentage !== null && conversionPercentage >= 70 && snapshot && snapshot.checkout.started > 0) {
    homeInsights.push(
      `A conversão do checkout está saudável nas últimas ${snapshot.checkout.window_hours}h, sustentando o ritmo comercial atual.`,
    )
  }

  if (nextSettlementDate && receivableBalance > 0) {
    homeInsights.push(
      `Há ${formatCurrency(receivableBalance)} ainda em trânsito financeiro, com próximo repasse previsto para ${formatDateBR(nextSettlementDate)}.`,
    )
  }

  if ((alerts?.length ?? 0) > 0 && alerts) {
    homeInsights.push(alerts[0].message)
  }

  const insightsToRender = homeInsights.slice(0, 3)

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1600 }}>
      <PageHeader
        title="Visão geral"
        subtitle="Uma leitura rápida do resultado, da direção do evento e do que precisa da sua atenção agora."
        accent
      />

      {canViewStats && (
        <PeriodFilter
          from={from}
          to={to}
          onChange={(nextFrom, nextTo) => {
            setFrom(nextFrom)
            setTo(nextTo)
          }}
          defaultPreset="this_month"
        />
      )}

      {canViewStats && error && (
        <Alert severity="warning" variant="outlined" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      <Stack spacing={1.5}>
        <Box>
          <Typography
            sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
          >
            Ações rápidas
          </Typography>
          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 200px), 1fr))',
              gap: 1.5,
            }}
          >
            {quickActions.map((action, index) => (
              <QuickActionCard key={action.label} icon={action.icon} label={action.label} index={index} to={action.to} />
            ))}
          </Box>
        </Box>

        {showOnboardingChecklist && checklist && (
          <OnboardingChecklistCard checklist={checklist} onDismiss={() => void dismissChecklist()} />
        )}

        {isAccessProfileLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
            <CircularProgress size={28} />
          </Box>
        ) : !canViewStats ? (
          <Paper
            variant="outlined"
            className="pt-reveal"
            sx={{
              p: { xs: 2.25, sm: 3 },
              ...ELEVATED_SURFACE_SX,
              display: 'flex',
              alignItems: 'flex-start',
              gap: 1.5,
            }}
          >
            <Box
              sx={{
                width: 44,
                height: 44,
                ...SOFT_PANEL_SX,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--pt-muted)',
                flexShrink: 0,
              }}
            >
              <LockOutlinedIcon />
            </Box>
            <Box>
              <Typography sx={{ fontSize: 15.5, fontWeight: 600, color: 'var(--pt-text)', mb: 0.5 }}>
                Você não tem acesso aos números da operação
              </Typography>
              <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>
                Peça ao proprietário da empresa para liberar a permissão &quot;Visão Geral&quot; no seu perfil de acesso.
              </Typography>
            </Box>
          </Paper>
        ) : (
          <>
            {isFirstOrderEmptyState && (
              <Paper
                variant="outlined"
                className="pt-reveal"
                sx={{
                  p: { xs: 2.25, sm: 3 },
                  ...ELEVATED_SURFACE_SX,
                  borderColor: 'color-mix(in srgb, var(--pt-accent) 24%, var(--pt-border))',
                  background: 'color-mix(in srgb, var(--pt-accent) 7%, var(--pt-surface))',
                }}
              >
                <Stack spacing={2}>
                  <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                    <Box
                      sx={{
                        width: 48,
                        height: 48,
                        ...SOFT_PANEL_SX,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'color-mix(in srgb, var(--pt-accent) 16%, transparent)',
                        color: 'var(--pt-accent)',
                        flexShrink: 0,
                      }}
                    >
                      <ShoppingCartCheckoutOutlinedIcon />
                    </Box>
                    <Box>
                      <Typography sx={{ fontSize: 18, fontWeight: 600, color: 'var(--pt-text)', mb: 0.5 }}>
                        Seu painel começa a ganhar vida com a primeira venda
                      </Typography>
                      <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', maxWidth: 760 }}>
                        Ainda não há vendas cadastradas nesta empresa. Assim que a primeira venda entrar, o PegaTicket
                        começa a preencher automaticamente resultado, ritmo, projeções e sinais operacionais.
                      </Typography>
                    </Box>
                  </Box>

                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}>
                    {can(ACCESS.salesCreate) ? (
                      <Button component={RouterLink} to="/vendas-manuais/nova" variant="contained">
                        Fazer primeira venda
                      </Button>
                    ) : null}
                    {can(ACCESS.eventsCreate) ? (
                      <Button component={RouterLink} to="/eventos/novo" variant="outlined">
                        Cadastrar evento
                      </Button>
                    ) : null}
                  </Stack>
                </Stack>
              </Paper>
            )}

            <Box>
              <Typography
                sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
              >
                Resultado
              </Typography>
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', xl: 'repeat(4,1fr)' },
                  gap: 1.5,
                }}
              >
                <MetricCard
                  icon={PaidOutlinedIcon}
                  label="Faturamento"
                  value={indicators ? formatCurrency(indicators.total_sales_amount) : null}
                  caption={
                    indicators
                      ? formatSignedPercentage(indicators.sales_growth_percentage) ?? `Período atual: ${indicators.comparison_current_label}`
                      : null
                  }
                  tone="accent"
                  isLoading={isLoading}
                  index={0}
                />
                <MetricCard
                  icon={SavingsOutlinedIcon}
                  label="Receita líquida"
                  value={indicators ? formatCurrency(indicators.net_revenue_amount) : null}
                  caption={
                    indicators
                      ? `${formatDecimal((toMoneyNumber(indicators.net_revenue_amount) / Math.max(1, toMoneyNumber(indicators.total_sales_amount))) * 100, 1)}% do faturamento`
                      : null
                  }
                  tone="primary"
                  isLoading={isLoading}
                  index={1}
                />
                <MetricCard
                  icon={ConfirmationNumberOutlinedIcon}
                  label="Ingressos vendidos"
                  value={indicators ? ticketsIssued.toLocaleString('pt-BR') : null}
                  caption={indicators ? `${pluralize(indicators.total_sales, 'venda', 'vendas')} no período` : null}
                  tone="accent"
                  isLoading={isLoading}
                  index={2}
                />
                <MetricCard
                  icon={EventSeatOutlinedIcon}
                  label="Ocupação comercial"
                  value={indicators ? formatPercentage(occupancyPercentage) : null}
                  caption={indicators ? `${ticketsIssued} de ${commercialCapacity} ingressos com capacidade` : null}
                  tone={occupancyPercentage >= HOME_TARGET_OCCUPANCY_PERCENTAGE ? 'accent' : 'primary'}
                  isLoading={isLoading}
                  index={3}
                />
              </Box>
            </Box>

            <Box>
              <Typography
                sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
              >
                Inteligência
              </Typography>
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', xl: 'repeat(4,1fr)' },
                  gap: 1.5,
                }}
              >
                <MetricCard
                  icon={QueryStatsOutlinedIcon}
                  label="Ritmo de vendas"
                  value={indicators ? `${formatDecimal(salesPacePerDay)}/dia` : null}
                  caption={
                    indicators
                      ? requiredPacePerDay > 0
                        ? `Necessário: ${formatDecimal(requiredPacePerDay)}/dia para buscar ${HOME_TARGET_OCCUPANCY_PERCENTAGE}%`
                        : `Meta de ${HOME_TARGET_OCCUPANCY_PERCENTAGE}% já atingida no ritmo atual`
                      : null
                  }
                  tone={requiredPacePerDay > 0 && salesPacePerDay < requiredPacePerDay ? 'warning' : 'accent'}
                  isLoading={isLoading}
                  index={4}
                />
                <MetricCard
                  icon={TimelineOutlinedIcon}
                  label="Projeção de ocupação"
                  value={indicators ? formatPercentage(projectedOccupancyPercentage) : null}
                  caption={indicators ? `Mantido o ritmo por mais ${pluralize(periodDays, 'dia', 'dias')}` : null}
                  tone={projectedOccupancyPercentage >= HOME_TARGET_OCCUPANCY_PERCENTAGE ? 'accent' : 'warning'}
                  isLoading={isLoading}
                  index={5}
                />
                <MetricCard
                  icon={PercentOutlinedIcon}
                  label="Conversão do checkout"
                  value={snapshot ? (conversionPercentage === null ? '—' : formatPercentage(conversionPercentage)) : null}
                  caption={
                    snapshot
                      ? snapshot.checkout.started > 0
                        ? `${snapshot.checkout.completed} de ${snapshot.checkout.started} concluídos (${snapshot.checkout.window_hours}h)`
                        : `Sem checkouts na janela de ${snapshot.checkout.window_hours}h`
                      : null
                  }
                  tone={conversionPercentage !== null && conversionPercentage < 50 ? 'warning' : 'primary'}
                  isLoading={isLoadingSnapshot}
                  index={6}
                />
                <MetricCard
                  icon={AccountBalanceWalletOutlinedIcon}
                  label="Saldo a receber"
                  value={indicators ? formatCurrency(receivableBalance) : null}
                  caption={
                    nextSettlementDate
                      ? `Próximo repasse: ${formatDateBR(nextSettlementDate)}`
                      : availableNowAmount !== null
                        ? `Disponível agora: ${formatCurrency(availableNowAmount)}`
                        : indicators
                          ? 'Receita ainda não liquidada no período'
                          : null
                  }
                  tone="info"
                  isLoading={isLoading}
                  index={7}
                />
              </Box>
            </Box>

            <Box>
              <Typography
                sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
              >
                Atenção agora
              </Typography>
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', xl: 'repeat(4,1fr)' },
                  gap: 1.5,
                }}
              >
                <MetricCard
                  icon={SellOutlinedIcon}
                  label="Ticket médio"
                  value={indicators ? formatCurrency(indicators.average_ticket) : null}
                  caption={indicators ? `${indicators.completed_sales} vendas concluídas no período` : null}
                  tone="primary"
                  isLoading={isLoading}
                  index={8}
                />
                <MetricCard
                  icon={TrendingDownOutlinedIcon}
                  label="Receita perdida estimada"
                  value={snapshot ? formatCurrency(estimatedLostRevenue) : null}
                  caption={
                    snapshot
                      ? incompleteCheckouts > 0
                        ? `${pluralize(incompleteCheckouts, 'checkout sem conversão', 'checkouts sem conversão')} nas últimas ${snapshot.checkout.window_hours}h`
                        : `Sem perda estimada nas últimas ${snapshot.checkout.window_hours}h`
                      : null
                  }
                  tone={estimatedLostRevenue > 0 ? 'warning' : 'primary'}
                  isLoading={isLoadingSnapshot}
                  index={9}
                />
                <MetricCard
                  icon={TodayOutlinedIcon}
                  label="Vendas hoje"
                  value={snapshot ? formatCurrency(snapshot.sales_today.total_amount) : null}
                  caption={snapshot ? `${pluralize(snapshot.sales_today.count, 'venda', 'vendas')} hoje` : null}
                  tone="accent"
                  isLoading={isLoadingSnapshot}
                  index={10}
                />
                <Paper
                  variant="outlined"
                  className="pt-reveal"
                  sx={{
                    p: { xs: 2.25, sm: 2.75 },
                    ...ELEVATED_SURFACE_SX,
                    minHeight: 220,
                    animationDelay: '770ms',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1.5,
                  }}
                >
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Box
                      sx={{
                        width: 44,
                        height: 44,
                        borderRadius: 'var(--pt-radius-lg)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'color-mix(in srgb, var(--pt-accent) 14%, var(--pt-surface))',
                        color: 'var(--pt-accent)',
                        flexShrink: 0,
                      }}
                    >
                      <LightbulbOutlinedIcon fontSize="small" />
                    </Box>
                    <Box sx={{ minWidth: 0 }}>
                      <Typography sx={{ fontSize: 13, fontWeight: 500, color: 'var(--pt-muted)' }}>
                        Alertas e oportunidades
                      </Typography>
                      <Typography
                        sx={{
                          fontFamily: '"Sora", "Inter", sans-serif',
                          fontSize: { xs: 26, sm: 32 },
                          fontWeight: 700,
                          color: 'var(--pt-text)',
                          lineHeight: 1.15,
                        }}
                      >
                        {alerts && alerts.length > 0 ? String(alerts.length) : '0'}
                      </Typography>
                    </Box>
                  </Box>

                  <Stack spacing={1}>
                    {insightsToRender.length > 0 ? (
                      insightsToRender.map((insight) => (
                        <Box
                          key={insight}
                          sx={{
                            p: 1.25,
                            ...SOFT_PANEL_SX,
                            background: 'color-mix(in srgb, var(--pt-accent) 7%, var(--pt-surface-soft))',
                          }}
                        >
                          <Typography sx={{ fontSize: 13, color: 'var(--pt-text)' }}>{insight}</Typography>
                        </Box>
                      ))
                    ) : (
                      <Box
                        sx={{
                          p: 1.25,
                          ...SOFT_PANEL_SX,
                        }}
                      >
                        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                          Nenhum alerta crítico no momento. Conforme o histórico crescer, esta área destaca sinais automáticos de
                          risco, oportunidade e ritmo comercial.
                        </Typography>
                      </Box>
                    )}
                  </Stack>
                </Paper>
              </Box>
            </Box>

            <OperationSnapshotCard snapshot={snapshot} />

            <Paper
              variant="outlined"
              className="pt-reveal"
              sx={{
                p: { xs: 2, sm: 3 },
                ...ELEVATED_SURFACE_SX,
                animationDelay: '210ms',
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.25 }}>
                <AddBusinessOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-muted)' }} />
                <Typography sx={{ fontWeight: 600, fontSize: 16, color: 'var(--pt-text)' }}>
                  Vendas por mês
                </Typography>
              </Box>
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>
                Volume de vendas criadas em cada mês.
              </Typography>

              <SalesByMonthChart data={charts?.sales_by_month ?? null} isLoading={isLoading} />
            </Paper>

            <SeasonalityMatrixCard rows={charts?.seasonality_matrix ?? null} isLoading={isLoading} />
          </>
        )}
      </Stack>
    </Box>
  )
}
