import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { ACCESS } from '../../access/requirements'
import { useAuth } from '../../hooks/useAuth'
import * as orderService from '../../services/orderService'
import * as orderFiscalPreviewService from '../../services/orderFiscalPreviewService'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { Order } from '../../types/order'
import type { OrderFiscalDocumentDetail, OrderFiscalPreview } from '../../types/orderFiscalPreview'
import { formatDateBR } from '../../utils/format'
import {
  formatFiscalDocumentCheckedAtLabel,
  formatFiscalDocumentPreparedSummary,
  formatFiscalDocumentReferenceLabel,
  formatFiscalDocumentStatusLabel,
  formatFiscalDocumentTypeLabel,
  formatFiscalLineTotal,
  formatFiscalTaxTypeLabel,
  isManualFiscalProvider,
} from './fiscal'

interface OrderFiscalDialogProps {
  orderUuid: string | null
  open: boolean
  onClose: () => void
  onChanged?: () => void
}

export function OrderFiscalDialog({ orderUuid, open, onClose, onChanged }: OrderFiscalDialogProps) {
  const { hasPermission } = useAuth()
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)
  const [fiscalPreview, setFiscalPreview] = useState<OrderFiscalPreview | null>(null)
  const [fiscalDocumentDetail, setFiscalDocumentDetail] = useState<OrderFiscalDocumentDetail | null>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [documentDetailError, setDocumentDetailError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [isSubmittingAction, setIsSubmittingAction] = useState(false)

  const canPrepareFiscalDocument = hasPermission(ACCESS.ordersUpdate)
  const canRunPreparationAction = !selectedOrder?.fiscal_document
    || ['draft', 'canceled', 'rejected'].includes(selectedOrder.fiscal_document.status)

  const isManualFiscalFlow = useMemo(
    () => Boolean(
      (selectedOrder?.fiscal_document && isManualFiscalProvider(selectedOrder.fiscal_document.provider))
        || (fiscalPreview && fiscalPreview.provider_mode === 'manual'),
    ),
    [fiscalPreview, selectedOrder],
  )

  useEffect(() => {
    if (!open || !orderUuid) return
    let cancelled = false
    setDialogError(null)
    setDocumentDetailError(null)
    setSelectedOrder(null)
    setFiscalPreview(null)
    setFiscalDocumentDetail(null)
    setIsLoading(true)

    Promise.allSettled([
      orderService.getOrder(orderUuid),
      orderFiscalPreviewService.getOrderFiscalPreview(orderUuid),
      orderFiscalPreviewService.getOrderFiscalDocument(orderUuid),
    ])
      .then((results) => {
        if (cancelled) return

        const [orderResult, previewResult, documentResult] = results

        if (orderResult.status === 'fulfilled') {
          setSelectedOrder(orderResult.value)
        } else {
          setDialogError(getApiErrorMessage(orderResult.reason, 'Não foi possível carregar as informações fiscais do pedido agora.'))
        }

        if (previewResult.status === 'fulfilled') {
          setFiscalPreview(previewResult.value)
        } else if (orderResult.status === 'fulfilled') {
          setDialogError(getApiErrorMessage(previewResult.reason, 'Não foi possível carregar o diagnóstico fiscal agora.'))
        }

        if (documentResult.status === 'fulfilled') {
          setFiscalDocumentDetail(documentResult.value)
        } else if (orderResult.status === 'fulfilled' && orderResult.value.fiscal_document) {
          setDocumentDetailError(getApiErrorMessage(documentResult.reason, 'Não foi possível carregar o documento fiscal agora.'))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [open, orderUuid])

  async function refetchFiscalState(uuid: string) {
    const [order, preview] = await Promise.all([
      orderService.getOrder(uuid),
      orderFiscalPreviewService.getOrderFiscalPreview(uuid),
    ])

    setSelectedOrder(order)
    setFiscalPreview(preview)

    if (order.fiscal_document) {
      try {
        setFiscalDocumentDetail(await orderFiscalPreviewService.getOrderFiscalDocument(uuid))
        setDocumentDetailError(null)
      } catch (error) {
        setFiscalDocumentDetail(null)
        setDocumentDetailError(getApiErrorMessage(error, 'Não foi possível carregar o documento fiscal agora.'))
      }
    } else {
      setFiscalDocumentDetail(null)
      setDocumentDetailError(null)
    }

    onChanged?.()
  }

  async function handlePrepareFiscalDocument() {
    if (!selectedOrder) return
    setDialogError(null)
    setIsSubmittingAction(true)
    try {
      await orderFiscalPreviewService.prepareOrderFiscalDocument(selectedOrder.uuid)
      await refetchFiscalState(selectedOrder.uuid)
    } catch (error) {
      setDialogError(getApiErrorMessage(error, 'Não foi possível preparar o documento fiscal agora.'))
    } finally {
      setIsSubmittingAction(false)
    }
  }

  async function handleSubmitFiscalDocument() {
    if (!selectedOrder) return
    setDialogError(null)
    setIsSubmittingAction(true)
    try {
      await orderFiscalPreviewService.submitOrderFiscalDocument(selectedOrder.uuid)
      await refetchFiscalState(selectedOrder.uuid)
    } catch (error) {
      setDialogError(getApiErrorMessage(error, 'Não foi possível encaminhar o documento fiscal para o fluxo interno agora.'))
    } finally {
      setIsSubmittingAction(false)
    }
  }

  async function handleSyncFiscalDocumentStatus() {
    if (!selectedOrder) return
    setDialogError(null)
    setIsSubmittingAction(true)
    try {
      await orderFiscalPreviewService.syncOrderFiscalDocumentStatus(selectedOrder.uuid)
      await refetchFiscalState(selectedOrder.uuid)
    } catch (error) {
      setDialogError(getApiErrorMessage(error, 'Não foi possível atualizar o andamento do documento fiscal agora.'))
    } finally {
      setIsSubmittingAction(false)
    }
  }

  async function handleCancelFiscalDocument() {
    if (!selectedOrder) return
    setDialogError(null)
    setIsSubmittingAction(true)
    try {
      await orderFiscalPreviewService.cancelOrderFiscalDocument(selectedOrder.uuid)
      await refetchFiscalState(selectedOrder.uuid)
    } catch (error) {
      setDialogError(getApiErrorMessage(error, 'Não foi possível cancelar o documento fiscal preparado agora.'))
    } finally {
      setIsSubmittingAction(false)
    }
  }

  async function handleDownloadFiscalXmlPreview() {
    if (!selectedOrder) return
    setDialogError(null)
    try {
      await orderFiscalPreviewService.downloadOrderFiscalDocumentXmlPreview(selectedOrder.uuid)
    } catch (error) {
      setDialogError(getApiErrorMessage(error, 'Não foi possível baixar a prévia XML do documento fiscal agora.'))
    }
  }

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="md">
      <DialogTitle>Informações fiscais do pedido</DialogTitle>
      <DialogContent dividers>
        {dialogError && (
          <Alert severity="warning" variant="outlined" sx={{ mb: 2 }}>
            {dialogError}
          </Alert>
        )}

        {isLoading && (
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
            Carregando informações fiscais...
          </Typography>
        )}

        {!isLoading && !dialogError && selectedOrder && fiscalPreview && (
          <Stack spacing={2}>
            <Typography>
              <strong>Pedido:</strong> {selectedOrder.codigo}
            </Typography>
            <Typography>
              <strong>Cliente:</strong> {selectedOrder.client?.name ?? '—'}
            </Typography>

            <Box sx={{ p: 2, ...SOFT_PANEL_SX, background: 'var(--mk-surface-subtle)' }}>
              <Stack spacing={1}>
                <Alert severity={fiscalPreview.status === 'ready' ? 'success' : 'warning'} variant="outlined">
                  {fiscalPreview.can_prepare
                    ? 'Pedido pronto para preparação fiscal.'
                    : 'Existem pendências fiscais antes da preparação da nota.'}
                </Alert>

                {isManualFiscalFlow && (
                  <Alert severity="info" variant="outlined">
                    O módulo fiscal está em modo manual. O sistema prepara e acompanha rascunhos internos, mas ainda não transmite documentos oficialmente para SEFAZ ou prefeitura.
                  </Alert>
                )}

                <Typography>
                  <strong>Documento sugerido:</strong> {formatFiscalDocumentTypeLabel(fiscalPreview.context.document_type)}
                </Typography>
                <Typography>
                  <strong>Perfil fiscal:</strong> {fiscalPreview.operation_profile?.name ?? 'Nenhum perfil fiscal compatível'}
                </Typography>
                <Typography>
                  <strong>Contexto:</strong> {fiscalPreview.context.order_origin} • {fiscalPreview.context.fulfillment_type} • {fiscalPreview.context.destination_type}
                </Typography>

                {selectedOrder.fiscal_document && (
                  <>
                    <Typography>
                      <strong>Documento fiscal:</strong> {formatFiscalDocumentTypeLabel(selectedOrder.fiscal_document.document_type)}
                      {selectedOrder.fiscal_document.series && selectedOrder.fiscal_document.document_number
                        ? ` ${selectedOrder.fiscal_document.series}/${selectedOrder.fiscal_document.document_number}`
                        : ''}{' '}
                      em {formatFiscalDocumentStatusLabel(selectedOrder.fiscal_document.status, selectedOrder.fiscal_document.provider).toLowerCase()}
                    </Typography>
                    {selectedOrder.fiscal_document.payload_snapshot_summary && (
                      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
                        {formatFiscalDocumentPreparedSummary(
                          selectedOrder.fiscal_document.payload_snapshot_summary.generated_at,
                          selectedOrder.fiscal_document.payload_snapshot_summary.items_count,
                          selectedOrder.fiscal_document.payload_snapshot_summary.operation_profile_name,
                        )}
                      </Typography>
                    )}
                    {selectedOrder.fiscal_document.provider_document_id && (
                      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
                        <strong>{formatFiscalDocumentReferenceLabel(selectedOrder.fiscal_document.provider)}:</strong> {selectedOrder.fiscal_document.provider_document_id}
                      </Typography>
                    )}
                    {selectedOrder.fiscal_document.provider_status_checked_at && (
                      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
                        <strong>{formatFiscalDocumentCheckedAtLabel(selectedOrder.fiscal_document.provider)}:</strong>{' '}
                        {formatDateBR(selectedOrder.fiscal_document.provider_status_checked_at)}
                      </Typography>
                    )}
                    <Button
                      variant="text"
                      size="small"
                      onClick={() => void handleDownloadFiscalXmlPreview()}
                      sx={{ alignSelf: 'flex-start', px: 0, minHeight: 36 }}
                    >
                      Baixar XML técnico do rascunho
                    </Button>
                  </>
                )}

                {canPrepareFiscalDocument && (
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignItems: { xs: 'stretch', sm: 'center' } }}>
                    {canRunPreparationAction && (
                      <Button
                        variant="contained"
                        startIcon={<DescriptionOutlinedIcon />}
                        disabled={isSubmittingAction || !fiscalPreview.can_prepare}
                        onClick={() => void handlePrepareFiscalDocument()}
                        sx={{ alignSelf: 'flex-start', minHeight: 44 }}
                      >
                        {selectedOrder.fiscal_document ? 'Atualizar preparação fiscal' : 'Preparar documento fiscal'}
                      </Button>
                    )}

                    {selectedOrder.fiscal_document?.status === 'draft' && (
                      <Button
                        variant="outlined"
                        disabled={isSubmittingAction}
                        onClick={() => void handleSubmitFiscalDocument()}
                        sx={{ alignSelf: 'flex-start', minHeight: 44 }}
                      >
                        {isManualFiscalFlow ? 'Encaminhar para fluxo interno' : 'Enviar ao provider fiscal'}
                      </Button>
                    )}

                    {selectedOrder.fiscal_document && ['provider_submitted', 'pending'].includes(selectedOrder.fiscal_document.status) && (
                      <Button
                        variant="outlined"
                        disabled={isSubmittingAction}
                        onClick={() => void handleSyncFiscalDocumentStatus()}
                        sx={{ alignSelf: 'flex-start', minHeight: 44 }}
                      >
                        {isManualFiscalFlow ? 'Atualizar andamento interno' : 'Atualizar status fiscal'}
                      </Button>
                    )}

                    {selectedOrder.fiscal_document && ['draft', 'provider_submitted', 'pending'].includes(selectedOrder.fiscal_document.status) && (
                      <Button
                        variant="outlined"
                        color="inherit"
                        disabled={isSubmittingAction}
                        onClick={() => void handleCancelFiscalDocument()}
                        sx={{ alignSelf: 'flex-start', minHeight: 44 }}
                      >
                        Cancelar documento fiscal
                      </Button>
                    )}
                  </Stack>
                )}

                {fiscalPreview.issues.length > 0 && (
                  <Stack spacing={0.75}>
                    <Typography sx={{ fontWeight: 700 }}>Pendências</Typography>
                    {fiscalPreview.issues.map((issue) => (
                      <Typography key={issue.key} sx={{ fontSize: 14 }}>
                        {issue.severity === 'error' ? 'Erro' : 'Atenção'}: {issue.details}
                      </Typography>
                    ))}
                  </Stack>
                )}

                <Stack spacing={0.75}>
                  <Typography sx={{ fontWeight: 700 }}>Resumo por item</Typography>
                  {fiscalPreview.line_items.map((item, index) => (
                    <Typography key={item.product_uuid ?? `${item.product_name}-${index}`} sx={{ fontSize: 14 }}>
                      {(item.product_name ?? 'Item sem produto')} • CFOP {item.resolved_cfop ?? '—'} • Regras{' '}
                      {item.matched_tax_rules.length > 0
                        ? item.matched_tax_rules.map((rule) => `${formatFiscalTaxTypeLabel(rule.tax_type)} ${rule.rate_percent}%`).join(' · ')
                        : 'não encontradas'}
                    </Typography>
                  ))}
                </Stack>
              </Stack>
            </Box>

            {documentDetailError && (
              <Alert severity="warning" variant="outlined">
                {documentDetailError}
              </Alert>
            )}

            {fiscalDocumentDetail && (
              <Box sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
                <Stack spacing={2}>
                  <Typography sx={{ fontWeight: 700 }}>Detalhes do documento fiscal</Typography>
                  <Typography>
                    <strong>Documento:</strong> {formatFiscalDocumentTypeLabel(fiscalDocumentDetail.document_type)}
                    {fiscalDocumentDetail.series && fiscalDocumentDetail.document_number
                      ? ` ${fiscalDocumentDetail.series}/${fiscalDocumentDetail.document_number}`
                      : ' sem numeração reservada'}
                  </Typography>
                  <Typography>
                    <strong>Status:</strong> {formatFiscalDocumentStatusLabel(fiscalDocumentDetail.status, fiscalDocumentDetail.provider)}
                  </Typography>
                  {fiscalDocumentDetail.access_key && (
                    <Typography>
                      <strong>Chave de acesso:</strong> {fiscalDocumentDetail.access_key}
                    </Typography>
                  )}
                  {fiscalDocumentDetail.rejection_reason && (
                    <Typography>
                      <strong>Motivo do retorno:</strong> {fiscalDocumentDetail.rejection_reason}
                    </Typography>
                  )}
                  {fiscalDocumentDetail.pdf_path && (
                    <Typography>
                      <strong>PDF/DANFE:</strong> {fiscalDocumentDetail.pdf_path}
                    </Typography>
                  )}
                  <Typography>
                    <strong>Emitente:</strong> {fiscalDocumentDetail.payload_snapshot?.issuer.name ?? '—'} • {fiscalDocumentDetail.payload_snapshot?.issuer.cnpj ?? '—'}
                  </Typography>
                  <Typography>
                    <strong>Destinatário:</strong> {fiscalDocumentDetail.payload_snapshot?.recipient.name ?? '—'} • {fiscalDocumentDetail.payload_snapshot?.recipient.document ?? '—'}
                  </Typography>
                  <Typography>
                    <strong>Operação:</strong> {fiscalDocumentDetail.payload_snapshot?.operation.operation_profile?.name ?? '—'} •{' '}
                    {fiscalDocumentDetail.payload_snapshot?.operation.order_origin ?? '—'} • {fiscalDocumentDetail.payload_snapshot?.operation.fulfillment_type ?? '—'}
                  </Typography>

                  <Stack spacing={0.75}>
                    <Typography sx={{ fontWeight: 700 }}>Itens do documento</Typography>
                    {fiscalDocumentDetail.payload_snapshot?.items.map((item, index) => (
                      <Typography key={item.product_uuid ?? `${item.product_name}-${index}`} sx={{ fontSize: 14 }}>
                        {item.product_name ?? 'Item sem produto'} • CFOP {item.cfop ?? '—'} • {formatFiscalLineTotal(item.line_total)}
                      </Typography>
                    ))}
                  </Stack>

                  <Typography>
                    <strong>Total do documento:</strong> {formatFiscalLineTotal(fiscalDocumentDetail.payload_snapshot?.totals.total_amount)}
                  </Typography>

                  {(fiscalDocumentDetail.payload_snapshot?.issues.length ?? 0) > 0 && (
                    <Stack spacing={0.75}>
                      <Typography sx={{ fontWeight: 700 }}>Pendências registradas no documento</Typography>
                      {fiscalDocumentDetail.payload_snapshot?.issues.map((issue) => (
                        <Typography key={issue.key} sx={{ fontSize: 14 }}>
                          {issue.severity === 'error' ? 'Erro' : 'Atenção'}: {issue.details}
                        </Typography>
                      ))}
                    </Stack>
                  )}

                  {fiscalDocumentDetail.provider_messages.length > 0 && (
                    <Stack spacing={0.75}>
                      <Typography sx={{ fontWeight: 700 }}>
                        {isManualFiscalProvider(fiscalDocumentDetail.provider) ? 'Histórico operacional' : 'Histórico do provider'}
                      </Typography>
                      {fiscalDocumentDetail.provider_messages.map((message) => (
                      <Typography key={message.uuid} sx={{ fontSize: 14 }}>
                          {message.received_at ? formatDateBR(message.received_at) : 'Data não informada'} • {message.summary}
                          {message.provider_status
                            ? ` • status ${formatFiscalDocumentStatusLabel(message.provider_status, fiscalDocumentDetail.provider).toLowerCase()}`
                            : ''}
                        </Typography>
                      ))}
                    </Stack>
                  )}

                  {fiscalDocumentDetail.attempts.length > 0 && (
                    <Stack spacing={0.75}>
                      <Typography sx={{ fontWeight: 700 }}>Tentativas técnicas</Typography>
                      {fiscalDocumentDetail.attempts.map((attempt) => (
                        <Typography key={attempt.uuid} sx={{ fontSize: 14 }}>
                          Tentativa {attempt.attempt_number} • {attempt.operation_type} • {attempt.status}
                          {attempt.provider ? ` • ${attempt.provider}` : ''}
                          {attempt.provider_reference ? ` • ref. ${attempt.provider_reference}` : ''}
                        </Typography>
                      ))}
                    </Stack>
                  )}
                </Stack>
              </Box>
            )}
          </Stack>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Fechar</Button>
      </DialogActions>
    </Dialog>
  )
}
