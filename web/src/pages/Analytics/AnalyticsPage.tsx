import { Box, Tab, Tabs } from '@mui/material'
import { useCallback, useState } from 'react'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { PageHeader } from '../../components/layout/PageHeader'
import { PlanUpgradeNotice } from '../../components/plan/PlanUpgradeNotice'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { presetRange } from '../../utils/period'
import { ClientsTab } from './tabs/ClientsTab'
import { LocationsTab } from './tabs/LocationsTab'
import { OverdueTab } from './tabs/OverdueTab'
import { OverviewTab } from './tabs/OverviewTab'
import { PaymentsTab } from './tabs/PaymentsTab'
import { ProductsTab } from './tabs/ProductsTab'
import { AccessTab } from './tabs/AccessTab'
import { SalesByDimensionTab } from './tabs/SalesByDimensionTab'
import { SeasonalityTab } from './tabs/SeasonalityTab'

type AnalyticsTabKey =
  | 'overview'
  | 'products'
  | 'sales-by-dimension'
  | 'payments'
  | 'access'
  | 'locations'
  | 'seasonality'
  | 'clients'
  | 'overdue'

const TABS: { key: AnalyticsTabKey; label: string }[] = [
  { key: 'overview', label: 'Financeiro' },
  { key: 'products', label: 'Produtos' },
  { key: 'sales-by-dimension', label: 'Vendas por dimensão' },
  { key: 'payments', label: 'Pagamentos' },
  { key: 'access', label: 'Acesso' },
  { key: 'locations', label: 'Locais' },
  { key: 'seasonality', label: 'Sazonalidade' },
  { key: 'clients', label: 'Clientes' },
  { key: 'overdue', label: 'Atrasos' },
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
        subtitle="Explore financeiro, ingressos, acesso, locais, clientes e atrasos da operação."
      />

      {planLocked ? (
        <PlanUpgradeNotice featureLabel="as análises avançadas" />
      ) : (
        <>
          <PeriodFilter
            from={from}
            to={to}
            onChange={handlePeriodChange}
            disabled={activeTab === 'seasonality'}
            disabledHint="Sazonalidade usa o histórico completo"
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
                {tab.key === 'locations' && <LocationsTab {...tabProps} />}
                {tab.key === 'seasonality' && <SeasonalityTab onPlanLocked={handlePlanLocked} />}
                {tab.key === 'clients' && <ClientsTab {...tabProps} />}
                {tab.key === 'overdue' && <OverdueTab {...tabProps} />}
              </Box>
            )
          })}
        </>
      )}
    </Box>
  )
}
