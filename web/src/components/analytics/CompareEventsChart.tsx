import { Box, useTheme } from '@mui/material'
import {
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip as ChartTooltip,
  type TooltipItem,
} from 'chart.js'
import { useMemo } from 'react'
import { Line } from 'react-chartjs-2'
import { pegaticketTokens } from '../../theme'
import type { CompareEventsEntry } from '../../types/analytics'
import { formatCurrency } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, ChartTooltip, Legend)

interface CompareEventsChartProps {
  events: CompareEventsEntry[]
}

/**
 * Curva de vendas acumuladas (receita) por evento, indexada por "dia N
 * desde a abertura de vendas" de CADA evento — nunca por data de
 * calendário (ver AnalyticsService::compareEvents no backend). Uma cor
 * qualitativa por evento (identidade garantida pela legenda).
 */
export function CompareEventsChart({ events }: CompareEventsChartProps) {
  const { palette } = useTheme()
  const tokens = pegaticketTokens[palette.mode]
  const seriesColors = useMemo(
    () => [tokens.primary, tokens.secondary, tokens.info, tokens.warning, tokens.danger],
    [tokens],
  )

  const maxDay = useMemo(
    () => Math.max(0, ...events.map((event) => event.series.at(-1)?.day ?? 0)),
    [events],
  )
  const labels = useMemo(() => Array.from({ length: maxDay + 1 }, (_, day) => day), [maxDay])

  const chartData = useMemo(
    () => ({
      labels: labels.map((day) => `Dia ${day}`),
      datasets: events.map((event, index) => {
        const byDay = new Map(event.series.map((point) => [point.day, point]))
        let running = 0
        const data = labels.map((day) => {
          const point = byDay.get(day)
          if (point) running += point.revenue
          return running
        })

        return {
          label: event.event_name,
          data,
          borderColor: seriesColors[index % seriesColors.length],
          backgroundColor: seriesColors[index % seriesColors.length],
          borderWidth: 2.5,
          pointRadius: 2,
          pointHoverRadius: 5,
          tension: 0.3,
        }
      }),
    }),
    [events, labels, seriesColors],
  )

  return (
    <Box sx={{ height: 340 }}>
      <Line
        data={chartData}
        options={{
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 400, easing: 'easeOutQuart' },
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                color: tokens.text,
                font: { size: 12, family: 'Inter' },
                usePointStyle: true,
                pointStyle: 'line',
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
                label: (item: TooltipItem<'line'>) => `${item.dataset.label}: ${formatCurrency(item.parsed.y ?? 0)} acumulado`,
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: tokens.muted, font: { size: 12, family: 'Inter' }, maxRotation: 0, maxTicksLimit: 12 },
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
