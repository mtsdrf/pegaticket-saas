import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmptyOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import type { ReactNode } from 'react'
import {
  approveStorefrontSale,
  cancelStorefrontSale,
  deliverStorefrontSale,
  dispatchStorefrontSale,
  payStorefrontSale,
  rejectStorefrontSale,
  undeliverStorefrontSale,
  undispatchStorefrontSale,
} from '../services/storefrontSaleService'
import { approveSaleCancellationRequest, rejectSaleCancellationRequest } from '../services/saleService'
import type { Sale } from '../types/sale'
import { formatDateTimeBR } from './format'

export type StatusTone = 'success' | 'warning' | 'info' | 'neutral'

export interface StatusInfo {
  label: string
  caption: string
  tone: StatusTone
  icon: ReactNode
}

export const STATUS_TONE_COLORS: Record<StatusTone, { fg: string; bg: string }> = {
  success: { fg: 'var(--pt-success)', bg: 'color-mix(in srgb, var(--pt-success) 14%, transparent)' },
  warning: { fg: 'var(--pt-warning)', bg: 'color-mix(in srgb, var(--pt-warning) 16%, transparent)' },
  info: { fg: 'var(--pt-info)', bg: 'color-mix(in srgb, var(--pt-info) 14%, transparent)' },
  neutral: { fg: 'var(--pt-muted)', bg: 'color-mix(in srgb, var(--pt-muted) 14%, transparent)' },
}

/**
 * Campos mínimos usados na derivação de status. `is_installment`/`delivered_at`/
 * `paid_at` são opcionais porque `GET /portal/sales` (lista agregada,
 * Fase 5.2) não traz esses três campos — só o detalhe via `GET /rastreio/{uuid}`
 * (Fase 5.1) traz. Ausentes, a legenda cai no texto genérico (sem data/hora).
 */
export interface SaleStatusSource {
  is_cancelled: boolean
  is_paid: boolean
  is_delivered: boolean
  is_installment?: boolean
  delivered_at?: string | null
  paid_at?: string | null
  /**
   * Fila de aprovação online — ausente em respostas antigas, tratado como
   * "confirmed" (comportamento anterior).
   * `cancellation_requested` (roadmap A4) — cliente solicitou cancelamento,
   * aguardando aprovação da operação.
   */
  status?: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  is_out_for_delivery?: boolean
}

/**
 * Não existe campo pronto de "status" na API — é derivado de
 * `is_cancelled`/`is_paid`/`is_delivered`/`is_installment`/`status`/
 * `is_out_for_delivery`, nessa ordem de prioridade (contrato acordado com o
 * backend da Fase 5.1, estendido pela tela de gestão de vendas online).
 * Único lugar dessa lógica no frontend — reaproveitada por
 * `SaleTrackingPage` (Fase 5.1) e `PortalSalesPage` (Fase 5.2), nunca
 * reimplementada.
 */
