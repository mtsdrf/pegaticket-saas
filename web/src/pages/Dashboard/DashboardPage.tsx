import AddBusinessOutlinedIcon from '@mui/icons-material/AddBusinessOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import AccountBalanceWalletOutlinedIcon from '@mui/icons-material/AccountBalanceWalletOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import SellOutlinedIcon from '@mui/icons-material/SellOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import ShoppingCartCheckoutOutlinedIcon from '@mui/icons-material/ShoppingCartCheckoutOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import InsightsOutlinedIcon from '@mui/icons-material/InsightsOutlined'
import ArrowOutwardOutlinedIcon from '@mui/icons-material/ArrowOutwardOutlined'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { PageHeader } from '../../components/layout/PageHeader'
import { OnboardingChecklistCard } from '../../components/dashboard/OnboardingChecklistCard'
import { OrdersByMonthChart } from '../../components/dashboard/OrdersByMonthChart'
import { QuickActionCard } from '../../components/dashboard/QuickActionCard'
import { RankingListCard } from '../../components/dashboard/RankingListCard'
import { ReceivablesAgingCard } from '../../components/dashboard/ReceivablesAgingCard'
import { ReceivablesForecastChart } from '../../components/dashboard/ReceivablesForecastChart'
import { SeasonalityMatrixCard } from '../../components/dashboard/SeasonalityMatrixCard'
import { useDashboardReport } from '../../hooks/useDashboardReport'
import { useOnboardingChecklist } from '../../hooks/useOnboardingChecklist'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { PAGE_CONTAINER_SX, UI_RADIUS } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency, formatPercentage } from '../../utils/format'
import { presetRange } from '../../utils/period'
import { ACCESS } from '../../access/requirements'
import type { OperationHealthStageSummary } from '../../types/report'

const QUICK_ACTIONS = [
  { icon: ReceiptLongOutlinedIcon, label: 'Novo pedido', to: '/pedidos/novo' },
  { icon: Inventory2OutlinedIcon, label: 'Cadastrar evento', to: '/eventos/novo' },
]

const DEFAULT_RANGE = presetRange('this_month')

function formatOperationAge(totalMinutes: number | null): string {
  if (totalMinutes === null) return 'Sem fila agora'
  if (totalMinutes < 60) return `${totalMinutes} min`

  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  if (hours < 24) {
    return minutes > 0 ? `${hours}h${String(minutes).padStart(2, '0')}` : `${hours}h`
  }

  const days = Math.floor(hours / 24)
  const remainingHours = hours % 24

  return remainingHours > 0 ? `${days}d ${remainingHours}h` : `${days}d`
}

function stageDestination(stage: OperationHealthStageSummary['stage']): string {
  return `/pedidos?stage=${stage}&source=dashboard`
}

function OperationHealthStageCard({
  stage,
  index,
  canOpenQueue,
}: {
  stage: OperationHealthStageSummary
  index: number
  canOpenQueue: boolean
}) {
  const hasCritical = stage.critical > 0
  const hasAttention = stage.attention > 0
  const accent = hasCritical ? 'var(--pt-danger)' : hasAttention ? 'var(--pt-warning)' : 'var(--pt-primary)'
  const subtitle =
    stage.total === 0
      ? 'Nenhum item nesta etapa.'
      : `${stage.attention} em atenção • ${stage.critical} crítico${stage.critical === 1 ? '' : 's'}`

  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{
        p: { xs: 2, sm: 2.25 },
        ...ELEVATED_SURFACE_SX,
        animationDelay: `${index * 70}ms`,
        borderColor: `color-mix(in srgb, ${accent} 26%, var(--pt-border))`,
        background: `color-mix(in srgb, ${accent} 6%, var(--pt-surface))`,
      }}
    >
      <Stack spacing={1.25}>
        <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 1.5 }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', textTransform: 'uppercase', letterSpacing: 0.32 }}>
              {stage.label}
            </Typography>
            <Typography sx={{ fontSize: 28, lineHeight: 1.1, fontWeight: 700, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
              {stage.total}
            </Typography>
          </Box>
          <Box
            sx={{
              minWidth: 84,
              px: 1.25,
              py: 0.875,
              borderRadius: UI_RADIUS.lg,
              background: `color-mix(in srgb, ${accent} 12%, transparent)`,
              color: accent,
              textAlign: 'right',
            }}
          >
            <Typography sx={{ fontSize: 11.5, fontWeight: 700, letterSpacing: 0.28, textTransform: 'uppercase' }}>
              Mais antigo
            </Typography>
            <Typography sx={{ fontSize: 14, fontWeight: 700 }}>
              {formatOperationAge(stage.oldest_minutes)}
            </Typography>
          </Box>
        </Box>

        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
          {subtitle}
        </Typography>

        {canOpenQueue ? (
          <Box>
            <Button
              component={RouterLink}
              to={stageDestination(stage.stage)}
              size="small"
              variant={hasCritical || hasAttention ? 'contained' : 'text'}
              endIcon={<ArrowOutwardOutlinedIcon sx={{ fontSize: 16 }} />}
            >
              Abrir fila
            </Button>
          </Box>
        ) : null}
      </Stack>
    </Paper>
  )
}

