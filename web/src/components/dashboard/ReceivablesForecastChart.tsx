import { Box, Skeleton, Typography, useTheme } from '@mui/material'
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
import type { SalesByMonthPoint } from '../../types/report'
import { formatCurrency, formatMonthLabel } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, BarElement, ChartTooltip)

interface ReceivablesForecastChartProps {
  data: SalesByMonthPoint[] | null
  isLoading: boolean
}

export function ReceivablesForecastChart({ data, isLoading }: ReceivablesForecastChartProps) {
  const { palette } = useTheme()
  const tokens = pegaticketTokens[palette.mode]

  const chartData = useMemo(() => {
    const points = data ?? []
    const lastIndex = points.length - 1

    return {
      labels: points.map((point) => formatMonthLabel(point.month)),
      datasets: [
        {
          data: points.map((point) => Number(point.total_amount)),
          backgroundColor: points.map((_, index) =>
            index === lastIndex ? tokens.warning : `color-mix(in srgb, ${tokens.accent} 76%, transparent)`,
          ),
          borderRadius: 6,
          maxBarThickness: 40,
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
          Nenhuma projeção de recebimento ainda
        </Typography>
        <Typography sx={{ fontSize: 13.5 }}>
          Assim que houver valores em aberto com vencimento, a projeção mensal aparece aqui.
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
                label: (item: TooltipItem<'bar'>) =>
                  `${formatCurrency(item.parsed.y ?? 0)} • ${data?.[item.dataIndex]?.count ?? 0} título${(data?.[item.dataIndex]?.count ?? 0) === 1 ? '' : 's'}`,
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
                callback: (value) => formatCurrency(Number(value ?? 0)),
              },
              grid: { color: `color-mix(in srgb, ${tokens.border} 70%, transparent)` },
            },
          },
        }}
      />
    </Box>
  )
}
