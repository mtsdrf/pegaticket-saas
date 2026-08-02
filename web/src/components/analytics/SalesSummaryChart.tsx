import { Box, useTheme } from '@mui/material'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  LinearScale,
  Tooltip as ChartTooltip,
  type TooltipItem,
} from 'chart.js'
import { useMemo } from 'react'
import { Bar } from 'react-chartjs-2'
import { pegaticketTokens } from '../../theme'
import type { SalesGroupBy, SalesSummaryBucket } from '../../types/analytics'
import { formatCurrency, formatMonthLabel } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, BarElement, ChartTooltip)

function formatBucketLabel(period: string, groupBy: SalesGroupBy): string {
  if (groupBy === 'month') return formatMonthLabel(period.slice(0, 7))
  const [, month, day] = period.slice(0, 10).split('-')
  return month && day ? `${day}/${month}` : period
}

interface SalesSummaryChartProps {
  buckets: SalesSummaryBucket[]
  groupBy: SalesGroupBy
}

/**
 * Faturamento por bucket (dia/mês) — série única na cor primária; qtd de
 * vendas e ticket médio aparecem no tooltip, nunca num segundo eixo.
 */
export function SalesSummaryChart({ buckets, groupBy }: SalesSummaryChartProps) {
  const { palette } = useTheme()
  const tokens = pegaticketTokens[palette.mode]

  const chartData = useMemo(
    () => ({
      labels: buckets.map((bucket) => formatBucketLabel(bucket.period, groupBy)),
      datasets: [
        {
          data: buckets.map((bucket) => bucket.total_amount),
          backgroundColor: `color-mix(in srgb, ${tokens.primary} 82%, transparent)`,
          hoverBackgroundColor: tokens.primary,
          borderRadius: 4,
          maxBarThickness: 36,
        },
      ],
    }),
    [buckets, groupBy, tokens],
  )

  return (
    <Box sx={{ height: 300 }}>
      <Bar
        data={chartData}
        options={{
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 400, easing: 'easeOutQuart' },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: tokens.surface,
              titleColor: tokens.text,
              bodyColor: tokens.muted,
              borderColor: tokens.border,
              borderWidth: 1,
              padding: 10,
              cornerRadius: 8,
              displayColors: false,
              callbacks: {
                label: (item: TooltipItem<'bar'>) => {
                  const bucket = buckets[item.dataIndex]
                  if (!bucket) return ''
                  return [
                    `Faturamento: ${formatCurrency(bucket.total_amount)}`,
                    `Vendas: ${bucket.count}`,
                    `Ticket médio: ${formatCurrency(bucket.average_ticket)}`,
                  ]
                },
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: {
                color: tokens.muted,
                font: { size: 12, family: 'Inter' },
                autoSkip: true,
                maxTicksLimit: 12,
                maxRotation: 0,
              },
            },
            y: {
              beginAtZero: true,
              ticks: {
                color: tokens.muted,
                font: { size: 12, family: 'Inter' },
                callback: (value) => formatCurrency(Number(value)),
                maxTicksLimit: 6,
              },
              grid: { color: `color-mix(in srgb, ${tokens.border} 70%, transparent)` },
            },
          },
        }}
      />
    </Box>
  )
}
