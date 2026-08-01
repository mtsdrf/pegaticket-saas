const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

export function formatCurrency(value: string | number): string {
  const numeric = typeof value === 'string' ? Number(value) : value
  return currencyFormatter.format(Number.isFinite(numeric) ? numeric : 0)
}

/**
 * Quantidade de item de pedido: só mostra casas decimais quando o produto é
 * vendido por peso (`unit === 'kg'`) — caso contrário exibe como inteiro,
 * sem o `.000` de `decimal(12,3)` que confundia (lido como "2 mil" em vez de
 * "2 unidades" no padrão de milhar pt-BR). Aceita string porque `SaleItem`
 * chega da API já serializado pelo cast `decimal:3` do Laravel (sempre
 * string, ex. `"7.000"`), nunca um `number` de fato.
 */
export function formatItemQuantity(quantity: string | number, unit: string | null): string {
  const numeric = typeof quantity === 'string' ? Number(quantity) : quantity
  const safe = Number.isFinite(numeric) ? numeric : 0

  if (unit?.toLowerCase() === 'kg') {
    return safe.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })
  }
  return String(Math.round(safe))
}

/**
 * Quantidade genérica sem unidade conhecida (estoque/movimentação): remove
 * os zeros à direita do cast `decimal:3` do Laravel ("5.000"→"5",
 * "5.500"→"5,5") sem arredondar quantidade fracionária de verdade —
 * diferente de `formatItemQuantity`, que força inteiro fora de itens `kg`
 * porque ali se conhece a unidade do produto; aqui não.
 */
export function formatQuantity(value: string | number): string {
  const numeric = typeof value === 'string' ? Number(value) : value
  const safe = Number.isFinite(numeric) ? numeric : 0
  return safe.toLocaleString('pt-BR', { maximumFractionDigits: 3 })
}

export function formatPercentage(value: number): string {
  return `${value.toFixed(2).replace('.', ',')}%`
}

const dateFormatter = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })

/**
 * Alguns recursos (ex. `SaleInstallment.due_date`) vêm da API como datetime
 * ISO completo (`2026-08-10T00:00:00.000000Z`, serialização padrão do Carbon
 * pra uma coluna `date`), não só `YYYY-MM-DD` — pega só os 10 primeiros
 * caracteres pra usar em `<input type="date">` (que rejeita silenciosamente
 * qualquer valor fora do formato exato) ou reenviar num payload.
 */
export function toDateOnly(value: string): string {
  return value.slice(0, 10)
}

/**
 * "dd/mm/aaaa" a partir de uma data `YYYY-MM-DD` (ou datetime ISO, normalizado
 * via `toDateOnly` internamente) — o `T00:00:00` evita o dia "voltar" um por
 * causa de fuso horário (Brasil é UTC negativo, `new Date('2026-07-20')`
 * sozinho parseia como UTC meia-noite).
 */
export function formatDateBR(value: string): string {
  const date = new Date(`${toDateOnly(value)}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value
  return dateFormatter.format(date)
}

/**
 * "dd/mm/aaaa" a partir de um datetime ISO COMPLETO (ex.: `Sale.created_at`,
 * timestamp real com timezone) — diferente de `formatDateBR`, que é pra
 * colunas `date` puras e força meia-noite local; aqui o valor já traz o
 * instante certo (UTC ou com offset), então `new Date(value)` sozinho já
 * respeita o fuso do navegador sem precisar (e sem poder) normalizar hora.
 * Usar esta função para qualquer datetime, nunca `formatDateBR` — pedido
 * feito à noite no Brasil (UTC-3) já é o dia seguinte em UTC, e o slice de
 * `formatDateBR` pegaria a data UTC errada.
 */
export function formatDateFromDateTimeBR(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return dateFormatter.format(date)
}

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

/**
 * "dd/mm/aaaa hh:mm" a partir de um datetime ISO completo (ex.:
 * `AuditLog.created_at`) — diferente de `formatDateBR`, que assume data pura
 * e força meia-noite local; aqui o horário importa (trilha de auditoria),
 * então o valor ISO é parseado direto, sem normalizar pra meia-noite.
 */
export function formatDateTimeBR(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return dateTimeFormatter.format(date)
}

const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'short' })

/**
 * "mmm/aa" (ex.: "Jul/26") em vez de só o mês — histórico real pode
 * cobrir vários anos, e "Jul" sozinho reaparece a cada ano no eixo do
 * gráfico sem forma de distinguir um do outro.
 */
export function formatMonthLabel(yearMonth: string): string {
  const [year, month] = yearMonth.split('-').map(Number)
  if (!year || !month) return yearMonth

  const label = monthFormatter.format(new Date(year, month - 1, 1)).replace('.', '')
  const capitalized = label.charAt(0).toUpperCase() + label.slice(1)
  return `${capitalized}/${String(year).slice(2)}`
}
