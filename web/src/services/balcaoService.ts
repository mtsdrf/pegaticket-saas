import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Order } from '../types/order'
import type {
  AddComandaItemPayload,
  CreateTableReservationPayload,
  CreateTableWaitlistPayload,
  BalcaoOfflineSnapshot,
  CloseComandaPayload,
  Comanda,
  ComandaItem,
  OpenComandaPayload,
  SeatTableReservationPayload,
  SeatTableWaitlistPayload,
  Station,
  Table,
  TableReservation,
  TableWaitlistEntry,
  UpdatePrepStatusPayload,
} from '../types/balcao'

/* ------------------------------------------------------------------ */
/* Estações (KDS)                                                       */
/* ------------------------------------------------------------------ */

export function listStations(): Promise<Station[]> {
  return unwrap(apiClient.get<ApiSuccess<Station[]>>('/balcao/stations'))
}

/** Fila do KDS de uma estação — itens `sent_to_station`/`preparing`/`ready`, ordenados por espera. Alvo do polling. */
export function listStationTickets(stationUuid: string): Promise<ComandaItem[]> {
  return unwrap(apiClient.get<ApiSuccess<ComandaItem[]>>(`/balcao/stations/${stationUuid}/tickets`))
}

/* ------------------------------------------------------------------ */
/* Mesas                                                               */
/* ------------------------------------------------------------------ */

export function listTables(): Promise<Table[]> {
  return unwrap(apiClient.get<ApiSuccess<Table[]>>('/balcao/tables'))
}

export function getOfflineSnapshot(): Promise<BalcaoOfflineSnapshot> {
  return unwrap(apiClient.get<ApiSuccess<BalcaoOfflineSnapshot>>('/balcao/offline-snapshot'))
}

export function listTableReservations(date?: string): Promise<TableReservation[]> {
  return unwrap(apiClient.get<ApiSuccess<TableReservation[]>>('/balcao/reservas', { params: date ? { date } : undefined }))
}

export function listReservationAvailability(
  scheduled_for: string,
  party_size: number,
  duration_minutes = 120,
): Promise<Table[]> {
  return unwrap(
    apiClient.get<ApiSuccess<Table[]>>('/balcao/reservas/disponibilidade', {
      params: { scheduled_for, party_size, duration_minutes },
    }),
  )
}

export function createTableReservation(payload: CreateTableReservationPayload): Promise<TableReservation> {
  return unwrap(apiClient.post<ApiSuccess<TableReservation>>('/balcao/reservas', payload))
}

export function seatTableReservation(uuid: string, payload?: SeatTableReservationPayload): Promise<TableReservation> {
  return unwrap(apiClient.post<ApiSuccess<TableReservation>>(`/balcao/reservas/${uuid}/seat`, payload))
}

export function cancelTableReservation(uuid: string, reason: string): Promise<TableReservation> {
  return unwrap(apiClient.post<ApiSuccess<TableReservation>>(`/balcao/reservas/${uuid}/cancel`, { reason }))
}

export function noShowTableReservation(uuid: string): Promise<TableReservation> {
  return unwrap(apiClient.post<ApiSuccess<TableReservation>>(`/balcao/reservas/${uuid}/no-show`))
}

export function listTableWaitlist(): Promise<TableWaitlistEntry[]> {
  return unwrap(apiClient.get<ApiSuccess<TableWaitlistEntry[]>>('/balcao/fila-espera'))
}

export function createTableWaitlist(payload: CreateTableWaitlistPayload): Promise<TableWaitlistEntry> {
  return unwrap(apiClient.post<ApiSuccess<TableWaitlistEntry>>('/balcao/fila-espera', payload))
}

export function callTableWaitlist(uuid: string): Promise<TableWaitlistEntry> {
  return unwrap(apiClient.post<ApiSuccess<TableWaitlistEntry>>(`/balcao/fila-espera/${uuid}/call`))
}

export function seatTableWaitlist(uuid: string, payload: SeatTableWaitlistPayload): Promise<TableWaitlistEntry> {
  return unwrap(apiClient.post<ApiSuccess<TableWaitlistEntry>>(`/balcao/fila-espera/${uuid}/seat`, payload))
}

export function cancelTableWaitlist(uuid: string, reason: string): Promise<TableWaitlistEntry> {
  return unwrap(apiClient.post<ApiSuccess<TableWaitlistEntry>>(`/balcao/fila-espera/${uuid}/cancel`, { reason }))
}

/* ------------------------------------------------------------------ */
/* Comandas                                                            */
/* ------------------------------------------------------------------ */

/**
 * Lista as comandas ABERTAS (status `open`/`closing`) do tenant, com
 * `items.product`/`items.station`/`table` carregados. Não há endpoint de
 * comanda única no backend — a tela da comanda filtra esta lista por uuid.
 */
export function listOpenComandas(): Promise<Comanda[]> {
  return unwrap(apiClient.get<ApiSuccess<Comanda[]>>('/balcao/comandas'))
}

/** Busca uma comanda aberta pelo uuid (deriva de `listOpenComandas`). `null` se já fechada/inexistente. */
export async function findOpenComanda(uuid: string): Promise<Comanda | null> {
  const comandas = await listOpenComandas()
  return comandas.find((comanda) => comanda.uuid === uuid) ?? null
}

/** Abre uma comanda. Sem `table_uuid` = comanda avulsa (balcão). */
export function openComanda(payload: OpenComandaPayload): Promise<Comanda> {
  return unwrap(apiClient.post<ApiSuccess<Comanda>>('/balcao/comandas', payload))
}

export function addComandaItem(comandaUuid: string, payload: AddComandaItemPayload): Promise<ComandaItem> {
  return unwrap(apiClient.post<ApiSuccess<ComandaItem>>(`/balcao/comandas/${comandaUuid}/items`, payload))
}

/**
 * Transiciona o `prep_status` de um item. `sent_to_station` é roteado no
 * backend para o fluxo com baixa de estoque; os demais são transições puras.
 * Erros a tratar: `COMANDA_ERROR` (422, transição inválida) e
 * `INSUFFICIENT_STOCK` (422, sem saldo no envio à estação).
 */
export function updatePrepStatus(
  comandaUuid: string,
  itemUuid: string,
  payload: UpdatePrepStatusPayload,
): Promise<ComandaItem> {
  return unwrap(
    apiClient.patch<ApiSuccess<ComandaItem>>(
      `/balcao/comandas/${comandaUuid}/items/${itemUuid}/prep-status`,
      payload,
    ),
  )
}

/**
 * Fecha a conta: materializa o `Order` (`origin: 'counter'`) e valida no
 * backend que a soma das formas == total (subtotal + taxa de serviço). Erros a
 * tratar: `COMANDA_ERROR` (422, ex. fechamento duplo/sem itens),
 * `PAYMENT_AMOUNT_MISMATCH` (422, soma ≠ total). Devolve o `OrderResource`.
 */
export function closeComanda(comandaUuid: string, payload: CloseComandaPayload): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/balcao/comandas/${comandaUuid}/close`, payload))
}
