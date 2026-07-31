import type { BalcaoPaymentMethod, PrepStatus, StationType, TableStatus } from '../types/balcao'

/** Ordem canônica das formas de pagamento no fechamento da comanda. */
export const BALCAO_PAYMENT_METHODS: BalcaoPaymentMethod[] = ['cash', 'pix', 'credit', 'debit', 'card']

export const BALCAO_PAYMENT_METHOD_LABELS: Record<BalcaoPaymentMethod, string> = {
  cash: 'Dinheiro',
  pix: 'Pix',
  credit: 'Cartão de crédito',
  debit: 'Cartão de débito',
  card: 'Cartão',
}

export const STATION_TYPE_LABELS: Record<StationType, string> = {
  kitchen: 'Cozinha',
  bar: 'Bar',
  grill: 'Chapa',
  other: 'Outra',
}

/**
 * Cor da mesa por status. Tokens semânticos do design-system (nunca hex).
 * `bg`/`border`/`text` casam com light e dark porque derivam de `--mk-*`.
 */
export const TABLE_STATUS_META: Record<TableStatus, { label: string; color: string }> = {
  free: { label: 'Livre', color: 'var(--mk-success)' },
  occupied: { label: 'Ocupada', color: 'var(--mk-primary)' },
  reserved: { label: 'Reservada', color: 'var(--mk-warning)' },
  closing: { label: 'Fechando', color: 'var(--mk-danger)' },
}

/** Rótulo + cor (token) + ordem de exibição de cada estágio de preparo. */
export const PREP_STATUS_META: Record<PrepStatus, { label: string; color: string; order: number }> = {
  queued: { label: 'Na comanda', color: 'var(--mk-muted)', order: 0 },
  sent_to_station: { label: 'Enviado', color: 'var(--mk-primary)', order: 1 },
  preparing: { label: 'Preparando', color: 'var(--mk-warning)', order: 2 },
  ready: { label: 'Pronto', color: 'var(--mk-success)', order: 3 },
  delivered_to_table: { label: 'Entregue', color: 'var(--mk-muted)', order: 4 },
  cancelled: { label: 'Cancelado', color: 'var(--mk-danger)', order: 5 },
}

/**
 * Próximo estágio avançável pelo KDS (leitura à distância, botão único grande).
 * `sent_to_station → preparing → ready → delivered_to_table`. `null` = terminal
 * para o preparador.
 */
export const KDS_NEXT_STATUS: Partial<
  Record<PrepStatus, { next: Exclude<PrepStatus, 'queued'>; actionLabel: string }>
> = {
  sent_to_station: { next: 'preparing', actionLabel: 'Iniciar preparo' },
  preparing: { next: 'ready', actionLabel: 'Marcar pronto' },
  ready: { next: 'delivered_to_table', actionLabel: 'Entregar' },
}
