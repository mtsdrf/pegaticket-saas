import { Tab, Tabs } from '@mui/material'
import { BILLING_PERIODS, type BillingPeriod } from '../utils/pricing'

interface BillingPeriodTabsProps {
  period: BillingPeriod
  onChange: (period: BillingPeriod) => void
}

/**
 * Toggle mensal/trimestral/anual reutilizado pela seção de planos da home
 * (`sections/Plans.tsx`) e pela página dedicada de preços (`pages/PrecosPage.tsx`).
 */
export function BillingPeriodTabs({ period, onChange }: BillingPeriodTabsProps) {
  return (
    <Tabs
      value={period}
      onChange={(_, value) => onChange(value)}
      aria-label="Período de cobrança"
      variant="scrollable"
      scrollButtons={false}
      allowScrollButtonsMobile
      sx={{
        minHeight: 40,
        maxWidth: '100%',
        border: '1px solid var(--mk-border)',
        borderRadius: 'var(--mk-radius-md)',
        backgroundColor: 'var(--mk-surface)',
        p: 0.5,
        '& .MuiTabs-indicator': { display: 'none' },
        '& .MuiTabs-flexContainer': { gap: 0.25 },
      }}
    >
      {(Object.keys(BILLING_PERIODS) as BillingPeriod[]).map((key) => (
        <Tab
          key={key}
          value={key}
          label={
            key === 'monthly'
              ? BILLING_PERIODS[key].label
              : `${BILLING_PERIODS[key].label} (−${BILLING_PERIODS[key].discountPercent}%)`
          }
          sx={{
            minHeight: 44,
            minWidth: 0,
            px: { xs: 1.25, sm: 2 },
            fontSize: { xs: 12.5, sm: 14 },
            whiteSpace: 'nowrap',
            borderRadius: 'var(--mk-radius-sm)',
            '&.Mui-selected': { backgroundColor: 'var(--mk-primary)', color: '#FFFFFF' },
          }}
        />
      ))}
    </Tabs>
  )
}
