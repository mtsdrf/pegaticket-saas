import { createPortal } from 'react-dom'
import type { Sale } from '../../types/sale'
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import { formatCurrency, formatDateTimeBR, formatItemQuantity } from '../../utils/format'

interface SaleReceiptPrintViewProps {
  sale: Sale
  tenantName: string
}

/**
 * Comprovante de venda imprimível (roadmap Fase 2 — "impressão/comprovantes
 * operacionais"). Mesmo padrão de `ServerGridPrintExport`: portal
 * "invisível" na tela, só exibido pela folha `@media print .pt-print-export`
 * de `index.css` — quem monta este componente chama `window.print()` logo
 * em seguida (ver SaleDetailDialog).
 */
export function SaleReceiptPrintView({ sale, tenantName }: SaleReceiptPrintViewProps) {
  const items = sale.items ?? []
  const paymentLabel = sale.payment_method ? PAYMENT_METHOD_LABELS[sale.payment_method as PaymentMethod] ?? sale.payment_method : null

  return createPortal(
    <div className="pt-print-export">
      <h1>{tenantName}</h1>
      <p>
        Venda #{sale.codigo} — {formatDateTimeBR(sale.created_at)}
        {sale.final_customer ? ` — Cliente: ${sale.final_customer.name}` : ''}
      </p>
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Qtd.</th>
            <th>Preço unit.</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          {items.map((item) => (
            <tr key={item.uuid}>
              <td>
                {item.ticket_type?.name ?? item.event_product?.name ?? 'Item'}
                {item.seat ? ` — ${item.seat.label}` : ''}
              </td>
              <td>{formatItemQuantity(item.quantity, item.ticket_type?.unit ?? null)}</td>
              <td>{formatCurrency(item.unit_price)}</td>
              <td>{formatCurrency(item.line_total)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p>
        <strong>Total: {formatCurrency(sale.total_amount)}</strong>
        {sale.discount_amount > 0 ? ` — Desconto: ${formatCurrency(sale.discount_amount)}` : ''}
        {' — '}
        {sale.is_paid ? 'Pago' : 'Aguardando pagamento'}
        {paymentLabel ? ` (${paymentLabel})` : ''}
      </p>
    </div>,
    document.body,
  )
}
