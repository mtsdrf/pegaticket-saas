import type { RouteStop, RouteType } from '../types/route'

/** Valor total de uma parada: soma de `orders` (entrega) ou `installments` (cobrança), conforme o tipo ativo da tela de rota. */
export function stopValue(stop: RouteStop, type: RouteType): number {
  if (type === 'delivery') {
    return stop.orders.reduce((total, order) => total + Number(order.total_amount), 0)
  }
  return stop.installments.reduce((total, installment) => total + Number(installment.amount), 0)
}