export function deriveSaleStatus(order: SaleStatusSource): StatusInfo {
  if (order.is_cancelled) {
    return {
      label: 'Pedido cancelado',
      caption: 'Fale com a equipe responsável se tiver dúvidas sobre este pedido.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  // status=rejected é uma recusa antes mesmo de confirmar o pedido — antes
  // desta checagem, um pedido recusado (cancelled_at continua null por
  // desenho, ver SaleService::reject()) caía no bucket genérico "em
  // preparação", já que is_cancelled nunca refletia isso.
  if (order.status === 'rejected') {
    return {
      label: 'Pedido recusado',
      caption: 'A equipe não conseguiu confirmar este pedido. Fale com a empresa se tiver dúvidas.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  // Solicitação do cliente final ainda em aberto (roadmap A4) — some assim
  // que a loja aprovar (vira is_cancelled) ou rejeitar (volta ao status
  // anterior guardado pelo backend, cai num dos ramos abaixo).
  if (order.status === 'cancellation_requested') {
    return {
      label: 'Cancelamento solicitado, aguardando aprovação',
      caption: 'Você pediu o cancelamento deste pedido. A equipe ainda vai analisar.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (order.status === 'pending_approval') {
    return {
      label: 'Aguardando confirmação',
      caption: 'Assim que a empresa confirmar seu pedido, o preparo começa.',
      tone: 'neutral',
      icon: <HourglassEmptyIcon />,
    }
  }

  if (order.is_out_for_delivery && !order.is_delivered) {
    return {
      label: 'Saiu para entrega',
      caption: 'Seu pedido está a caminho.',
      tone: 'info',
      icon: <LocalShippingOutlinedIcon />,
    }
  }

  if (order.is_paid && order.is_delivered) {
    return {
      label: 'Pedido concluído',
      caption: order.delivered_at
        ? `Pago e entregue em ${formatDateTimeBR(order.delivered_at)}.`
        : 'Pedido pago e entregue.',
      tone: 'success',
      icon: <CheckCircleOutlineIcon />,
    }
  }

  if (order.is_delivered && !order.is_paid) {
    return order.is_installment
      ? {
          label: 'Entregue — pagamento parcelado em andamento',
          caption: 'Seu pedido já foi entregue. Acompanhe as parcelas abaixo.',
          tone: 'info',
          icon: <LocalShippingOutlinedIcon />,
        }
      : {
          label: 'Entregue — pagamento pendente',
          caption: 'Seu pedido já foi entregue. Aguardando o pagamento.',
          tone: 'warning',
          icon: <LocalShippingOutlinedIcon />,
        }
  }

  if (order.is_paid && !order.is_delivered) {
    return {
      label: 'Pago — aguardando entrega',
      caption: order.paid_at ? `Pagamento confirmado em ${formatDateTimeBR(order.paid_at)}.` : 'Pagamento confirmado.',
      tone: 'info',
      icon: <PaymentsOutlinedIcon />,
    }
  }

  return {
    label: 'Pedido em preparação',
    caption: 'Seu pedido ainda está sendo preparado pela empresa.',
    tone: 'neutral',
    icon: <HourglassEmptyIcon />,
  }
}

/**
 * Botão de ação de um pedido da loja: `back` = passo negativo/voltar
 * (renderizado à esquerda, outlined); `forward` = passo positivo/avançar
 * (renderizado à direita, contained). `requiresReason` decide se o clique
 * abre o diálogo de motivo obrigatório antes de `run()`.
 */
export interface SaleActionButton {
  label: string
  tone: 'back' | 'forward'
  requiresReason: boolean
  run: (uuid: string, reason?: string) => Promise<Sale>
  /** Aviso extra mostrado no diálogo de confirmação (ex.: ação irreversível) — substitui o texto genérico "Confirmar esta ação?". */
  confirmWarning?: string
}

/**
 * Elegibilidade de "solicitar cancelamento" (Portal, roadmap A4) — mesma
 * regra do backend (`SaleService::assertCancellationRequestEligible`):
 * ainda não saiu para entrega nem foi entregue, não está cancelado/recusado
 * e não tem uma solicitação em aberto. Fonte única, reaproveitada por
 * `PortalSalesPage` (lista) e pela tela de detalhe do pedido (rastreio).
 */
export function canRequestSaleCancellation(order: SaleStatusSource): boolean {
  return (
    !order.is_cancelled &&
    !order.is_out_for_delivery &&
    !order.is_delivered &&
    order.status !== 'rejected' &&
    order.status !== 'cancellation_requested'
  )
}

/**
 * Mapa único de "status atual do pedido online -> exatamente 2 (ou 0) botões
 * de ação", conforme fluxo confirmado com o usuário (gestão de /vendas-online).
 * Ordem do array = ordem visual (esquerda→direita). Estados terminais
 * (concluído/recusado/cancelado) retornam `[]` — o modal só mostra o badge de
 * status read-only. Fonte única dessa regra; não reimplementar por tela.
 *
 * `canManageCancellation` (roadmap A4) — só quando o usuário tem
 * `perm:sales,update` (endpoints `approve-cancellation`/`reject-cancellation`
 * vivem no grupo `orders`, não em `storefront-sales`) os botões de
 * aprovar/rejeitar cancelamento aparecem; sem a permissão, o pedido mostra só
 * o badge de status (via `deriveSaleStatus`), sem ação.
 */
export function getSaleActionButtons(order: Sale, canManageCancellation = false): SaleActionButton[] {
  if (order.status === 'cancellation_requested') {
    if (!canManageCancellation) return []
    return [
      { label: 'Rejeitar cancelamento', tone: 'back', requiresReason: false, run: (uuid) => rejectSaleCancellationRequest(uuid) },
      {
        label: 'Aprovar cancelamento',
        tone: 'forward',
        requiresReason: false,
        run: (uuid) => approveSaleCancellationRequest(uuid),
        confirmWarning:
          'Ao aprovar, o pedido é cancelado de verdade agora (estoque reservado é liberado). Esta ação não pode ser desfeita.',
      },
    ]
  }

  if (order.status === 'pending_approval') {
    return [
      { label: 'Recusar', tone: 'back', requiresReason: true, run: (uuid, reason) => rejectStorefrontSale(uuid, reason) },
      { label: 'Aceitar pedido', tone: 'forward', requiresReason: false, run: (uuid) => approveStorefrontSale(uuid) },
    ]
  }

  if (order.status === 'confirmed' && !order.is_out_for_delivery && !order.is_delivered) {
    return [
      { label: 'Cancelar pedido', tone: 'back', requiresReason: true, run: (uuid, reason) => cancelStorefrontSale(uuid, reason ?? '') },
      { label: 'Saiu para entrega', tone: 'forward', requiresReason: false, run: (uuid) => dispatchStorefrontSale(uuid) },
    ]
  }

  if (order.status === 'confirmed' && order.is_out_for_delivery && !order.is_delivered) {
    return [
      { label: 'Voltar para em preparação', tone: 'back', requiresReason: false, run: (uuid) => undispatchStorefrontSale(uuid) },
      { label: 'Marcar como entregue', tone: 'forward', requiresReason: false, run: (uuid) => deliverStorefrontSale(uuid) },
    ]
  }

  if (order.status === 'confirmed' && order.is_delivered && !order.is_paid) {
    return [
      { label: 'Voltar para "saiu para entrega"', tone: 'back', requiresReason: false, run: (uuid) => undeliverStorefrontSale(uuid) },
      { label: 'Concluir pedido', tone: 'forward', requiresReason: false, run: (uuid) => payStorefrontSale(uuid) },
    ]
  }

  return []
}
