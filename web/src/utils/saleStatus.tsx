import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmptyOutlined'
import type { ReactNode } from 'react'
import { cancelStorefrontSale, rejectStorefrontSale, approveStorefrontSale } from '../services/storefrontSaleService'
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
 * Campos mínimos usados na derivação de status. `is_installment`/`paid_at`
 * são opcionais porque `GET /portal/sales` (lista agregada, Fase 5.2) não
 * traz esses campos — só o detalhe via `GET /rastreio/{uuid}` traz.
 */
export interface SaleStatusSource {
  is_cancelled: boolean
  is_paid: boolean
  is_installment?: boolean
  paid_at?: string | null
  status?: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  latest_payment?: {
    status: string
    provider_status?: string | null
    method?: string | null
    paid_at?: string | null
  } | null
}

/**
 * Não existe campo pronto de "status" na API — é derivado de
 * `is_cancelled`/`is_paid`/`status`. Finalização é sempre automática:
 * venda online só é paga via retorno do PagBank (webhook); venda manual
 * já nasce paga. Único lugar dessa lógica no frontend.
 */
export function deriveSaleStatus(sale: SaleStatusSource): StatusInfo {
  const paymentStatus = sale.latest_payment?.status ?? null
  const providerStatus = sale.latest_payment?.provider_status ?? null

  if (sale.is_cancelled) {
    return {
      label: 'Venda cancelada',
      caption: 'Fale com a equipe responsável se tiver dúvidas sobre esta compra.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (sale.status === 'rejected') {
    if (paymentStatus === 'failed' || providerStatus === 'DECLINED') {
      return {
        label: 'Compra não aprovada',
        caption: 'O PagBank recusou a transação. Revise os dados e refaça a operação para concluir a compra.',
        tone: 'warning',
        icon: <CancelOutlinedIcon />,
      }
    }

    return {
      label: 'Venda recusada',
      caption: 'A equipe não conseguiu confirmar esta compra. Fale com a empresa se tiver dúvidas.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (sale.status === 'cancellation_requested') {
    return {
      label: 'Cancelamento solicitado, aguardando aprovação',
      caption: 'Você pediu o cancelamento desta compra. A equipe ainda vai analisar.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (sale.status === 'pending_approval') {
    return {
      label: 'Aguardando confirmação',
      caption: 'Assim que a empresa confirmar sua compra, ela é liberada.',
      tone: 'neutral',
      icon: <HourglassEmptyIcon />,
    }
  }

  if (paymentStatus === 'in_analysis' || providerStatus === 'IN_ANALYSIS') {
    return {
      label: 'Pagamento em análise',
      caption: 'O PagBank está analisando sua transação. Avisaremos assim que houver uma definição.',
      tone: 'info',
      icon: <HourglassEmptyIcon />,
    }
  }

  if (paymentStatus === 'authorized' || providerStatus === 'AUTHORIZED') {
    return {
      label: 'Pagamento autorizado',
      caption: 'A transação foi autorizada e está aguardando a confirmação final do PagBank.',
      tone: 'info',
      icon: <HourglassEmptyIcon />,
    }
  }

  if (paymentStatus === 'failed' || providerStatus === 'DECLINED') {
    return {
      label: 'Pagamento recusado',
      caption: 'O PagBank recusou a transação. Revise os dados ou tente outro meio de pagamento.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (paymentStatus === 'canceled' || providerStatus === 'CANCELED') {
    return {
      label: 'Pagamento cancelado',
      caption: 'A cobrança foi cancelada no PagBank. Você pode tentar novamente se ainda quiser concluir a compra.',
      tone: 'warning',
      icon: <CancelOutlinedIcon />,
    }
  }

  if (sale.is_paid) {
    return {
      label: 'Venda concluída',
      caption: sale.paid_at ? `Pagamento confirmado em ${formatDateTimeBR(sale.paid_at)}.` : 'Pagamento confirmado.',
      tone: 'success',
      icon: <CheckCircleOutlineIcon />,
    }
  }

  return {
    label: sale.is_installment ? 'Pagamento parcelado em andamento' : 'Aguardando confirmação do pagamento',
    caption: sale.is_installment
      ? 'Acompanhe as parcelas abaixo.'
      : providerStatus === 'WAITING'
        ? 'O PagBank ainda está aguardando a conclusão do pagamento.'
        : 'A confirmação do pagamento é automática.',
    tone: 'info',
    icon: <HourglassEmptyIcon />,
  }
}

/**
 * Botão de ação de uma venda da loja: `back` = passo negativo/voltar
 * (renderizado à esquerda, outlined); `forward` = passo positivo/avançar
 * (renderizado à direita, contained). `requiresReason` decide se o clique
 * abre o diálogo de motivo obrigatório antes de `run()`.
 */
export interface SaleActionButton {
  label: string
  tone: 'back' | 'forward'
  requiresReason: boolean
  run: (uuid: string, reason?: string) => Promise<Sale>
  confirmWarning?: string
}

/**
 * Elegibilidade de "solicitar cancelamento" (Portal, roadmap A4) — mesma
 * regra do backend: ainda não paga, não está cancelada/recusada e não tem
 * uma solicitação em aberto.
 */
export function canRequestSaleCancellation(sale: SaleStatusSource): boolean {
  return (
    !sale.is_cancelled &&
    !sale.is_paid &&
    sale.status !== 'rejected' &&
    sale.status !== 'cancellation_requested'
  )
}

/**
 * Mapa único de "status atual da venda online -> botões de ação". Não
 * existe mais ação manual de concluir/pagar — finalização é sempre
 * automática (webhook do PagBank). Estados terminais (pago/recusado/
 * cancelado) retornam `[]` — o modal só mostra o badge de status read-only.
 */
export function getSaleActionButtons(sale: Sale, canManageCancellation = false): SaleActionButton[] {
  if (sale.status === 'cancellation_requested') {
    if (!canManageCancellation) return []
    return [
      { label: 'Rejeitar cancelamento', tone: 'back', requiresReason: false, run: (uuid) => rejectSaleCancellationRequest(uuid) },
      {
        label: 'Aprovar cancelamento',
        tone: 'forward',
        requiresReason: false,
        run: (uuid) => approveSaleCancellationRequest(uuid),
        confirmWarning: 'Ao aprovar, a venda é cancelada de verdade agora. Esta ação não pode ser desfeita.',
      },
    ]
  }

  if (sale.status === 'pending_approval') {
    return [
      { label: 'Recusar', tone: 'back', requiresReason: true, run: (uuid, reason) => rejectStorefrontSale(uuid, reason) },
      { label: 'Confirmar venda', tone: 'forward', requiresReason: false, run: (uuid) => approveStorefrontSale(uuid) },
    ]
  }

  if (sale.status === 'confirmed' && !sale.is_paid) {
    return [{ label: 'Cancelar venda', tone: 'back', requiresReason: true, run: (uuid, reason) => cancelStorefrontSale(uuid, reason ?? '') }]
  }

  return []
}
