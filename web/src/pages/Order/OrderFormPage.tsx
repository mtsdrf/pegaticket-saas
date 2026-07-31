import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import {
  Alert,
  Box,
  Button,
  Divider,
  FormControlLabel,
  IconButton,
  InputAdornment,
  Paper,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AsyncAutocomplete } from '../../components/crud/AsyncAutocomplete'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { ProductOptionsConfiguratorDialog, type ProductOptionSelection } from '../../components/product/ProductOptionsConfiguratorDialog'
import { useAuth } from '../../hooks/useAuth'
import * as clientService from '../../services/clientService'
import * as orderService from '../../services/orderService'
import * as productService from '../../services/productService'
import * as stockLocationService from '../../services/stockLocationService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { Client } from '../../types/client'
import type { Order, OrderCreateItemPayload } from '../../types/order'
import type { Product } from '../../types/product'
import type { StockLocation } from '../../types/stockLocation'
import { formatCurrency } from '../../utils/format'
import { buildOrderCreatedWhatsAppMessage, buildWhatsAppUrl, isDigitsOnlyPhone } from '../../utils/whatsApp'

const NOTES_MAX_LENGTH = 500

interface DraftItem {
  id: string
  product: Product | null
  quantity: string
  /** Preço praticado — pré-preenchido com `product.price` ao selecionar, mas editável (desconto/acréscimo manual). */
  unitPrice: string
  selectedOptions: ProductOptionSelection[]
}

function createDraftItem(): DraftItem {
  return { id: crypto.randomUUID(), product: null, quantity: '1', unitPrice: '', selectedOptions: [] }
}

/** `unitPrice` vazio (item recém-adicionado, ainda sem edição manual) cai no preço de tabela. */
function effectiveUnitPrice(item: DraftItem): number {
  if (item.unitPrice.trim() !== '' && Number.isFinite(Number(item.unitPrice))) return Number(item.unitPrice)
  return item.product?.price ?? 0
}