export function DashboardPage() {
  const { can } = useAccessControl()
  const { isAccessProfileLoading } = useAuth()
  const canViewStats = can(ACCESS.dashboard)
  const canOpenOrdersQueue = can(ACCESS.ordersRead)
  const canOpenStorefrontQueue = can(ACCESS.storefrontOrdersRead)
  const [from, setFrom] = useState(DEFAULT_RANGE.from)
  const [to, setTo] = useState(DEFAULT_RANGE.to)
  const { indicators, charts, operationHealth, isLoading, error } = useDashboardReport(from, to, canViewStats)
  const { checklist, dismiss: dismissChecklist } = useOnboardingChecklist()
  const showOnboardingChecklist = checklist !== null && checklist.completed < checklist.total && !checklist.is_dismissed
  const growthCaption = indicators
    ? indicators.sales_growth_percentage === null
      ? 'Novo'
      : `${indicators.sales_growth_percentage > 0 ? '+' : ''}${indicators.sales_growth_percentage.toFixed(2)}% vs período anterior`
    : null
  const isFirstOrderEmptyState = !isLoading && !error && indicators !== null && indicators.total_orders === 0
  const quickActions = QUICK_ACTIONS.filter((action) => {
    if (action.to === '/pedidos/novo') return can(ACCESS.ordersCreate)
    if (action.to === '/eventos/novo') return can(ACCESS.eventsCreate)
    return true
  })
  const operationStages = operationHealth
    ? [
        operationHealth.internal.approval,
        operationHealth.internal.production,
        operationHealth.internal.dispatch,
        operationHealth.internal.financial_pending,
      ]
    : []
  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1600 }}>
      <PageHeader title="Visão geral" subtitle="Acompanhe os principais números da operação." accent />

      {canViewStats && (
        <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} defaultPreset="this_month" />
      )}

      {canViewStats && error && (
        <Alert severity="warning" variant="outlined" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      {/* spacing igual ao `gap` de todo grid de cards abaixo (1.5) — antes
          era {xs:3,sm:4}, maior que o gap horizontal entre cards da mesma
          linha, dando uma respiração vertical desproporcional entre seções. */}
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
              // auto-fit: nº de colunas segue a largura DISPONÍVEL (não o
              // breakpoint) — com o drawer permanente, sm pode ter só ~420px
              // úteis e 3 colunas fixas estourariam a página.
              gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 200px), 1fr))',
              gap: 1.5,
            }}
          >
            {quickActions.map((action, index) => (
              <QuickActionCard
                key={action.label}
                icon={action.icon}
                label={action.label}
                index={index}
                to={action.to}
              />
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
                Peça ao proprietário da empresa para liberar a permissão "Visão Geral" no seu perfil de acesso.
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
                    Seu painel começa a ganhar vida com o primeiro pedido
                  </Typography>
                  <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', maxWidth: 760 }}>
                    Ainda não há pedidos cadastrados nesta empresa. Assim que você lançar o primeiro pedido, os
                    indicadores, gráficos, rankings e previsões financeiras começam a ser preenchidos automaticamente.
                  </Typography>
                </Box>
              </Box>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}>
                {can(ACCESS.ordersCreate) ? (
                  <Button component={RouterLink} to="/pedidos/novo" variant="contained">
                    Fazer primeiro pedido
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

        <Box
          sx={{
            display: 'grid',
            // Contagem fixa (6 KPIs): colunas explícitas por breakpoint que
            // dividem exatamente a contagem — 1/2/3/6 nunca deixam card órfão
            // esticado numa linha própria (ver design-system.md).
            gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', md: 'repeat(3,1fr)' },
            gap: 1.5,
          }}
        >
          <MetricCard
            icon={ReceiptLongOutlinedIcon}
            label="Pedidos no período"
            value={indicators ? String(indicators.total_orders) : null}
            tone="primary"
            isLoading={isLoading}
            index={0}
          />
          <MetricCard
            icon={PaidOutlinedIcon}
            label="Faturamento"
            value={indicators ? formatCurrency(indicators.total_sales_amount) : null}
            caption={growthCaption}
            tone="accent"
            isLoading={isLoading}
            index={1}
          />
          <MetricCard
            icon={AccountBalanceWalletOutlinedIcon}
            label="Valor recebido"
            value={indicators ? formatCurrency(indicators.amount_received) : null}
            caption={indicators ? `${indicators.paid_orders} pedido${indicators.paid_orders === 1 ? '' : 's'} pago${indicators.paid_orders === 1 ? '' : 's'}` : null}
            tone="accent"
            isLoading={isLoading}
            index={2}
          />
          <MetricCard
            icon={LocalShippingOutlinedIcon}
            label="Pedidos não entregues"
            value={indicators ? String(indicators.undelivered_orders) : null}
            caption={indicators ? `${indicators.delivered_orders} já entregue${indicators.delivered_orders === 1 ? '' : 's'}` : null}
            tone={indicators && indicators.undelivered_orders > 0 ? 'warning' : 'primary'}
            isLoading={isLoading}
            index={3}
          />
          <MetricCard
            icon={SellOutlinedIcon}
            label="Ticket médio"
            value={indicators ? formatCurrency(indicators.average_ticket) : null}
            caption={indicators ? `${indicators.delivered_orders} entregues e ${indicators.paid_orders} pagos` : null}
            tone="primary"
            isLoading={isLoading}
            index={4}
          />
        </Box>

        <Paper
          variant="outlined"
          className="pt-reveal"
          sx={{
            p: { xs: 2, sm: 3 },
            ...ELEVATED_SURFACE_SX,
            animationDelay: '180ms',
          }}
        >
          <Stack spacing={2}>
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, flexWrap: 'wrap' }}>
              <Box>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.25 }}>
                  <InsightsOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-muted)' }} />
                  <Typography sx={{ fontWeight: 600, fontSize: 16, color: 'var(--pt-text)' }}>
                    Saúde da operação
                  </Typography>
                </Box>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  Filas internas que precisam de acompanhamento agora.
                </Typography>
              </Box>

              {operationHealth ? (
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                  <Box
                    sx={{
                      px: 1.5,
                      py: 1,
                      borderRadius: UI_RADIUS.pill,
                      background: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
                      color: 'var(--pt-warning)',
                    }}
                  >
                    <Typography sx={{ fontSize: 12, fontWeight: 700 }}>
                      {operationHealth.totals.items_requiring_attention} item(ns) em atenção
                    </Typography>
                  </Box>
                  <Box
                    sx={{
                      px: 1.5,
                      py: 1,
                      borderRadius: UI_RADIUS.pill,
                      background: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)',
                      color: 'var(--pt-danger)',
                    }}
                  >
                    <Typography sx={{ fontSize: 12, fontWeight: 700 }}>
                      {operationHealth.totals.critical_items} crítico(s)
                    </Typography>
                  </Box>
                </Stack>
              ) : null}
            </Box>

            {operationHealth && operationHealth.totals.critical_items > 0 ? (
              <Alert severity="error" variant="outlined">
                Existem {operationHealth.totals.critical_items} item(ns) críticos entre aprovação, produção,
                expedição e financeiro.
                {canOpenOrdersQueue ? (
                  <Button
                    component={RouterLink}
                    to="/pedidos?stage=approval&source=dashboard"
                    size="small"
                    color="error"
                    sx={{ ml: 1, mt: { xs: 1, sm: 0 } }}
                    endIcon={<ArrowOutwardOutlinedIcon sx={{ fontSize: 16 }} />}
                  >
                    Abrir operação
                  </Button>
                ) : null}
              </Alert>
            ) : null}

            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))', xl: 'repeat(4, minmax(0, 1fr))' },
                gap: 1.5,
              }}
            >
              {isLoading && operationStages.length === 0
                ? Array.from({ length: 4 }).map((_, index) => (
                    <Paper key={index} variant="outlined" sx={{ p: 2.25, ...ELEVATED_SURFACE_SX }}>
                      <Stack spacing={1.25}>
                        <CircularProgress size={22} sx={{ alignSelf: 'flex-start' }} />
                        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Carregando etapa...</Typography>
                      </Stack>
                    </Paper>
                  ))
                : operationStages.map((stage, index) => (
                    <OperationHealthStageCard
                      key={stage.stage}
                      stage={stage}
                      index={index}
                      canOpenQueue={canOpenOrdersQueue}
                    />
                  ))}
            </Box>

            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: 'minmax(0, 1fr)' },
                gap: 1.5,
              }}
            >
              <Paper
                variant="outlined"
                className="pt-reveal"
                sx={{ p: { xs: 2, sm: 2.25 }, ...ELEVATED_SURFACE_SX }}
              >
                <Stack spacing={1.25}>
                  <Box>
                    <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', textTransform: 'uppercase', letterSpacing: 0.32 }}>
                      Loja online
                    </Typography>
                    <Typography sx={{ fontSize: 18, fontWeight: 700, color: 'var(--pt-text)' }}>
                      {operationHealth ? operationHealth.internal.approval.total : 0} pedido(s) aguardando aprovação
                    </Typography>
                  </Box>
                  <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                    Acompanhe os pedidos recebidos pela loja que ainda dependem de aprovação manual.
                  </Typography>
                  {canOpenStorefrontQueue ? (
                    <Box>
                      <Button
                        component={RouterLink}
                        to="/pedidos-loja?stage=approval&source=dashboard"
                        size="small"
                        variant="text"
                        endIcon={<ArrowOutwardOutlinedIcon sx={{ fontSize: 16 }} />}
                      >
                        Abrir board da loja
                      </Button>
                    </Box>
                  ) : null}
                </Stack>
              </Paper>
            </Box>
          </Stack>
        </Paper>

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
              Pedidos por mês
            </Typography>
          </Box>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>
            Volume de pedidos criados em cada mês.
          </Typography>

          <OrdersByMonthChart data={charts?.orders_by_month ?? null} isLoading={isLoading} />
        </Paper>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'repeat(2, minmax(0, 1fr))' },
            gap: 1.5,
          }}
        >
          <RankingListCard
            title="Vendas por cidade"
            subtitle="Cidades com maior faturamento no período."
            isLoading={isLoading}
            items={
              charts?.orders_by_city?.map((item) => ({
                title: item.city_name,
                value: formatCurrency(item.total_amount),
                meta: `${item.count} pedido${item.count === 1 ? '' : 's'}`,
              })) ?? null
            }
            emptyTitle="Nenhuma cidade com vendas ainda"
            emptyDescription="Quando os pedidos entrarem, o ranking por cidade aparece aqui."
          />

          <RankingListCard
            title="Vendas por bairro"
            subtitle="Bairros com maior faturamento no período."
            isLoading={isLoading}
            items={
              charts?.orders_by_neighborhood?.map((item) => ({
                title: item.neighborhood_name,
                value: formatCurrency(item.total_amount),
                meta: `${item.count} pedido${item.count === 1 ? '' : 's'}`,
              })) ?? null
            }
            emptyTitle="Nenhum bairro com vendas ainda"
            emptyDescription="Quando os pedidos entrarem, o ranking por bairro aparece aqui."
          />
        </Box>

        <SeasonalityMatrixCard rows={charts?.seasonality_matrix ?? null} isLoading={isLoading} />

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'repeat(2, minmax(0, 1fr))' },
            gap: 1.5,
          }}
        >
          <RankingListCard
            title="Produtos campeões"
            subtitle="Itens com maior faturamento no período."
            isLoading={isLoading}
            items={
              charts?.top_products?.map((item) => ({
                title: item.product_name,
                value: formatCurrency(item.revenue),
                meta: `${item.quantity_sold} vendidos`,
              })) ?? null
            }
            emptyTitle="Nenhum produto vendido ainda"
            emptyDescription="Assim que os pedidos começarem a gerar itens, o ranking aparece aqui."
          />

          <RankingListCard
            title="Melhores clientes"
            subtitle="Clientes com maior volume financeiro no período."
            isLoading={isLoading}
            items={
              charts?.top_clients?.map((item) => ({
                title: item.client_name,
                value: formatCurrency(item.total_amount),
                meta: `${item.order_count} pedido${item.order_count === 1 ? '' : 's'}`,
              })) ?? null
            }
            emptyTitle="Nenhum cliente no ranking ainda"
            emptyDescription="Quando houver pedidos válidos no período, o ranking financeiro dos clientes aparece aqui."
          />
        </Box>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'repeat(3, minmax(0, 1fr))' },
            gap: 1.5,
          }}
        >
          <RankingListCard
            title="Clientes por RFM"
            subtitle="Recência, frequência e valor dos clientes mais relevantes."
            isLoading={isLoading}
            items={
              charts?.rfm_clients?.map((item) => ({
                title: item.client_name,
                value: formatCurrency(item.monetary),
                meta: `${item.segment} • ${item.frequency} pedido${item.frequency === 1 ? '' : 's'} • há ${item.recency_days} dia${item.recency_days === 1 ? '' : 's'}`,
              })) ?? null
            }
            emptyTitle="Nenhum cliente ranqueado ainda"
            emptyDescription="Quando houver histórico de compras, o recorte RFM aparece aqui."
          />

          <RankingListCard
            title="Clientes que demoram a pagar"
            subtitle="Tempo médio entre entrega e pagamento."
            isLoading={isLoading}
            items={
              charts?.late_payment_clients?.map((item) => ({
                title: item.client_name,
                value: `${item.avg_days_to_pay} dia${item.avg_days_to_pay === 1 ? '' : 's'}`,
                meta: `${item.paid_orders_count} pedido${item.paid_orders_count === 1 ? '' : 's'} pagos`,
              })) ?? null
            }
            emptyTitle="Nenhum prazo de pagamento medido ainda"
            emptyDescription="Assim que houver pedidos entregues e pagos, o ranking aparece aqui."
          />

          <RankingListCard
            title="Pedidos mais atrasados"
            subtitle="Títulos vencidos com maior tempo em aberto."
            isLoading={isLoading}
            items={
              charts?.overdue_orders?.map((item) => ({
                title: item.client_name,
                value: `${item.days_overdue} dia${item.days_overdue === 1 ? '' : 's'}`,
                meta: `${item.source === 'installment' ? 'Parcela' : 'Pedido'} • vence em ${item.due_date} • ${formatCurrency(item.amount)}`,
              })) ?? null
            }
            emptyTitle="Nenhum atraso financeiro agora"
            emptyDescription="Quando existir pedido ou parcela vencida, o ranking aparece aqui."
          />
        </Box>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)', xl: 'repeat(3, minmax(0, 1fr))' },
            gap: 1.5,
          }}
        >
          <ReceivablesAgingCard buckets={charts?.receivables_aging ?? null} isLoading={isLoading} />

          <RankingListCard
            title="Curva ABC de produtos"
            subtitle="Participação dos itens no faturamento acumulado."
            isLoading={isLoading}
            items={
              charts?.abc_products?.map((item) => ({
                title: item.product_name,
                value: item.curve_class,
                meta: `${formatCurrency(item.revenue)} • ${formatPercentage(item.participation_percentage)} do faturamento • acum. ${formatPercentage(item.cumulative_percentage)}`,
              })) ?? null
            }
            emptyTitle="Nenhum produto na curva ABC ainda"
            emptyDescription="Quando houver vendas com itens, a classificação aparece aqui."
          />

          <RankingListCard
            title="Curva ABC de clientes"
            subtitle="Participação dos clientes no faturamento acumulado."
            isLoading={isLoading}
            items={
              charts?.abc_clients?.map((item) => ({
                title: item.client_name,
                value: item.curve_class,
                meta: `${formatCurrency(item.revenue)} • ${formatPercentage(item.participation_percentage)} do faturamento • acum. ${formatPercentage(item.cumulative_percentage)}`,
              })) ?? null
            }
            emptyTitle="Nenhum cliente na curva ABC ainda"
            emptyDescription="Quando houver faturamento no período, a classificação aparece aqui."
          />
        </Box>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)' },
            gap: 1.5,
          }}
        >
          <Paper
            variant="outlined"
            className="pt-reveal"
            sx={{
              p: { xs: 2, sm: 3 },
              ...ELEVATED_SURFACE_SX,
            }}
          >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.25 }}>
              <PaidOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-muted)' }} />
              <Typography sx={{ fontWeight: 600, fontSize: 16, color: 'var(--pt-text)' }}>
                Projeção de recebimentos
              </Typography>
            </Box>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>
              Valores em aberto agrupados por mês de vencimento.
            </Typography>

            <ReceivablesForecastChart data={charts?.receivables_forecast_by_month ?? null} isLoading={isLoading} />
          </Paper>
        </Box>
        </>
        )}
      </Stack>
    </Box>
  )
}
