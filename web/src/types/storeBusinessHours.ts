/** 0=domingo ... 6=sábado — mesma numeração do backend (`day_of_week`). */
export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6

export const WEEKDAY_LABELS: Record<DayOfWeek, string> = {
  0: 'Domingo',
  1: 'Segunda-feira',
  2: 'Terça-feira',
  3: 'Quarta-feira',
  4: 'Quinta-feira',
  5: 'Sexta-feira',
  6: 'Sábado',
}

/**
 * `GET/PUT /store-settings/business-hours` — pode haver MÚLTIPLOS turnos no
 * mesmo `day_of_week` (manhã + tarde, até 4, sem sobreposição). O `GET`
 * sempre devolve ao menos 1 item por dia (placeholder fechado quando nada
 * configurado); o `PUT` só persiste/devolve os turnos enviados (dia sem
 * turno simplesmente não aparece).
 */
export interface StoreBusinessHourDay {
  day_of_week: DayOfWeek
  opens_at: string | null
  closes_at: string | null
  is_closed: boolean
}

export interface UpdateStoreBusinessHoursPayload {
  days: StoreBusinessHourDay[]
}
