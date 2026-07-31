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
import { maskatsTokens } from '../../theme'
import type { SalesHistoryYear } from '../../types/analytics'
import { formatCurrency } from '../../utils/format'

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, ChartTooltip, Legend)

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']
const MAX_YEARS = 4

interface SeasonalityLinesChartProps {
  rows: SalesHistoryYear[]
}

/**
 * Faturamento mensal comparado entre anos (forma "ênfase": o ano mais
 * recente na cor primária, anos anteriores em cinzas decrescentes —
 * identidade garantida pela legenda, nunca só pela cor).
 */
export function SeasonalityLinesChart({ rows }: SeasonalityLinesChartProps) {
  const { palette } = useTheme()
  const tokens = maskatsTokens[palette.mode]

  const years = useMemo(
    () => [...rows].sort((a, b) => b.year - a.year).slice(0, MAX_YEARS),
    [rows],
  )

  const chartData = useMemo(() => {
    const contextMixes = [88, 62, 40]

    return {
      labels: MONTH_LABELS,
      datasets: years.map((row, index) => {
        const isCurrent = index === 0
        const color = isCurrent
          ? tokens.primary
          : `color-mix(in srgb, ${tokens.muted} ${contextMixes[index - 1] ?? 40}%, transparent)`
        const byMonth = new Map(row.months.map((month) => [month.month, month]))

        return {
          label: String(row.year),
          data: MONTH_LABELS.map((_, monthIndex) => byMonth.get(monthIndex + 1)?.total_amount ?? 0),
          borderColor: color,
          backgroundColor: color,
          borderWidth: isCurrent ? 2.5 : 2,
          pointRadius: isCurrent ? 3 : 2,
          pointHoverRadius: 5,
          tension: 0.3,
        }
      }),
    }
  }, [years, tokens])

  return (
    <Box sx={{ height: 320 }}>
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
                label: (item: TooltipItem<'line'>) => {
                  const row = years[item.datasetIndex]
                  const month = row?.months.find((entry) => entry.month === item.dataIndex + 1)
                  const count = month?.count ?? 0
                  return `${item.dataset.label}: ${formatCurrency(item.parsed.y ?? 0)} • ${count} pedido${count === 1 ? '' : 's'}`
                },
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: tokens.muted, font: { size: 12, family: 'Inter' }, maxRotation: 0 },
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