export function OrderFormPage() {
  const navigate = useNavigate()
  const { activeTenantUuid, activeTenant } = useAuth()

  const [client, setClient] = useState<Client | null>(null)
  const [locations, setLocations] = useState<StockLocation[]>([])
  const [stockLocationUuid, setStockLocationUuid] = useState('')
  const [isInstallment, setIsInstallment] = useState(false)
  const [installmentsCount, setInstallmentsCount] = useState('2')
  const [notes, setNotes] = useState('')
  const [items, setItems] = useState<DraftItem[]>([createDraftItem()])
  const [expectedDeliveryDate, setExpectedDeliveryDate] = useState('')
  const [markAsDelivered, setMarkAsDelivered] = useState(true)
  const [markAsPaid, setMarkAsPaid] = useState(false)
  const [paidAmount, setPaidAmount] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const whatsAppWindowRef = useRef<Window | null>(null)
  const [optionsDialogItemId, setOptionsDialogItemId] = useState<string | null>(null)

  useEffect(() => {
    if (!activeTenantUuid) return
    setIsLoading(true)
    setLoadError(null)

    stockLocationService
      .listStockLocations({ per_page: 100 })
      .then((result) => setLocations(result.items))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do pedido agora.')))
      .finally(() => setIsLoading(false))
  }, [activeTenantUuid])

  // "Pago" não existe pra pedido parcelado (backend rejeita com 422) — some
  // junto se o usuário ligar "parcelado" depois de já ter marcado "pago".
  useEffect(() => {
    if (isInstallment) setMarkAsPaid(false)
  }, [isInstallment])

  const fetchClientOptions = useCallback(
    async (query: string): Promise<Client[]> => {
      if (!activeTenantUuid) return []
      const result = await clientService.listClients({ name: query || undefined, per_page: 20, sort_by: 'name', sort_dir: 'asc' })
      return result.clients
    },
    [activeTenantUuid],
  )

  const fetchProductOptions = useCallback(
    async (query: string): Promise<Product[]> => {
      if (!activeTenantUuid) return []
      const result = await productService.listProducts({ name: query || undefined, per_page: 20, sort_by: 'name', sort_dir: 'asc' })
      return result.items
    },
    [activeTenantUuid],
  )

  function updateItem(id: string, patch: Partial<DraftItem>) {
    setItems((current) => current.map((item) => (item.id === id ? { ...item, ...patch } : item)))
  }

  async function handleItemProductChange(id: string, product: Product | null) {
    // Troca de produto reinicia o preço praticado pro preço de tabela do
    // novo produto — o "desconto manual" anterior não faz sentido pra um
    // produto diferente. Preenche primeiro com o preço de tabela (fallback
    // visual imediato, síncrono) e substitui pelo preço sugerido pra
    // categoria do cliente quando a chamada resolver — evita o campo
    // ficar vazio/travado enquanto a requisição está em voo.
    updateItem(id, { product, unitPrice: product ? String(product.price) : '', selectedOptions: [] })

    if (!product || !client) return

    try {
      const [suggestedPrice, detailedProduct] = await Promise.all([
        productService.getSuggestedPrice(product.uuid, client.uuid),
        productService.getProduct(product.uuid),
      ])
      // Confirma que o item ainda está no mesmo produto antes de aplicar —
      // evita sobrescrever uma troca de produto mais recente caso essa
      // chamada resolva atrasada (condição de corrida).
      setItems((current) =>
        current.map((item) =>
          item.id === id && item.product?.uuid === product.uuid
            ? { ...item, product: detailedProduct, unitPrice: String(suggestedPrice) }
            : item,
        ),
      )
    } catch {
      // Sugestão é só uma conveniência — se falhar, mantém o preço de
      // tabela já preenchido acima; não bloqueia o formulário nem mostra
      // erro (o campo continua editável manualmente).
    }
  }

  const summary = useMemo(() => {
    let totalTable = 0
    let totalPracticed = 0

    for (const item of items) {
      const quantity = Number(item.quantity)
      if (!item.product || !quantity || quantity <= 0) continue
      const optionsTotal = item.selectedOptions.reduce((sum, option) => sum + option.unit_price * option.quantity, 0) * quantity
      totalTable += item.product.price * quantity + optionsTotal
      totalPracticed += effectiveUnitPrice(item) * quantity + optionsTotal
    }

    return {
      totalTable,
      totalPracticed,
      discount: totalTable - totalPracticed,
    }
  }, [items])

  const paidAmountNumber = paidAmount.trim() === '' ? summary.totalPracticed : Number(paidAmount)
  const pendingOrChange = summary.totalPracticed - (Number.isFinite(paidAmountNumber) ? paidAmountNumber : 0)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    if (!client) {
      setFormError('Selecione um cliente.')
      return
    }

    const validItems = items.filter((item) => item.product && Number(item.quantity) > 0)
    if (validItems.length === 0) {
      setFormError('Adicione ao menos um item com produto e quantidade.')
      return
    }

    if (notes.length > NOTES_MAX_LENGTH) {
      setFormError(`Observações não pode passar de ${NOTES_MAX_LENGTH} caracteres.`)
      return
    }

    setIsSubmitting(true)

    const itemsPayload: OrderCreateItemPayload[] = validItems.map((item) => ({
      product_uuid: item.product!.uuid,
      quantity: Number(item.quantity),
      unit_price: effectiveUnitPrice(item),
      options: item.selectedOptions.map((option) => ({
        product_option_uuid: option.product_option_uuid,
        quantity: option.quantity,
      })),
    }))

    try {
      const order: Order = await orderService.createOrder({
        client_uuid: client.uuid,
        stock_location_uuid: stockLocationUuid || undefined,
        is_installment: isInstallment,
        installments_count: isInstallment ? Number(installmentsCount) : null,
        notes: notes.trim() || undefined,
        items: itemsPayload,
        mark_as_delivered: markAsDelivered,
        mark_as_paid: isInstallment ? false : markAsPaid,
        expected_delivery_date: expectedDeliveryDate || undefined,
      })

      if (isDigitsOnlyPhone(client.phone_primary)) {
        const message = buildOrderCreatedWhatsAppMessage({
          clientName: client.name,
          items: validItems.map((item) => ({
            name: item.product!.name,
            quantity: item.quantity,
            unitPrice: effectiveUnitPrice(item),
          })),
          total: order.total_amount,
          expectedDeliveryDate,
          isPaid: markAsPaid && !isInstallment,
          paidAmount: markAsPaid && !isInstallment ? paidAmountNumber : null,
          trackingUrl: activeTenant?.send_tracking_link_whatsapp
            ? `${window.location.origin}/rastreio/${order.uuid}`
            : undefined,
        })
        const url = buildWhatsAppUrl(client.phone_primary, message)
        whatsAppWindowRef.current = window.open(url, '_blank')
      }

      navigate('/pedidos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível criar o pedido agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  const optionsDialogItem = items.find((item) => item.id === optionsDialogItemId) ?? null

  function handleOpenOptions(itemId: string) {
    setOptionsDialogItemId(itemId)
  }

  function handleConfirmOptions(selections: ProductOptionSelection[]) {
    if (!optionsDialogItemId) return
    updateItem(optionsDialogItemId, { selectedOptions: selections })
    setOptionsDialogItemId(null)
  }

  return (
    <CrudFormShell
      backLabel="Pedidos"
      backTo="/pedidos"
      title="Novo pedido"
      subtitle="Monte os itens, defina a forma de pagamento e confirme a venda."
      breadcrumbs={[{ label: 'Pedidos', to: '/pedidos' }, { label: 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2.2}>
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2 }}>
          <AsyncAutocomplete
            label="Cliente"
            required
            value={client}
            onChange={setClient}
            fetchOptions={fetchClientOptions}
            getOptionLabel={(option) => option.name}
            getOptionKey={(option) => option.uuid}
            placeholder="Buscar cliente pelo nome"
          />

          <LocalAutocomplete
            label="Local de estoque"
            placeholder="Padrão da empresa"
            fullWidth
            options={locations}
            value={locations.find((location) => location.uuid === stockLocationUuid) ?? null}
            onChange={(location) => setStockLocationUuid(location?.uuid ?? '')}
            getOptionLabel={(location) => location.name}
            getOptionKey={(location) => location.uuid}
          />
        </Box>

        <FormControlLabel
          control={<Switch checked={isInstallment} onChange={(event) => setIsInstallment(event.target.checked)} />}
          label="Pedido parcelado"
        />

        {isInstallment && (
          <TextField
            label="Quantidade de parcelas"
            type="number"
            value={installmentsCount}
            onChange={(event) => setInstallmentsCount(event.target.value)}
            slotProps={{ htmlInput: { min: 1, step: 1 } }}
            sx={{ maxWidth: 220 }}
          />
        )}

        <Stack spacing={1.5}>
          <Typography sx={{ fontWeight: 700 }}>Itens do pedido</Typography>

          {items.map((item) => {
            const discount = item.product && Number(item.quantity) > 0 ? (item.product.price - effectiveUnitPrice(item)) * Number(item.quantity) : 0
            const hasDiscountInfo = item.product && Number(item.quantity) > 0 && Math.abs(discount) > 0.005

            return (
              <Box key={item.id}>
                <Box
                  sx={{
                    display: 'grid',
                    gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 2fr) 110px 150px 44px' },
                    gap: 1.5,
                    alignItems: 'flex-start',
                  }}
                >
                  <AsyncAutocomplete
                    label="Produto"
                    value={item.product}
                    onChange={(product) => void handleItemProductChange(item.id, product)}
                    fetchOptions={fetchProductOptions}
                    getOptionLabel={(option) => option.name}
                    getOptionKey={(option) => option.uuid}
                    placeholder="Buscar produto pelo nome"
                  />

                  <TextField
                    label="Quantidade"
                    type="number"
                    value={item.quantity}
                    onChange={(event) => updateItem(item.id, { quantity: event.target.value })}
                    slotProps={{ htmlInput: { min: 0.001, step: '0.001' } }}
                  />

                  <TextField
                    label="Valor unitário"
                    type="number"
                    value={item.unitPrice}
                    onChange={(event) => updateItem(item.id, { unitPrice: event.target.value })}
                    disabled={!item.product}
                    placeholder={item.product ? String(item.product.price) : undefined}
                    slotProps={{
                      htmlInput: { min: 0, step: '0.01' },
                      input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    }}
                  />

                  <IconButton
                    aria-label="Remover item"
                    disabled={items.length === 1}
                    onClick={() => setItems((current) => current.filter((entry) => entry.id !== item.id))}
                    sx={{ minWidth: 44, minHeight: 44, justifySelf: { xs: 'end', md: 'auto' } }}
                  >
                    <DeleteOutlineIcon />
                  </IconButton>
                </Box>

                {hasDiscountInfo && (
                  <Typography
                    sx={{ fontSize: 13, mt: 0.5, color: discount > 0 ? 'var(--pt-success)' : 'var(--pt-warning)' }}
                  >
                    {discount > 0
                      ? `Desconto neste item: ${formatCurrency(discount)}`
                      : `Acréscimo neste item: ${formatCurrency(Math.abs(discount))}`}
                  </Typography>
                )}

                {item.product?.option_groups && item.product.option_groups.length > 0 && (
                  <Stack spacing={0.75} sx={{ mt: 1 }}>
                    <Button variant="outlined" onClick={() => handleOpenOptions(item.id)} sx={{ alignSelf: 'flex-start' }}>
                      {item.selectedOptions.length > 0 ? 'Editar opcionais' : 'Selecionar opcionais'}
                    </Button>
                    {item.selectedOptions.length > 0 && (
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                        {item.selectedOptions
                          .map((option) => `${option.group_name}: ${option.name}${option.quantity > 1 ? ` x${option.quantity}` : ''}`)
                          .join(' • ')}
                      </Typography>
                    )}
                  </Stack>
                )}
              </Box>
            )
          })}

          <Button
            variant="outlined"
            onClick={() => setItems((current) => [...current, createDraftItem()])}
            sx={{ minHeight: 44, alignSelf: { xs: 'stretch', sm: 'flex-start' }, width: { xs: '100%', sm: 'auto' } }}
          >
            Adicionar item
          </Button>
        </Stack>

        <TextField
          label="Previsão de entrega"
          type="date"
          value={expectedDeliveryDate}
          onChange={(event) => setExpectedDeliveryDate(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
          fullWidth
          sx={{ maxWidth: { xs: '100%', sm: 260 } }}
        />

        <TextField
          label="Observações"
          value={notes}
          onChange={(event) => setNotes(event.target.value.slice(0, NOTES_MAX_LENGTH))}
          minRows={3}
          multiline
          fullWidth
          helperText={`${notes.length}/${NOTES_MAX_LENGTH}`}
          slotProps={{ htmlInput: { maxLength: NOTES_MAX_LENGTH } }}
        />

        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 1 }}>
          <FormControlLabel
            control={<Switch checked={markAsDelivered} onChange={(event) => setMarkAsDelivered(event.target.checked)} />}
            label="Entregue"
          />
          <Stack>
            <FormControlLabel
              control={
                <Switch
                  checked={markAsPaid}
                  disabled={isInstallment}
                  onChange={(event) => {
                    setMarkAsPaid(event.target.checked)
                    if (event.target.checked && paidAmount.trim() === '') {
                      setPaidAmount(summary.totalPracticed.toFixed(2))
                    }
                  }}
                />
              }
              label="Pago"
            />
            {isInstallment && (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                Pedido parcelado não pode nascer pago — use "Pagar parcela" depois de criado.
              </Typography>
            )}
          </Stack>
        </Box>

        {markAsPaid && !isInstallment && (
          <TextField
            label="Valor pago"
            type="number"
            value={paidAmount}
            onChange={(event) => setPaidAmount(event.target.value)}
            slotProps={{
              htmlInput: { min: 0, step: '0.01' },
              input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
            }}
            sx={{ maxWidth: 260 }}
          />
        )}

        <Paper variant="outlined" sx={{ p: 2, ...SOFT_PANEL_SX }}>
          <Typography sx={{ fontWeight: 700, mb: 1.5 }}>Resumo</Typography>
          <Stack spacing={0.75}>
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ color: 'var(--pt-muted)' }}>Total sem desconto</Typography>
              <Typography>{formatCurrency(summary.totalTable)}</Typography>
            </Stack>
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ color: 'var(--pt-muted)' }}>{summary.discount >= 0 ? 'Desconto' : 'Acréscimo'}</Typography>
              <Typography sx={{ color: summary.discount >= 0 ? 'var(--pt-success)' : 'var(--pt-warning)' }}>
                {formatCurrency(Math.abs(summary.discount))}
              </Typography>
            </Stack>
            <Divider sx={{ my: 0.5 }} />
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontWeight: 700 }}>Total do pedido</Typography>
              <Typography sx={{ fontWeight: 700 }}>{formatCurrency(summary.totalPracticed)}</Typography>
            </Stack>

            {markAsPaid && !isInstallment && (
              <>
                <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
                  <Typography sx={{ color: 'var(--pt-muted)' }}>Valor pago</Typography>
                  <Typography>{formatCurrency(Number.isFinite(paidAmountNumber) ? paidAmountNumber : 0)}</Typography>
                </Stack>
                <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
                  <Typography sx={{ color: 'var(--pt-muted)' }}>{pendingOrChange > 0 ? 'Pendente' : pendingOrChange < 0 ? 'Troco' : 'Quitado'}</Typography>
                  <Typography>{pendingOrChange === 0 ? '—' : formatCurrency(Math.abs(pendingOrChange))}</Typography>
                </Stack>
              </>
            )}
          </Stack>
        </Paper>

        {!isDigitsOnlyPhone(client?.phone_primary) && client && (
          <Alert severity="info" variant="outlined">
            Este cliente não tem um telefone válido pra notificação automática por WhatsApp — o pedido é criado normalmente, só o aviso não é enviado.
          </Alert>
        )}
      </Stack>

      <ProductOptionsConfiguratorDialog
        open={Boolean(optionsDialogItem)}
        title={optionsDialogItem?.product ? `Personalizar ${optionsDialogItem.product.name}` : 'Personalizar produto'}
        groups={(optionsDialogItem?.product?.option_groups ?? []).map((group) => ({
          uuid: group.uuid,
          name: group.name,
          description: group.description,
          min_select: group.min_select,
          max_select: group.max_select,
          options: group.options
            .filter((option) => option.is_available)
            .map((option) => ({
              uuid: option.uuid,
              name: option.name,
              description: option.description,
              price: option.price,
            })),
        }))}
        initialSelections={optionsDialogItem?.selectedOptions ?? []}
        onClose={() => setOptionsDialogItemId(null)}
        onConfirm={handleConfirmOptions}
      />
    </CrudFormShell>
  )
}
