import { Box, Tab, Tabs } from '@mui/material'
import { useCallback, useState } from 'react'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { PageHeader } from '../../components/layout/PageHeader'
import { PlanUpgradeNotice } from '../../components/plan/PlanUpgradeNotice'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { presetRange } from '../../utils/period'
import { AffiliatesTab } from './tabs/AffiliatesTab'
import { ClientsTab } from './tabs/ClientsTab'
import { CohortsTab } from './tabs/CohortsTab'
import { CompareEventsTab } from './tabs/CompareEventsTab'
import { CouponsTab } from './tabs/CouponsTab'
import { EventAffinityTab } from './tabs/EventAffinityTab'
import { FunnelTab } from './tabs/FunnelTab'
import { InventoryTab } from './tabs/InventoryTab'
import { LtvTab } from './tabs/LtvTab'
import { OperatorsTab } from './tabs/OperatorsTab'
import { OverviewTab } from './tabs/OverviewTab'
import { PaymentsTab } from './tabs/PaymentsTab'
import { ProductsTab } from './tabs/ProductsTab'
import { RefundsTab } from './tabs/RefundsTab'
import { ResaleTab } from './tabs/ResaleTab'
import { RiskTab } from './tabs/RiskTab'
import { AccessTab } from './tabs/AccessTab'
import { SalesByDimensionTab } from './tabs/SalesByDimensionTab'
import { SeasonalityTab } from './tabs/SeasonalityTab'

type AnalyticsTabKey =
  | 'overview'
  | 'products'
  | 'sales-by-dimension'
  | 'payments'
  | 'access'
  | 'seasonality'
  | 'clients'
  | 'affiliates'
  | 'coupons'
  | 'refunds'
  | 'inventory'
  | 'funnel'
  | 'compare-events'
  | 'risk'
  | 'resale'
  | 'operators'
  | 'cohorts'
  | 'ltv'
  | 'event-affinity'

const TABS: { key: AnalyticsTabKey; label: string }[] = [
  { key: 'overview', label: 'Financeiro' },
  { key: 'products', label: 'Produtos' },
  { key: 'sales-by-dimension', label: 'Vendas por dimensão' },
  { key: 'payments', label: 'Pagamentos' },
  { key: 'access', label: 'Acesso' },
  { key: 'seasonality', label: 'Sazonalidade' },
  { key: 'clients', label: 'Clientes' },
  { key: 'affiliates', label: 'Afiliados' },
  { key: 'coupons', label: 'Cupons' },
  { key: 'refunds', label: 'Reembolsos' },
  { key: 'inventory', label: 'Inventário' },
  { key: 'funnel', label: 'Funil' },
  { key: 'compare-events', label: 'Comparar eventos' },
  { key: 'risk', label: 'Antifraude' },
  { key: 'resale', label: 'Revenda' },
  { key: 'operators', label: 'Operadores' },
  { key: 'cohorts', label: 'Coortes' },
  { key: 'ltv', label: 'LTV' },
  { key: 'event-affinity', label: 'Afinidade' },
]

const DEFAULT_RANGE = presetRange('last_12_months')

