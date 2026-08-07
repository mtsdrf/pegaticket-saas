import { Box, Skeleton, Typography, useTheme } from '@mui/material'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend as ChartLegend,
  LinearScale,
  Tooltip as ChartTooltip,
  type TooltipItem,
} from 'chart.js'
import { useMemo } from 'react'
import { Bar } from 'react-chartjs-2'
import { pegaticketTokens } from '../../theme'
import type { SalesByMonthPoint } from '../../types/report'
import { formatMonthLabel } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, BarElement, ChartTooltip, ChartLegend)

interface SalesByMonthChartProps {
  data: SalesByMonthPoint[] | null
  isLoading: boolean
}

export function SalesByMonthChart({ data, isLoading }: SalesByMonthChartProps) {
  const { palette } = useTheme()
  const tokens = pegaticketTokens[palette.mode]

  const chartData = useMemo(() => {
    const points = data ?? []
    const lastIndex = points.length - 1

    return {
      labels: points.map((point) => formatMonthLabel(point.month)),
      datasets: [
        {
          label: 'Manual',
          data: points.map((point) => point.manual_count),
          backgroundColor: points.map((_, index) =>
            index === lastIndex ? tokens.primary : 'rgb(17, 61, 52)',
          ),
          borderRadius: 0,
          maxBarThickness: 40,
          stack: 'sales',
        },
        {
          label: 'Online',
          data: points.map((point) => point.online_count),
          backgroundColor: points.map((_, index) =>
            index === lastIndex ? tokens.accent : 'rgba(15, 118, 110, 0.85)',
          ),
          borderRadius: 0,
          maxBarThickness: 40,
          stack: 'sales',
        },
      ],
    }
  }, [data, tokens])

  if (isLoading) {
    return <Skeleton variant="rounded" height={260} sx={{ borderRadius: 'var(--pt-radius-md)' }} />
  }

  if (!data || data.length === 0) {
    return (
      <Box
        sx={{
          height: 260,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 0.5,
          color: 'var(--pt-muted)',
          textAlign: 'center',
          px: 2,
        }}
      >
        <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
          Nenhuma venda neste período ainda
        </Typography>
        <Typography sx={{ fontSize: 13.5 }}>
          Assim que as primeiras vendas forem criadas, o histórico mensal aparece aqui.
        </Typography>
      </Box>
    )
  }

  return (
    <Box sx={{ height: 260 }}>
      <Bar
        data={chartData}
        options={{
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 400, easing: 'easeOutQuart' },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              align: 'start',
              labels: {
                color: tokens.muted,
                boxWidth: 12,
                boxHeight: 12,
                useBorderRadius: true,
                borderRadius: 4,
                font: { size: 12, family: 'Inter' },
              },
            },
            tooltip: {
              backgroundColor: tokens.surface,
              titleColor: tokens.text,
              bodyColor: tokens.muted,
              borderColor: tokens.border,
              borderWidth: 1,
              padding: 10,
              cornerRadius: 8,
              callbacks: {
                label: (item: TooltipItem<'bar'>) =>
                  `${item.dataset.label}: ${item.formattedValue} venda${item.parsed.y === 1 ? '' : 's'} • ${
                    item.dataset.label === 'Manual'
                      ? data?.[item.dataIndex]?.manual_total_amount ?? '0,00'
                      : data?.[item.dataIndex]?.online_total_amount ?? '0,00'
                  }`,
                footer: (items) => {
                  const point = data?.[items[0]?.dataIndex ?? -1]
                  if (!point) return ''
                  return `Total do mês: ${point.count} vendas • ${point.total_amount}`
                },
              },
            },
          },
          scales: {
            x: {
              stacked: true,
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
              stacked: true,
              beginAtZero: true,
              ticks: { color: tokens.muted, font: { size: 12, family: 'Inter' }, precision: 0 },
              grid: { color: `color-mix(in srgb, ${tokens.border} 70%, transparent)` },
            },
          },
        }}
      />
    </Box>
  )
}
