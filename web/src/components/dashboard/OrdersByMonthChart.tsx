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
import { maskatsTokens } from '../../theme'
import type { OrdersByMonthPoint } from '../../types/report'
import { formatMonthLabel } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, BarElement, ChartTooltip)

interface OrdersByMonthChartProps {
  data: OrdersByMonthPoint[] | null
  isLoading: boolean
}

export function OrdersByMonthChart({ data, isLoading }: OrdersByMonthChartProps) {
  const { palette } = useTheme()
  const tokens = maskatsTokens[palette.mode]

  const chartData = useMemo(() => {
    const points = data ?? []
    const lastIndex = points.length - 1

    return {
      labels: points.map((point) => formatMonthLabel(point.month)),
      datasets: [
        {
          data: points.map((point) => point.count),
          backgroundColor: points.map((_, index) =>
            index === lastIndex ? tokens.accent : `color-mix(in srgb, ${tokens.primary} 78%, transparent)`,
          ),
          borderRadius: 6,
          maxBarThickness: 40,
        },
      ],
    }
  }, [data, tokens])

  if (isLoading) {
    return <Skeleton variant="rounded" height={260} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
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
          color: 'var(--mk-muted)',
          textAlign: 'center',
          px: 2,
        }}
      >
        <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', fontSize: 14.5 }}>
          Nenhum pedido neste período ainda
        </Typography>
        <Typography sx={{ fontSize: 13.5 }}>
          Assim que os primeiros pedidos forem criados, o histórico mensal aparece aqui.
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
                  `${item.formattedValue} pedido${item.parsed.y === 1 ? '' : 's'} • ${data?.[item.dataIndex]?.total_amount ?? '0,00'}`,
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
              ticks: { color: tokens.muted, font: { size: 12, family: 'Inter' }, precision: 0 },
              grid: { color: `color-mix(in srgb, ${tokens.border} 70%, transparent)` },
            },
          },
        }}
      />
    </Box>
  )
}