export function AnalyticsPage() {
  const [activeTab, setActiveTab] = useState<AnalyticsTabKey>('overview')
  // Abas já visitadas ficam montadas (ocultas) — trocar de aba não refaz
  // as consultas, que são pesadas (agregações sobre todo o histórico).
  const [visitedTabs, setVisitedTabs] = useState<Set<AnalyticsTabKey>>(new Set(['overview']))
  const [from, setFrom] = useState(DEFAULT_RANGE.from)
  const [to, setTo] = useState(DEFAULT_RANGE.to)
  const [planLocked, setPlanLocked] = useState(false)

  const handlePlanLocked = useCallback(() => setPlanLocked(true), [])

  function handleTabChange(_: unknown, value: AnalyticsTabKey) {
    setActiveTab(value)
    setVisitedTabs((current) => (current.has(value) ? current : new Set(current).add(value)))
  }

  function handlePeriodChange(nextFrom: string, nextTo: string) {
    setFrom(nextFrom)
    setTo(nextTo)
  }

  const tabProps = { from, to, onPlanLocked: handlePlanLocked }

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1600 }}>
      <PageHeader
        title="Análises"
        subtitle="Explore financeiro, ingressos, acesso e clientes da operação."
      />

      {planLocked ? (
        <PlanUpgradeNotice featureLabel="as análises avançadas" />
      ) : (
        <>
          <PeriodFilter
            from={from}
            to={to}
            onChange={handlePeriodChange}
            disabled={
              activeTab === 'seasonality' ||
              activeTab === 'inventory' ||
              activeTab === 'compare-events' ||
              activeTab === 'cohorts' ||
              activeTab === 'ltv' ||
              activeTab === 'event-affinity'
            }
            disabledHint={
              activeTab === 'inventory'
                ? 'Inventário usa o evento selecionado, não período'
                : activeTab === 'compare-events'
                  ? 'Comparação usa os eventos selecionados, não período'
                  : activeTab === 'cohorts'
                    ? 'Coortes usa o mês de coorte selecionado, não período'
                    : activeTab === 'ltv'
                      ? 'LTV histórico é vitalício, não usa período'
                      : activeTab === 'event-affinity'
                        ? 'Afinidade usa o evento selecionado, não período'
                        : 'Sazonalidade usa o histórico completo'
            }
          />

          <Tabs
            value={activeTab}
            onChange={handleTabChange}
            variant="scrollable"
            scrollButtons="auto"
            allowScrollButtonsMobile
            aria-label="Abas de análise"
            sx={{
              mb: 2.5,
              borderBottom: '1px solid var(--pt-border)',
              '& .MuiTab-root': { minHeight: UI_SIZE.compactControl, borderRadius: UI_RADIUS.sm, textTransform: 'none' },
            }}
          >
            {TABS.map((tab) => (
              <Tab key={tab.key} value={tab.key} label={tab.label} id={`analytics-tab-${tab.key}`} aria-controls={`analytics-tabpanel-${tab.key}`} />
            ))}
          </Tabs>

          {TABS.map((tab) => {
            if (!visitedTabs.has(tab.key)) return null
            return (
              <Box
                key={tab.key}
                role="tabpanel"
                id={`analytics-tabpanel-${tab.key}`}
                aria-labelledby={`analytics-tab-${tab.key}`}
                hidden={activeTab !== tab.key}
              >
                {tab.key === 'overview' && <OverviewTab {...tabProps} />}
                {tab.key === 'products' && <ProductsTab {...tabProps} />}
                {tab.key === 'sales-by-dimension' && <SalesByDimensionTab {...tabProps} />}
                {tab.key === 'payments' && <PaymentsTab {...tabProps} />}
                {tab.key === 'access' && <AccessTab {...tabProps} />}
                {tab.key === 'seasonality' && <SeasonalityTab onPlanLocked={handlePlanLocked} />}
                {tab.key === 'clients' && <ClientsTab {...tabProps} />}
                {tab.key === 'affiliates' && <AffiliatesTab {...tabProps} />}
                {tab.key === 'coupons' && <CouponsTab {...tabProps} />}
                {tab.key === 'refunds' && <RefundsTab {...tabProps} />}
                {tab.key === 'inventory' && <InventoryTab />}
                {tab.key === 'funnel' && <FunnelTab {...tabProps} />}
                {tab.key === 'compare-events' && <CompareEventsTab />}
                {tab.key === 'risk' && <RiskTab {...tabProps} />}
                {tab.key === 'resale' && <ResaleTab {...tabProps} />}
                {tab.key === 'operators' && <OperatorsTab {...tabProps} />}
                {tab.key === 'cohorts' && <CohortsTab />}
                {tab.key === 'ltv' && <LtvTab />}
                {tab.key === 'event-affinity' && <EventAffinityTab />}
              </Box>
            )
          })}
        </>
      )}
    </Box>
  )
}
