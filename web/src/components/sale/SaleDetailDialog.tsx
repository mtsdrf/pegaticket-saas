import AddIcon from '@mui/icons-material/Add'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  InputAdornment,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { AsyncAutocomplete } from '../crud/AsyncAutocomplete'
import { SaleRefundsSection } from './SaleRefundsSection'
import * as eventProductService from '../../services/eventProductService'
import * as saleService from '../../services/saleService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import type { Sale, SaleInstallmentsReallocatePayload, SaleUpdateItemsPayload } from '../../types/sale'
import { formatCurrency, formatDateBR, formatItemQuantity, toDateOnly } from '../../utils/format'

const NOTES_MAX_LENGTH = 500

/** Linha editável do editor de parcelas — valores como string pra permitir digitação livre, convertidos só no submit. */
interface InstallmentDraftRow {
  uuid?: string
  installment_number: string
  amount: string
  due_date: string
}

/** Item de catálogo vendável usado no editor de itens — `ticket_type` OU `event_product`, nunca os dois. */
interface EditableCatalogItemOption {
  kind: 'ticket_type' | 'event_product'
  uuid: string
  name: string
  price: number
}

/** Linha editável do editor de itens. `uuid` presente = item existente (atualiza); ausente = item novo (cria). */
interface EditableSaleItemDraft {
  id: string
  uuid?: string
  item: EditableCatalogItemOption | null
  quantity: string
  unitPrice: string
}

function createEmptyItemDraft(): EditableSaleItemDraft {
  return { id: crypto.randomUUID(), item: null, quantity: '1', unitPrice: '' }
}

/** Busca combinada de tipos de ingresso e adicionais do evento — mesmo padrão de `SaleFormPage`. */
async function fetchCatalogOptions(query: string): Promise<EditableCatalogItemOption[]> {
  const [ticketTypes, eventProducts] = await Promise.all([
    ticketTypeService.listTicketTypes({ name: query || undefined, per_page: 15, sort_by: 'name', sort_dir: 'asc' }),
    eventProductService.listEventProducts({ name: query || undefined, per_page: 15, sort_by: 'name', sort_dir: 'asc' }),
  ])

  return [
    ...ticketTypes.items.map((ticketType) => ({
      kind: 'ticket_type' as const,
      uuid: ticketType.uuid,
      name: `${ticketType.name} (ingresso)`,
      price: ticketType.price,
    })),
    ...eventProducts.items.map((eventProduct) => ({
      kind: 'event_product' as const,
      uuid: eventProduct.uuid,
      name: `${eventProduct.name} (adicional)`,
      price: eventProduct.price,
    })),
  ]
}

interface SaleDetailDialogProps {
  orderUuid: string | null
  open: boolean
  onClose: () => void
  /** Chamado depois de qualquer mutação bem-sucedida (ação de status, parcelas ou itens) — quem monta o dialog atualiza sua própria lista/grid. */
  onChanged?: () => void
}

/**
 * Dialog reutilizável de "Detalhes do pedido" — busca o pedido sozinho ao
 * abrir e concentra toda a UI de ações (entregar/pagar/cancelar), edição de
 * parcelas e edição de itens/cabeçalho. Usado tanto pela listagem de Pedidos
 * quanto pela tela de Rotas (uma parada pode abrir o pedido de origem).
 */
