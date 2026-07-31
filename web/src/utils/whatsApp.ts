import { formatCurrency, formatDateBR, formatItemQuantity } from './format'

/** Só dígitos, igual ao legado (`/^\d+$/`) — WhatsApp precisa do telefone "limpo". */
export function isDigitsOnlyPhone(phone: string | null | undefined): phone is string {
  return typeof phone === 'string' && /^\d+$/.test(phone)
}

/** Monta a URL de envio do WhatsApp Web/App a partir de um telefone já validado por `isDigitsOnlyPhone` (DDI 55 fixo, mesmo padrão do legado) — centraliza o que antes estava duplicado inline em `OrderFormPage`/`ReceivableReportListPage`. */
export function buildWhatsAppUrl(phone: string, message: string): string {
  return `https://api.whatsapp.com/send?phone=${encodeURIComponent('55' + phone)}&text=${encodeURIComponent(message)}`
}

/**
 * Resumo completo da compra, enviado na criação do pedido (`OrderFormPage`) — lista de itens, total, entrega
 * prevista e status de pagamento no momento da criação. `trackingUrl` é opcional (toggle `tenant_settings.
 * send_tracking_link_whatsapp`, ver `AuthContext.tsx`/`activeTenant`) — quando omitido, a linha do link some
 * e o restante da mensagem sai igual a antes.
 */
export function buildOrderCreatedWhatsAppMessage(params: {
  clientName: string
  items: { name: string; quantity: string; unitPrice: number }[]
  total: number
  expectedDeliveryDate: string
  isPaid: boolean
  paidAmount: number | null
  trackingUrl?: string
}): string {
  const { clientName, items, total, expectedDeliveryDate, isPaid, paidAmount, trackingUrl } = params

  const productLines = items.map((item) => `• ${item.quantity} ${item.name} – ${formatCurrency(item.unitPrice)}`).join('\n')
  const statusLine = isPaid ? '✅ *Status:* Pago' : '❗ *Status:* Ainda não pago'
  const deliveryLine = expectedDeliveryDate ? `\n📅 *Entrega prevista:* ${formatDateBR(expectedDeliveryDate)}` : ''
  const paidLine = isPaid && paidAmount !== null ? `\n💵 *Valor pago:* ${formatCurrency(paidAmount)}` : ''
  const trackingBlock = trackingUrl ? `📦 *Acompanhe seu pedido:* ${trackingUrl}\n\n` : ''

  return (
    `🧾 *Resumo da sua compra, ${clientName}!*\n\n` +
    `📦 Produtos:\n${productLines}\n\n` +
    `💰 *Total:* ${formatCurrency(total)}${deliveryLine}\n` +
    `${statusLine}${paidLine}\n\n` +
    `${trackingBlock}` +
    `Qualquer dúvida, estamos à disposição! 😊`
  )
}

/**
 * Resumo do pedido enviado sob demanda (`ClientOrdersPage`) — mensagem idêntica à
 * usada em produção no sistema legado (confirmada com o usuário, exemplo real
 * pedido #12608), sem emoji. `trackingUrl` é opcional (toggle `tenant_settings.
 * send_tracking_link_whatsapp`, ver `AuthContext.tsx`/`activeTenant`) — quando
 * omitido, a mensagem sai igual a antes, sem linha de rastreio.
 */
export function buildOrderSummaryWhatsAppMessage(params: {
  codigo: string
  clientName: string
  isDelivered: boolean
  deliveredAt: string | null
  notes: string | null
  items: { name: string; quantity: number; unit: string | null; lineTotal: number }[]
  total: number
  paidAmount: number | null
  trackingUrl?: string
}): string {
  const { codigo, clientName, isDelivered, deliveredAt, notes, items, total, paidAmount, trackingUrl } = params

  const lines: string[] = [
    'Olá, obrigado por comprar em nossa loja!',
    'Aqui estão os dados do seu pedido.',
    '',
    `Pedido #${codigo}`,
    '',
    `Cliente: ${clientName}`,
    '',
    `Entregue: ${isDelivered ? 'Sim' : 'Não'}`,
  ]

  if (deliveredAt) {
    lines.push('', `Data de entrega: ${formatDateBR(deliveredAt)}`)
  }

  if (notes && notes.trim() !== '') {
    lines.push('', 'Observação:', notes)
  }

  lines.push('', 'Produtos:', '')
  for (const item of items) {
    lines.push(`${formatItemQuantity(item.quantity, item.unit)} - ${formatCurrency(item.lineTotal)} -> ${item.name}`)
  }

  lines.push('', `Total: ${formatCurrency(total)}`)

  if (paidAmount !== null && paidAmount > 0) {
    lines.push('', `Total pago: ${formatCurrency(paidAmount)}`)
  }

  if (trackingUrl) {
    lines.push('', `Acompanhe seu pedido: ${trackingUrl}`)
  }

  lines.push('', '--Mensagem automática--')

  return lines.join('\n')
}

export function buildReceivableWhatsAppMessage(params: {
  clientName: string
  sourceLabel: string
  amount: number
  dueDate: string
  daysOverdue: number
}): string {
  const { clientName, sourceLabel, amount, dueDate, daysOverdue } = params
  const overdueLine = daysOverdue > 0 ? `\n⏰ *Atraso:* ${daysOverdue} dia${daysOverdue === 1 ? '' : 's'}` : ''

  return (
    `Olá, ${clientName}! Tudo bem?\n\n` +
    `Identificamos um ${sourceLabel.toLowerCase()} em aberto no valor de ${formatCurrency(amount)}.` +
    `\n📅 *Vencimento:* ${formatDateBR(dueDate)}` +
    `${overdueLine}\n\n` +
    `Se você já realizou o pagamento, por favor desconsidere esta mensagem. Caso precise, estamos à disposição para ajudar com a regularização.`
  )
}
