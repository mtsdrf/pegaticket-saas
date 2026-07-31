import type { StoreBusinessHourDay } from '../types/storeBusinessHours'

/**
 * Mesma regra do guard `StoreBusinessHoursService::isOpenNow()` do backend,
 * calculada client-side a partir do `business_hours` já devolvido por
 * `GET /loja/{slug}` (evita round-trip extra só pro badge do catálogo).
 * `opens_at`/`closes_at` podem vir como `HH:mm` ou `HH:mm:ss` (coluna
 * `TIME` sem cast no backend) — compara sempre só os 5 primeiros
 * caracteres (`HH:mm`).
 *
 * Suporta horário que atravessa a meia-noite (`closes_at < opens_at`, ex.:
 * abre 20:00 fecha 03:00) — precisa olhar o registro de HOJE (cobre "já
 * abriu hoje à noite") e o de ONTEM (cobre "ainda dentro da madrugada de
 * ontem"), cada um só pelo lado que faz sentido pra ele. Ver comentário
 * espelhado em `StoreBusinessHoursService::isOpenNow()` (backend) — mesma
 * lógica, mantida sincronizada a propósito.
 */
export function isStoreOpenNow(businessHours: StoreBusinessHourDay[], now: Date = new Date()): boolean {
  const current = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

  const shiftsOf = (dayOfWeek: number) =>
    businessHours.filter(
      (day) => day.day_of_week === dayOfWeek && !day.is_closed && day.opens_at && day.closes_at,
    )

  // Cada dia pode ter vários turnos agora — a loja está aberta se o horário
  // atual cai em QUALQUER turno de hoje (ou ainda dentro de um turno de ontem
  // que atravessa a meia-noite).
  for (const shift of shiftsOf(now.getDay())) {
    const opensAt = shift.opens_at!.slice(0, 5)
    const closesAt = shift.closes_at!.slice(0, 5)
    const crossesMidnight = closesAt <= opensAt

    if (crossesMidnight) {
      if (current >= opensAt) return true
    } else if (current >= opensAt && current <= closesAt) {
      return true
    }
  }

  const yesterdayIndex = (now.getDay() + 6) % 7
  for (const shift of shiftsOf(yesterdayIndex)) {
    const opensAt = shift.opens_at!.slice(0, 5)
    const closesAt = shift.closes_at!.slice(0, 5)
    const crossesMidnight = closesAt <= opensAt

    if (crossesMidnight && current <= closesAt) return true
  }

  return false
}

/** `09:45`/`09:45:00` → `09h45`; hora cheia (`15:00`) → `15h` (omite os minutos). */
function formatShiftTime(value: string): string {
  const [hours, minutes] = value.slice(0, 5).split(':')
  return minutes === '00' ? `${hours}h` : `${hours}h${minutes}`
}

/**
 * Agrupa os turnos de UM dia numa linha legível, tipo
 * "09h45 às 15h · 17h45 às 22h30". Dia fechado (nenhum turno com horário) → "Fechado".
 */
export function formatBusinessHoursLine(shiftsOfDay: StoreBusinessHourDay[]): string {
  const shifts = shiftsOfDay.filter((shift) => !shift.is_closed && shift.opens_at && shift.closes_at)

  if (shifts.length === 0) return 'Fechado'

  return shifts
    .map((shift) => `${formatShiftTime(shift.opens_at!)} às ${formatShiftTime(shift.closes_at!)}`)
    .join(' · ')
}