export function SaleDetailDialog({ orderUuid, open, onClose, onChanged }: SaleDetailDialogProps) {
  const [selectedSale, setSelectedOrder] = useState<Sale | null>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [isLoadingDetail, setIsLoadingDetail] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [isSubmittingAction, setIsSubmittingAction] = useState(false)
  /** Roadmap A4 — confirmação extra antes de aprovar cancelamento (ação irreversível, executa o cancelamento de fato). */
  const [approveCancellationConfirmOpen, setApproveCancellationConfirmOpen] = useState(false)
  /** Data opcional de pagamento (YYYY-MM-DD) — vazia = comportamento atual (data de hoje, decidida pelo backend). */
  const [paidAtDraft, setPaidAtDraft] = useState('')
  /** Valor opcional de pagamento — vazio = totalmente pago (comportamento atual). Só usado no pagamento do pedido (não parcelado), nunca em parcela. */
  const [paidAmountDraft, setPaidAmountDraft] = useState('')

  const [installmentEditorOpen, setInstallmentEditorOpen] = useState(false)
  const [installmentDrafts, setInstallmentDrafts] = useState<InstallmentDraftRow[]>([])
  const [installmentEditorError, setInstallmentEditorError] = useState<string | null>(null)
  const [installmentFieldErrors, setInstallmentFieldErrors] = useState<Record<string, string[]>>({})
  const [isSavingInstallments, setIsSavingInstallments] = useState(false)

  const [itemsEditorOpen, setItemsEditorOpen] = useState(false)
  const [itemDrafts, setItemDrafts] = useState<EditableSaleItemDraft[]>([])
  const [itemsEditorError, setItemsEditorError] = useState<string | null>(null)
  const [itemsFieldErrors, setItemsFieldErrors] = useState<Record<string, string[]>>({})
  const [isSavingItems, setIsSavingItems] = useState(false)
  const [headerNotesDraft, setHeaderNotesDraft] = useState('')
  const [headerExpectedDeliveryDraft, setHeaderExpectedDeliveryDraft] = useState('')

  useEffect(() => {
    if (!open || !orderUuid) return
    let cancelled = false
    setDialogError(null)
    setIsLoadingDetail(true)
    setSelectedOrder(null)
    setCancelReason('')
    setPaidAtDraft('')
    setPaidAmountDraft('')
    setInstallmentEditorOpen(false)
    setItemsEditorOpen(false)

    Promise.resolve(saleService.getSale(orderUuid))
      .then((order) => {
        if (cancelled) return
        setSelectedOrder(order)
      })
      .catch((error) => {
        if (!cancelled) {
          setDialogError(getApiErrorMessage(error, 'Não foi possível carregar o pedido agora.'))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoadingDetail(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [open, orderUuid])

  const canEditItems = Boolean(selectedSale) && !selectedSale!.is_delivered && !selectedSale!.is_paid && !selectedSale!.cancelled_at

  const installmentsSum = useMemo(
    () => (selectedSale?.installments ?? []).reduce((sum, installment) => sum + Number(installment.amount), 0),
    [selectedSale],
  )

  const paidInstallmentsSum = useMemo(
    () => (selectedSale?.installments ?? []).filter((installment) => installment.is_paid).reduce((sum, installment) => sum + Number(installment.amount), 0),
    [selectedSale],
  )

  const draftInstallmentsSum = useMemo(
    () => installmentDrafts.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
    [installmentDrafts],
  )

  const installmentEditorTotal = paidInstallmentsSum + draftInstallmentsSum
  const installmentEditorMismatch = selectedSale ? Math.abs(installmentEditorTotal - selectedSale.total_amount) > 0.005 : false
  const installmentEditorHasIncompleteRow = installmentDrafts.some(
    (row) => !row.installment_number.trim() || !row.amount.trim() || !row.due_date.trim(),
  )

  const itemsEditorHasIncompleteRow = itemDrafts.some((row) => !row.item || !row.quantity.trim() || Number(row.quantity) <= 0)

  async function refetchSelectedSale(uuid: string) {
    const order = await saleService.getSale(uuid)
    setSelectedOrder(order)
    onChanged?.()
  }

  async function runAction(
    action: 'deliver' | 'pay' | 'cancel' | 'approveCancellation' | 'rejectCancellation',
    installmentUuid?: string,
  ) {
    if (!selectedSale) return
    setDialogError(null)
    setIsSubmittingAction(true)
    try {
      let updated: Sale
      if (action === 'deliver') updated = await saleService.deliverSale(selectedSale.uuid)
      else if (action === 'pay') {
        const paidAt = paidAtDraft.trim() || undefined
        if (installmentUuid) {
          updated = await saleService.payInstallment(selectedSale.uuid, installmentUuid, paidAt)
        } else {
          const amount = paidAmountDraft.trim() ? Number(paidAmountDraft) : undefined
          updated = await saleService.paySale(selectedSale.uuid, paidAt, amount)
        }
      } else if (action === 'approveCancellation') {
        updated = await saleService.approveSaleCancellationRequest(selectedSale.uuid)
      } else if (action === 'rejectCancellation') {
        updated = await saleService.rejectSaleCancellationRequest(selectedSale.uuid)
      } else {
        updated = await saleService.cancelSale(selectedSale.uuid, cancelReason.trim())
      }
      setSelectedOrder(updated)
      setApproveCancellationConfirmOpen(false)
      onChanged?.()
    } catch (err) {
      setDialogError(getApiErrorMessage(err, 'Não foi possível concluir a ação agora.'))
    } finally {
      setIsSubmittingAction(false)
    }
  }

  function openInstallmentEditor() {
    if (!selectedSale) return
    const drafts: InstallmentDraftRow[] = (selectedSale.installments ?? [])
      .filter((installment) => !installment.is_paid)
      .map((installment) => ({
        uuid: installment.uuid,
        installment_number: String(installment.installment_number),
        amount: String(installment.amount),
        due_date: toDateOnly(installment.due_date),
      }))
    setInstallmentDrafts(drafts)
    setInstallmentEditorError(null)
    setInstallmentFieldErrors({})
    setInstallmentEditorOpen(true)
  }

  function closeInstallmentEditor() {
    if (isSavingInstallments) return
    setInstallmentEditorOpen(false)
  }

  function addDraftRow() {
    setInstallmentDrafts((current) => {
      const paidNumbers = (selectedSale?.installments ?? []).filter((installment) => installment.is_paid).map((installment) => installment.installment_number)
      const usedNumbers = [...paidNumbers, ...current.map((row) => Number(row.installment_number) || 0)]
      const nextNumber = usedNumbers.length > 0 ? Math.max(...usedNumbers) + 1 : 1
      return [...current, { installment_number: String(nextNumber), amount: '', due_date: '' }]
    })
  }

  function removeDraftRow(index: number) {
    setInstallmentDrafts((current) => current.filter((_, rowIndex) => rowIndex !== index))
  }

  function updateDraftRow(index: number, patch: Partial<InstallmentDraftRow>) {
    setInstallmentDrafts((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))
  }

  async function saveInstallments() {
    if (!selectedSale) return
    setInstallmentEditorError(null)
    setInstallmentFieldErrors({})
    setIsSavingInstallments(true)
    try {
      const payload: SaleInstallmentsReallocatePayload = {
        installments: installmentDrafts.map((row) => ({
          ...(row.uuid ? { uuid: row.uuid } : {}),
          installment_number: Number(row.installment_number),
          amount: Number(row.amount),
          due_date: row.due_date,
        })),
      }
      await saleService.reallocateInstallments(selectedSale.uuid, payload)
      await refetchSelectedSale(selectedSale.uuid)
      setInstallmentEditorOpen(false)
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setInstallmentFieldErrors(err.errors)
      }
      setInstallmentEditorError(getApiErrorMessage(err, 'Não foi possível salvar as parcelas agora.'))
    } finally {
      setIsSavingInstallments(false)
    }
  }

  function openItemsEditor() {
    if (!selectedSale) return
    const drafts: EditableSaleItemDraft[] = (selectedSale.items ?? []).map((item) => ({
      id: crypto.randomUUID(),
      uuid: item.uuid,
      item: item.ticket_type
        ? { kind: 'ticket_type' as const, uuid: item.ticket_type.uuid, name: item.ticket_type.name, price: item.unit_price }
        : item.event_product
          ? { kind: 'event_product' as const, uuid: item.event_product.uuid, name: item.event_product.name, price: item.unit_price }
          : null,
      // `item.quantity` chega como string com 3 casas fixas do cast
      // decimal:3 (ex. "7.000") — Number()+String() normaliza pro campo
      // editável sem o ".000" que confundia (mesma causa raiz corrigida em
      // formatItemQuantity, aqui é um input editável, não texto read-only).
      quantity: String(Number(item.quantity)),
      unitPrice: String(item.unit_price),
    }))
    setItemDrafts(drafts.length > 0 ? drafts : [createEmptyItemDraft()])
    setHeaderNotesDraft(selectedSale.notes ?? '')
    setHeaderExpectedDeliveryDraft(selectedSale.expected_delivery_date ? toDateOnly(selectedSale.expected_delivery_date) : '')
    setItemsEditorError(null)
    setItemsFieldErrors({})
    setItemsEditorOpen(true)
  }

  function closeItemsEditor() {
    if (isSavingItems) return
    setItemsEditorOpen(false)
  }

  function addItemDraftRow() {
    setItemDrafts((current) => [...current, createEmptyItemDraft()])
  }

  function removeItemDraftRow(id: string) {
    setItemDrafts((current) => current.filter((row) => row.id !== id))
  }

  function updateItemDraftRow(id: string, patch: Partial<EditableSaleItemDraft>) {
    setItemDrafts((current) => current.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  const fetchProductOptions = useCallback(async (query: string): Promise<EditableCatalogItemOption[]> => {
    return fetchCatalogOptions(query)
  }, [])

  function handleItemDraftProductChange(id: string, item: EditableCatalogItemOption | null) {
    // Mesma lógica de `SaleFormPage`: troca de item reinicia o preço
    // praticado pro preço de tabela do novo item. Preços por categoria de
    // cliente foram descontinuados nesta migração.
    updateItemDraftRow(id, { item, unitPrice: item ? String(item.price) : '' })
  }

  async function saveItemsEdits() {
    if (!selectedSale || itemDrafts.length === 0 || itemsEditorHasIncompleteRow) return
    setItemsEditorError(null)
    setItemsFieldErrors({})
    setIsSavingItems(true)
    try {
      const payload: SaleUpdateItemsPayload = {
        notes: headerNotesDraft.trim() || undefined,
        expected_delivery_date: headerExpectedDeliveryDraft || undefined,
        items: itemDrafts.map((row) => ({
          ...(row.uuid ? { uuid: row.uuid } : {}),
          ...(row.item!.kind === 'ticket_type'
            ? { ticket_type_uuid: row.item!.uuid }
            : { event_product_uuid: row.item!.uuid }),
          quantity: Number(row.quantity),
          ...(row.unitPrice.trim() !== '' ? { unit_price: Number(row.unitPrice) } : {}),
        })),
      }
      await saleService.updateSaleItems(selectedSale.uuid, payload)
      await refetchSelectedSale(selectedSale.uuid)
      setItemsEditorOpen(false)
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setItemsFieldErrors(err.errors)
      }
      setItemsEditorError(getApiErrorMessage(err, 'Não foi possível salvar os itens agora.'))
    } finally {
      setIsSavingItems(false)
    }
  }

  return (
    <>
      <Dialog open={open} onClose={onClose} fullWidth maxWidth="md">
        <DialogTitle>Detalhes do pedido</DialogTitle>
        <DialogContent dividers>
          {dialogError && <Typography color="error">{dialogError}</Typography>}
          {!dialogError && selectedSale && (
            <Stack spacing={2}>
              <Typography><strong>Cliente:</strong> {selectedSale.final_customer?.name ?? '—'}</Typography>
              <Typography><strong>Total:</strong> {formatCurrency(selectedSale.total_amount)}</Typography>

              {selectedSale.status === 'cancellation_requested' && (
                <Alert severity="warning" variant="outlined">
                  O cliente solicitou o cancelamento deste pedido
                  {selectedSale.cancellation_reason ? `: "${selectedSale.cancellation_reason}"` : ', sem motivo informado.'}{' '}
                  Aprove ou rejeite abaixo.
                </Alert>
              )}
              {!itemsEditorOpen && (
                <>
                  {selectedSale.payment_method && (
                    <Typography>
                      <strong>Pagamento:</strong> {PAYMENT_METHOD_LABELS[selectedSale.payment_method as PaymentMethod] ?? selectedSale.payment_method}
                      {selectedSale.needs_change &&
                        ` — troco para ${formatCurrency(selectedSale.change_for_amount ?? 0)}`}
                    </Typography>
                  )}
                  <Typography><strong>Observações:</strong> {selectedSale.notes ?? '—'}</Typography>
                </>
              )}

              <Box>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
                  <Typography sx={{ fontWeight: 700 }}>Itens</Typography>
                  {!itemsEditorOpen && canEditItems && (
                    <Button size="small" startIcon={<EditOutlinedIcon />} onClick={openItemsEditor} sx={{ minHeight: 44 }}>
                      Editar itens
                    </Button>
                  )}
                </Stack>

                {!itemsEditorOpen ? (
                  <Stack spacing={0.75}>
                    {selectedSale.items?.map((item) => (
                      <Box key={item.uuid}>
                        <Typography>
                          {item.ticket_type?.name ?? item.event_product?.name ?? '—'} •{' '}
                          {formatItemQuantity(item.quantity, item.ticket_type?.unit ?? null)} • {formatCurrency(item.line_total)}
                        </Typography>
                        {item.notes && (
                          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{item.notes}</Typography>
                        )}
                      </Box>
                    ))}
                  </Stack>
                ) : (
                  <Stack spacing={2}>
                    {itemsEditorError && <Alert severity="error">{itemsEditorError}</Alert>}

                    <TextField
                      label="Previsão de entrega"
                      type="date"
                      size="small"
                      fullWidth
                      value={headerExpectedDeliveryDraft}
                      onChange={(event) => setHeaderExpectedDeliveryDraft(event.target.value)}
                      slotProps={{ inputLabel: { shrink: true } }}
                      error={Boolean(itemsFieldErrors.expected_delivery_date?.[0])}
                      helperText={itemsFieldErrors.expected_delivery_date?.[0]}
                      sx={{ maxWidth: { md: '50%' } }}
                    />

                    <TextField
                      label="Observações"
                      value={headerNotesDraft}
                      onChange={(event) => setHeaderNotesDraft(event.target.value.slice(0, NOTES_MAX_LENGTH))}
                      minRows={2}
                      multiline
                      fullWidth
                      size="small"
                      helperText={itemsFieldErrors.notes?.[0] ?? `${headerNotesDraft.length}/${NOTES_MAX_LENGTH}`}
                      error={Boolean(itemsFieldErrors.notes?.[0])}
                      slotProps={{ htmlInput: { maxLength: NOTES_MAX_LENGTH } }}
                    />

                    <Stack spacing={1.5}>
                      {itemDrafts.map((row, index) => (
                        <Stack key={row.id} spacing={1}>
                          <Box
                            sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 2fr) 110px 150px 44px' }, gap: 1.5, alignItems: 'flex-start' }}
                          >
                            <AsyncAutocomplete
                              label="Ingresso / adicional"
                              value={row.item}
                              onChange={(option) => handleItemDraftProductChange(row.id, option)}
                              fetchOptions={fetchProductOptions}
                              getOptionLabel={(option) => option.name}
                              getOptionKey={(option) => `${option.kind}:${option.uuid}`}
                              placeholder="Buscar tipo de ingresso ou adicional pelo nome"
                              error={Boolean(
                                itemsFieldErrors[`items.${index}.ticket_type_uuid`]?.[0] ??
                                  itemsFieldErrors[`items.${index}.event_product_uuid`]?.[0],
                              )}
                              helperText={
                                itemsFieldErrors[`items.${index}.ticket_type_uuid`]?.[0] ??
                                itemsFieldErrors[`items.${index}.event_product_uuid`]?.[0]
                              }
                            />

                            <TextField
                              label="Quantidade"
                              type="number"
                              value={row.quantity}
                              onChange={(event) => updateItemDraftRow(row.id, { quantity: event.target.value })}
                              slotProps={{ htmlInput: { min: 0.001, step: '0.001' } }}
                              error={Boolean(itemsFieldErrors[`items.${index}.quantity`]?.[0])}
                              helperText={itemsFieldErrors[`items.${index}.quantity`]?.[0]}
                            />

                            <TextField
                              label="Valor unitário"
                              type="number"
                              value={row.unitPrice}
                              onChange={(event) => updateItemDraftRow(row.id, { unitPrice: event.target.value })}
                              disabled={!row.item}
                              placeholder={row.item ? String(row.item.price) : undefined}
                              slotProps={{
                                htmlInput: { min: 0, step: '0.01' },
                                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                              }}
                              error={Boolean(itemsFieldErrors[`items.${index}.unit_price`]?.[0])}
                              helperText={itemsFieldErrors[`items.${index}.unit_price`]?.[0]}
                            />

                            <Tooltip title="Remover item" arrow>
                              <IconButton
                                aria-label="Remover item"
                                disabled={itemDrafts.length === 1}
                                onClick={() => removeItemDraftRow(row.id)}
                                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
                              >
                                <DeleteOutlineIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          </Box>
                        </Stack>
                      ))}
                    </Stack>

                    <Button variant="outlined" startIcon={<AddIcon />} onClick={addItemDraftRow} fullWidth sx={{ minHeight: 44 }}>
                      Adicionar item
                    </Button>

                    <Stack spacing={1}>
                      <Button
                        variant="contained"
                        onClick={() => void saveItemsEdits()}
                        disabled={isSavingItems || itemDrafts.length === 0 || itemsEditorHasIncompleteRow}
                        fullWidth
                        sx={{ minHeight: 44 }}
                      >
                        {isSavingItems ? 'Salvando…' : 'Salvar alterações'}
                      </Button>
                      <Button onClick={closeItemsEditor} disabled={isSavingItems} color="inherit" fullWidth sx={{ minHeight: 44 }}>
                        Cancelar edição
                      </Button>
                    </Stack>
                  </Stack>
                )}
              </Box>

              <SaleRefundsSection sale={selectedSale} onRefundRegistered={() => void refetchSelectedSale(selectedSale.uuid)} />

              {selectedSale.status !== 'cancellation_requested' && !selectedSale.cancelled_at && !selectedSale.is_paid && (
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ width: '100%' }}>
                  <TextField
                    label="Data do pagamento (opcional)"
                    type="date"
                    value={paidAtDraft}
                    onChange={(event) => setPaidAtDraft(event.target.value)}
                    slotProps={{ inputLabel: { shrink: true } }}
                    helperText="Deixe em branco para usar a data de hoje. Datas futuras agendam o pagamento."
                    sx={{ width: { xs: '100%', sm: '50%' } }}
                  />
                  {!selectedSale.is_installment && (
                    <TextField
                      label="Valor pago (opcional)"
                      type="number"
                      value={paidAmountDraft}
                      onChange={(event) => setPaidAmountDraft(event.target.value)}
                      slotProps={{
                        htmlInput: { min: 0.01, step: '0.01' },
                        input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                      }}
                      helperText="Deixe em branco para marcar como totalmente pago. Um valor menor que o total registra um pagamento parcial sem marcar como pago."
                      sx={{ width: { xs: '100%', sm: '50%' } }}
                    />
                  )}
                </Stack>
              )}

              {selectedSale.is_installment && (
                <Box>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'baseline', justifyContent: 'space-between', mb: 1, flexWrap: 'wrap' }}>
                    <Typography sx={{ fontWeight: 700 }}>Parcelas</Typography>
                    <Typography
                      sx={{
                        fontSize: 13,
                        color: Math.abs(installmentsSum - selectedSale.total_amount) > 0.005 ? 'var(--pt-warning)' : 'var(--pt-muted)',
                      }}
                    >
                      Parcelas somam {formatCurrency(installmentsSum)} de {formatCurrency(selectedSale.total_amount)}
                    </Typography>
                  </Stack>
                  <Stack spacing={1}>
                    {(selectedSale.installments ?? []).map((installment) => (
                      <Stack key={installment.uuid} direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                        <Typography>
                          Parcela {installment.installment_number} • {formatCurrency(installment.amount)} • vence {formatDateBR(installment.due_date)}
                        </Typography>
                        {installment.is_paid ? (
                          <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-success)' }}>Paga</Typography>
                        ) : (
                          <Button
                            size="small"
                            startIcon={<PaidOutlinedIcon />}
                            disabled={isSubmittingAction}
                            onClick={() => void runAction('pay', installment.uuid)}
                          >
                            Pagar
                          </Button>
                        )}
                      </Stack>
                    ))}
                  </Stack>
                  {!selectedSale.cancelled_at && (
                    <Button size="small" startIcon={<EditOutlinedIcon />} onClick={openInstallmentEditor} sx={{ mt: 1, minHeight: 44 }}>
                      Editar parcelas
                    </Button>
                  )}
                </Box>
              )}

              {selectedSale.status !== 'cancellation_requested' && !selectedSale.cancelled_at && !selectedSale.is_paid && (
                <TextField
                  label="Motivo do cancelamento"
                  value={cancelReason}
                  onChange={(event) => setCancelReason(event.target.value)}
                  minRows={2}
                  multiline
                  fullWidth
                />
              )}
            </Stack>
          )}
          {!selectedSale && isLoadingDetail && <Typography>Carregando...</Typography>}
        </DialogContent>
        <DialogActions sx={{ flexDirection: 'column', alignItems: 'stretch', gap: 1, px: 3, pb: 2 }}>
          {selectedSale && selectedSale.status === 'cancellation_requested' && (
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ width: '100%' }}>
              <Button
                fullWidth
                variant="outlined"
                color="inherit"
                disabled={isSubmittingAction}
                onClick={() => void runAction('rejectCancellation')}
                sx={{ minHeight: 44 }}
              >
                Rejeitar cancelamento
              </Button>
              <Button
                fullWidth
                variant="contained"
                color="error"
                startIcon={<CancelOutlinedIcon />}
                disabled={isSubmittingAction}
                onClick={() => setApproveCancellationConfirmOpen(true)}
                sx={{ minHeight: 44 }}
              >
                Aprovar cancelamento
              </Button>
            </Stack>
          )}
          {selectedSale && selectedSale.status !== 'cancellation_requested' && !selectedSale.is_delivered && !selectedSale.cancelled_at && (
            <Button startIcon={<LocalShippingOutlinedIcon />} disabled={isSubmittingAction} onClick={() => void runAction('deliver')} sx={{ minHeight: 44 }}>
              Entregar
            </Button>
          )}
          {selectedSale && selectedSale.status !== 'cancellation_requested' && !selectedSale.is_installment && !selectedSale.is_paid && !selectedSale.cancelled_at && (
            <Button startIcon={<PaidOutlinedIcon />} disabled={isSubmittingAction} onClick={() => void runAction('pay')} sx={{ minHeight: 44 }}>
              Marcar como pago
            </Button>
          )}
          {selectedSale && selectedSale.status !== 'cancellation_requested' && !selectedSale.cancelled_at && !selectedSale.is_paid && (
            <Button
              color="error"
              startIcon={<CancelOutlinedIcon />}
              disabled={isSubmittingAction || !cancelReason.trim()}
              onClick={() => void runAction('cancel')}
              sx={{ minHeight: 44 }}
            >
              Cancelar pedido
            </Button>
          )}
          <Button onClick={onClose} sx={{ minHeight: 44 }}>Fechar</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={approveCancellationConfirmOpen} onClose={() => !isSubmittingAction && setApproveCancellationConfirmOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Aprovar cancelamento</DialogTitle>
        <DialogContent>
          <Alert severity="warning">
            Ao aprovar, o pedido é cancelado de verdade agora. Esta ação não pode ser desfeita.
          </Alert>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setApproveCancellationConfirmOpen(false)} disabled={isSubmittingAction}>
            Voltar
          </Button>
          <Button
            onClick={() => void runAction('approveCancellation')}
            variant="contained"
            color="error"
            disabled={isSubmittingAction}
          >
            {isSubmittingAction ? 'Aprovando…' : 'Aprovar cancelamento'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={installmentEditorOpen} onClose={closeInstallmentEditor} maxWidth="sm" fullWidth>
        <DialogTitle sx={{ fontWeight: 600 }}>Editar parcelas</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {installmentEditorError && <Alert severity="error">{installmentEditorError}</Alert>}

            {paidInstallmentsSum > 0 && (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                Parcelas já pagas (não editáveis): {formatCurrency(paidInstallmentsSum)}
              </Typography>
            )}

            {installmentDrafts.length === 0 && (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                Nenhuma parcela pendente. Adicione uma abaixo.
              </Typography>
            )}

            <Stack spacing={1.5}>
              {installmentDrafts.map((row, index) => (
                <Box
                  key={row.uuid ?? `new-${index}`}
                  sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: '90px minmax(0, 1fr) 160px 44px' }, gap: 1, alignItems: 'flex-start' }}
                >
                  <TextField
                    label="Nº"
                    type="number"
                    value={row.installment_number}
                    onChange={(event) => updateDraftRow(index, { installment_number: event.target.value })}
                    slotProps={{ htmlInput: { min: 1, step: 1 } }}
                    error={Boolean(installmentFieldErrors[`installments.${index}.installment_number`]?.[0])}
                    helperText={installmentFieldErrors[`installments.${index}.installment_number`]?.[0]}
                  />
                  <TextField
                    label="Valor"
                    type="number"
                    value={row.amount}
                    onChange={(event) => updateDraftRow(index, { amount: event.target.value })}
                    slotProps={{
                      htmlInput: { min: 0.01, step: '0.01' },
                      input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    }}
                    error={Boolean(installmentFieldErrors[`installments.${index}.amount`]?.[0])}
                    helperText={installmentFieldErrors[`installments.${index}.amount`]?.[0]}
                  />
                  <TextField
                    label="Vencimento"
                    type="date"
                    value={row.due_date}
                    onChange={(event) => updateDraftRow(index, { due_date: event.target.value })}
                    slotProps={{ inputLabel: { shrink: true } }}
                    error={Boolean(installmentFieldErrors[`installments.${index}.due_date`]?.[0])}
                    helperText={installmentFieldErrors[`installments.${index}.due_date`]?.[0]}
                  />
                  <Tooltip title="Remover parcela" arrow>
                    <IconButton
                      aria-label={`Remover parcela ${row.installment_number || index + 1}`}
                      onClick={() => removeDraftRow(index)}
                      sx={{ minWidth: 44, minHeight: 44, alignSelf: 'flex-start', color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </Box>
              ))}
            </Stack>

            <Button variant="outlined" startIcon={<AddIcon />} onClick={addDraftRow} sx={{ alignSelf: 'flex-start', minHeight: 44 }}>
              Adicionar parcela
            </Button>

            <Typography sx={{ fontSize: 13, fontWeight: 600, color: installmentEditorMismatch ? 'var(--pt-warning)' : 'var(--pt-success)' }}>
              Total: {formatCurrency(installmentEditorTotal)} de {formatCurrency(selectedSale?.total_amount ?? 0)}
              {installmentEditorMismatch && ' — a soma precisa bater exatamente com o total do pedido antes de salvar.'}
            </Typography>
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={closeInstallmentEditor} disabled={isSavingInstallments} color="inherit">
            Cancelar
          </Button>
          <Button
            onClick={() => void saveInstallments()}
            disabled={isSavingInstallments || installmentEditorHasIncompleteRow}
            variant="contained"
          >
            {isSavingInstallments ? 'Salvando…' : 'Salvar parcelas'}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
