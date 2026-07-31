export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function daysBetween(from: string, to: string): number {
  const start = new Date(`${from}T00:00:00`)
  const end = new Date(`${to}T00:00:00`)
  const diff = Math.round((end.getTime() - start.getTime()) / 86_400_000)
  return Number.isFinite(diff) ? diff : 0
}

export type PeriodPreset = 'this_month' | 'last_30' | 'last_90' | 'last_12_months' | 'this_year' | 'custom'

export function presetRange(preset: Exclude<PeriodPreset, 'custom'>): { from: string; to: string } {
  const today = new Date()
  const to = toIsoDate(today)

  if (preset === 'this_month') {
    return { from: toIsoDate(new Date(today.getFullYear(), today.getMonth(), 1)), to }
  }

  if (preset === 'last_30') {
    const from = new Date(today)
    from.setDate(from.getDate() - 29)
    return { from: toIsoDate(from), to }
  }

  if (preset === 'last_90') {
    const from = new Date(today)
    from.setDate(from.getDate() - 89)
    return { from: toIsoDate(from), to }
  }

  if (preset === 'this_year') {
    return { from: toIsoDate(new Date(today.getFullYear(), 0, 1)), to }
  }

  const from = new Date(today.getFullYear(), today.getMonth() - 11, 1)
  return { from: toIsoDate(from), to }
}
