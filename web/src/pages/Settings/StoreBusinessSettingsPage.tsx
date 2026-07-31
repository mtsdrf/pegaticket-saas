import AddCircleOutlineIcon from '@mui/icons-material/AddCircleOutlineOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import LocalOfferOutlinedIcon from '@mui/icons-material/LocalOfferOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import SellOutlinedIcon from '@mui/icons-material/SellOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Divider,
  FormControlLabel,
  FormGroup,
  IconButton,
  MenuItem,
  Paper,
  Skeleton,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { FORM_FIELD_SURFACE_SX } from '../../styles/formFieldStyles'
import { FORM_GRID_2_SX, PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { PAYMENT_METHODS, PAYMENT_METHOD_SETTINGS_LABELS } from '../../constants/paymentMethods'
import * as locationService from '../../services/locationService'
import * as productService from '../../services/productService'
import * as storeDeliveryFeeService from '../../services/storeDeliveryFeeService'
import * as couponService from '../../services/couponService'
import * as productPromotionService from '../../services/productPromotionService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'
import type { StoreDeliveryFee } from '../../types/storeDeliveryFee'
import type { Bairro, Cidade, Estado } from '../../types/location'
import type { Product } from '../../types/product'
import type { Coupon, CouponPayload, CouponType } from '../../types/coupon'
import type { ProductPromotion, ProductPromotionDiscountType } from '../../types/productPromotion'

const SECTION_SX = {
  p: { xs: 2, sm: 3 },
  ...ELEVATED_SURFACE_SX,
  ...FORM_FIELD_SURFACE_SX,
} as const

/** Seção 1: taxa de entrega por bairro — CRUD normal (upsert), cascata Estado→Cidade→Bairro reaproveitando `locationService` (staff). */
function DeliveryFeeSection() {
  const [fees, setFees] = useState<StoreDeliveryFee[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [estados, setEstados] = useState<Estado[]>([])
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [selectedEstado, setSelectedEstado] = useState<Estado | null>(null)
  const [selectedCidade, setSelectedCidade] = useState<Cidade | null>(null)
  const [selectedBairro, setSelectedBairro] = useState<Bairro | null>(null)
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)
  const [feeValue, setFeeValue] = useState('')

  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [removingUuid, setRemovingUuid] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    storeDeliveryFeeService
      .listDeliveryFees()
      .then(setFees)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as taxas de entrega agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
    locationService
      .getEstados()
      .then(setEstados)
      .catch(() => undefined)
  }, [])

  async function handleEstadoChange(estado: Estado | null) {
    setSelectedEstado(estado)
    setSelectedCidade(null)
    setSelectedBairro(null)
    setCidades([])
    setBairros([])
    if (!estado) return
    setIsLoadingCidades(true)
    try {
      setCidades(await locationService.getCidades(estado.uuid))
    } catch {
      setCidades([])
    } finally {
      setIsLoadingCidades(false)
    }
  }

  async function handleCidadeChange(cidade: Cidade | null) {
    setSelectedCidade(cidade)
    setSelectedBairro(null)
    setBairros([])
    if (!cidade) return
    setIsLoadingBairros(true)
    try {
      setBairros(await locationService.getBairros(cidade.uuid))
    } catch {
      setBairros([])
    } finally {
      setIsLoadingBairros(false)
    }
  }

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})

    const errors: Record<string, string[]> = {}
    if (!selectedBairro) errors.bairro_uuid = ['Selecione um bairro.']
    const feeNumber = Number(feeValue)
    if (!feeValue.trim() || Number.isNaN(feeNumber) || feeNumber < 0) {
      errors.fee = ['Informe um valor válido.']
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }

    setIsSubmitting(true)
    try {
      await storeDeliveryFeeService.upsertDeliveryFee(selectedBairro!.uuid, feeNumber)
      setFeeValue('')
      setSelectedEstado(null)
      setSelectedCidade(null)
      setSelectedBairro(null)
      setCidades([])
      setBairros([])
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a taxa de entrega agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete(uuid: string) {
    setRemovingUuid(uuid)
    setFormError(null)
    try {
      await storeDeliveryFeeService.deleteDeliveryFee(uuid)
      setFees((current) => current.filter((fee) => fee.uuid !== uuid))
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível remover esta taxa agora.'))
    } finally {
      setRemovingUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={SECTION_SX}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <LocalShippingOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Taxas de entrega por bairro</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Bairros sem taxa cadastrada não recebem entrega pela loja online — o checkout bloqueia esses pedidos.
      </Typography>

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(4, minmax(0, 1fr))' }, mb: 2 }}>
        <LocalAutocomplete
          label="Estado"
          options={estados}
          value={selectedEstado}
          onChange={(value) => void handleEstadoChange(value)}
          getOptionLabel={(estado) => `${estado.name} (${estado.uf})`}
          getOptionKey={(estado) => estado.uuid}
        />
        <LocalAutocomplete
          label="Cidade"
          disabled={!selectedEstado || isLoadingCidades}
          loading={isLoadingCidades}
          options={cidades}
          value={selectedCidade}
          onChange={(value) => void handleCidadeChange(value)}
          getOptionLabel={(cidade) => cidade.name}
          getOptionKey={(cidade) => cidade.uuid}
        />
        <LocalAutocomplete
          label="Bairro"
          disabled={!selectedCidade || isLoadingBairros}
          loading={isLoadingBairros}
          options={bairros}
          value={selectedBairro}
          onChange={setSelectedBairro}
          getOptionLabel={(bairro) => bairro.name}
          getOptionKey={(bairro) => bairro.uuid}
          error={Boolean(fieldErrors.bairro_uuid)}
          helperText={fieldErrors.bairro_uuid?.[0]}
        />
        <TextField
          label="Taxa de entrega"
          type="number"
          value={feeValue}
          onChange={(event) => setFeeValue(event.target.value)}
          error={Boolean(fieldErrors.fee)}
          helperText={fieldErrors.fee?.[0]}
          slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
        />
      </Box>

      <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 3 }}>
        <Button variant="outlined" startIcon={<AddCircleOutlineIcon />} disabled={isSubmitting} onClick={() => void handleSubmit()}>
          {isSubmitting ? 'Salvando…' : 'Salvar taxa'}
        </Button>
      </Stack>

      <Divider sx={{ mb: 2 }} />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={120} />
      ) : (
        !loadError &&
        (fees.length === 0 ? (
          <EmptyState
            icon={<LocalShippingOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
            title="Nenhum bairro atendido ainda"
            description="Cadastre a taxa de entrega dos bairros atendidos pela sua loja online."
          />
        ) : (
          <Stack spacing={1}>
            {fees.map((fee) => (
              <Stack
                key={fee.uuid}
                direction="row"
                sx={{
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  p: 1.5,
                  ...SOFT_PANEL_SX,
                }}
              >
                <Typography sx={{ fontSize: 14, fontWeight: 500 }}>{fee.bairro.name}</Typography>
                <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                  <Typography sx={{ fontSize: 14, fontWeight: 700, color: 'var(--mk-primary)' }}>
                    {formatCurrency(fee.fee)}
                  </Typography>
                  <IconButton
                    size="small"
                    aria-label={`Remover taxa de ${fee.bairro.name}`}
                    disabled={removingUuid === fee.uuid}
                    onClick={() => void handleDelete(fee.uuid)}
                  >
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              </Stack>
            ))}
          </Stack>
        ))
      )}
    </Paper>
  )
}

const COUPON_TYPE_LABELS: Record<CouponType, string> = {
  percentage: 'Percentual',
  fixed: 'Valor fixo',
  free_shipping: 'Frete grátis',
}

const EMPTY_COUPON_FORM: CouponPayload = {
  code: '',
  type: 'percentage',
  value: null,
  minimum_order_value: null,
  max_uses_total: null,
  max_uses_per_customer: null,
  starts_at: null,
  expires_at: null,
  is_active: true,
  allowed_payment_methods: [],
}

function toDateInput(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : ''
}

/** Seção 2: cupons de desconto sobre o carrinho todo (Delivery Fase 3) — CRUD completo, sem elegibilidade por produto. */
function CouponsSection() {
  const [coupons, setCoupons] = useState<Coupon[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [editingUuid, setEditingUuid] = useState<string | null>(null)
  const [form, setForm] = useState<CouponPayload>(EMPTY_COUPON_FORM)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [removingUuid, setRemovingUuid] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    couponService
      .listCoupons()
      .then(setCoupons)
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os cupons agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(load, [])

  function resetForm() {
    setEditingUuid(null)
    setForm(EMPTY_COUPON_FORM)
    setFieldErrors({})
  }

  function startEdit(coupon: Coupon) {
    setEditingUuid(coupon.uuid)
    setForm({
      code: coupon.code,
      type: coupon.type,
      value: coupon.value,
      minimum_order_value: coupon.minimum_order_value,
      max_uses_total: coupon.max_uses_total,
      max_uses_per_customer: coupon.max_uses_per_customer,
      starts_at: coupon.starts_at,
      expires_at: coupon.expires_at,
      is_active: coupon.is_active,
      allowed_payment_methods: coupon.allowed_payment_methods ?? [],
    })
    setFieldErrors({})
  }

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})

    const errors: Record<string, string[]> = {}
    if (!form.code.trim()) errors.code = ['Informe o código do cupom.']
    if (form.type !== 'free_shipping' && (form.value === null || form.value === undefined || form.value <= 0)) {
      errors.value = ['Informe um valor maior que zero.']
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }

    const payload: CouponPayload = {
      ...form,
      code: form.code.trim().toUpperCase(),
      value: form.type === 'free_shipping' ? null : form.value,
      // [] e null significam a mesma coisa pro usuário ("vale para
      // qualquer meio"), mas o backend só trata `null` como "sem
      // restrição" (CouponService::validateForCheckout) — um array vazio
      // bloquearia o cupom pra todo mundo.
      allowed_payment_methods: form.allowed_payment_methods?.length ? form.allowed_payment_methods : null,
    }

    setIsSubmitting(true)
    try {
      if (editingUuid) {
        await couponService.updateCoupon(editingUuid, payload)
      } else {
        await couponService.createCoupon(payload)
      }
      resetForm()
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o cupom agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete(uuid: string) {
    setRemovingUuid(uuid)
    setFormError(null)
    try {
      await couponService.deleteCoupon(uuid)
      setCoupons((current) => current.filter((coupon) => coupon.uuid !== uuid))
      if (editingUuid === uuid) resetForm()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível remover este cupom agora.'))
    } finally {
      setRemovingUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={{ ...SECTION_SX, mt: 3 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <LocalOfferOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Cupons de desconto</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Vale sobre o carrinho todo do pedido, sem restrição por produto ou categoria.
      </Typography>

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(3, minmax(0, 1fr))' }, gap: 2, mb: 2 }}>
        <TextField
          label="Código"
          value={form.code}
          onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))}
          error={Boolean(fieldErrors.code)}
          helperText={fieldErrors.code?.[0]}
        />
        <TextField
          select
          label="Tipo"
          value={form.type}
          onChange={(event) => setForm((current) => ({ ...current, type: event.target.value as CouponType }))}
        >
          {(Object.keys(COUPON_TYPE_LABELS) as CouponType[]).map((type) => (
            <MenuItem key={type} value={type}>
              {COUPON_TYPE_LABELS[type]}
            </MenuItem>
          ))}
        </TextField>
        <TextField
          label={form.type === 'percentage' ? 'Valor (%)' : 'Valor (R$)'}
          type="number"
          disabled={form.type === 'free_shipping'}
          value={form.type === 'free_shipping' ? '' : (form.value ?? '')}
          onChange={(event) =>
            setForm((current) => ({ ...current, value: event.target.value === '' ? null : Number(event.target.value) }))
          }
          error={Boolean(fieldErrors.value)}
          helperText={fieldErrors.value?.[0]}
          slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
        />
        <TextField
          label="Pedido mínimo (opcional)"
          type="number"
          value={form.minimum_order_value ?? ''}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              minimum_order_value: event.target.value === '' ? null : Number(event.target.value),
            }))
          }
          slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
        />
        <TextField
          label="Limite total de usos (opcional)"
          type="number"
          value={form.max_uses_total ?? ''}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              max_uses_total: event.target.value === '' ? null : Number(event.target.value),
            }))
          }
          slotProps={{ htmlInput: { min: 1, step: 1 } }}
        />
        <TextField
          label="Limite por cliente (opcional)"
          type="number"
          value={form.max_uses_per_customer ?? ''}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              max_uses_per_customer: event.target.value === '' ? null : Number(event.target.value),
            }))
          }
          slotProps={{ htmlInput: { min: 1, step: 1 } }}
        />
        <TextField
          label="Início (opcional)"
          type="date"
          value={toDateInput(form.starts_at)}
          onChange={(event) => setForm((current) => ({ ...current, starts_at: event.target.value || null }))}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="Fim (opcional)"
          type="date"
          value={toDateInput(form.expires_at)}
          onChange={(event) => setForm((current) => ({ ...current, expires_at: event.target.value || null }))}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Switch
            checked={form.is_active ?? true}
            onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
          />
          <Typography sx={{ fontSize: 14 }}>Ativo</Typography>
        </Stack>
      </Box>

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, mb: 0.5 }}>Formas de pagamento aceitas pelo cupom</Typography>
      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mb: 1 }}>
        Deixe todas desmarcadas para o cupom valer com qualquer forma de pagamento.
      </Typography>
      <FormGroup sx={{ mb: 2.5 }}>
        {PAYMENT_METHODS.map((method) => (
          <FormControlLabel
            key={method}
            control={
              <Checkbox
                checked={(form.allowed_payment_methods ?? []).includes(method)}
                onChange={(event) =>
                  setForm((current) => {
                    const currentMethods = current.allowed_payment_methods ?? []
                    return {
                      ...current,
                      allowed_payment_methods: event.target.checked
                        ? [...currentMethods, method]
                        : currentMethods.filter((item) => item !== method),
                    }
                  })
                }
              />
            }
            label={PAYMENT_METHOD_SETTINGS_LABELS[method]}
          />
        ))}
      </FormGroup>

      <Stack direction="row" spacing={1.5} sx={{ justifyContent: 'flex-end', mb: 3 }}>
        {editingUuid && (
          <Button variant="text" onClick={resetForm} disabled={isSubmitting}>
            Cancelar edição
          </Button>
        )}
        <Button variant="outlined" startIcon={<AddCircleOutlineIcon />} disabled={isSubmitting} onClick={() => void handleSubmit()}>
          {isSubmitting ? 'Salvando…' : editingUuid ? 'Salvar alterações' : 'Criar cupom'}
        </Button>
      </Stack>

      <Divider sx={{ mb: 2 }} />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={120} />
      ) : (
        !loadError &&
        (coupons.length === 0 ? (
          <EmptyState
            icon={<LocalOfferOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
            title="Nenhum cupom cadastrado ainda"
            description="Crie um cupom percentual, de valor fixo ou de frete grátis pra sua loja online."
          />
        ) : (
          <Stack spacing={1}>
            {coupons.map((coupon) => (
              <Stack
                key={coupon.uuid}
                direction="row"
                sx={{
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  p: 1.5,
                  ...SOFT_PANEL_SX,
                  opacity: coupon.is_active ? 1 : 0.6,
                }}
              >
                <Box>
                  <Typography sx={{ fontSize: 14, fontWeight: 700 }}>{coupon.code}</Typography>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                    {COUPON_TYPE_LABELS[coupon.type]}
                    {coupon.type !== 'free_shipping' && coupon.value !== null
                      ? ` — ${coupon.type === 'percentage' ? `${coupon.value}%` : formatCurrency(coupon.value)}`
                      : ''}
                    {!coupon.is_active ? ' · inativo' : ''}
                    {coupon.allowed_payment_methods?.length
                      ? ` · só ${coupon.allowed_payment_methods.map((method) => PAYMENT_METHOD_SETTINGS_LABELS[method]).join(', ')}`
                      : ''}
                  </Typography>
                </Box>
                <Stack direction="row" spacing={0.5}>
                  <Button size="small" onClick={() => startEdit(coupon)}>
                    Editar
                  </Button>
                  <IconButton
                    size="small"
                    aria-label={`Remover cupom ${coupon.code}`}
                    disabled={removingUuid === coupon.uuid}
                    onClick={() => void handleDelete(coupon.uuid)}
                  >
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              </Stack>
            ))}
          </Stack>
        ))
      )}
    </Paper>
  )
}

const PROMOTION_DISCOUNT_TYPE_LABELS: Record<ProductPromotionDiscountType, string> = {
  fixed_price: 'Preço fixo (de/por)',
  percentage: 'Percentual (%)',
}

/** Seção 3: preço promocional "de/por" ou percentual por produto (Delivery Fase 3) — upsert 1 por produto, mesmo padrão de Taxa de Entrega. */
function PromotionsSection() {
  const [promotions, setPromotions] = useState<ProductPromotion[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [products, setProducts] = useState<Product[]>([])
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null)
  const [discountType, setDiscountType] = useState<ProductPromotionDiscountType>('fixed_price')
  const [promoPrice, setPromoPrice] = useState('')
  const [discountPercentage, setDiscountPercentage] = useState('')
  const [startsAt, setStartsAt] = useState('')
  const [expiresAt, setExpiresAt] = useState('')

  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [removingUuid, setRemovingUuid] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    productPromotionService
      .listPromotions()
      .then(setPromotions)
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as promoções agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
    // Catálogo de produtos carregado uma vez, sem busca incremental —
    // simplificação aceitável pro tamanho típico de catálogo desta tela.
    productService
      .listProducts({ per_page: 200 })
      .then((result) => setProducts(result.items))
      .catch(() => undefined)
  }, [])

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})

    const errors: Record<string, string[]> = {}
    if (!selectedProduct) errors.product_uuid = ['Selecione um produto.']

    const priceNumber = Number(promoPrice)
    const percentageNumber = Number(discountPercentage)
    if (discountType === 'fixed_price') {
      if (!promoPrice.trim() || Number.isNaN(priceNumber) || priceNumber < 0) {
        errors.promo_price = ['Informe um preço promocional válido.']
      }
    } else {
      if (!discountPercentage.trim() || Number.isNaN(percentageNumber) || percentageNumber <= 0 || percentageNumber > 100) {
        errors.discount_percentage = ['Informe um percentual entre 0,01 e 100.']
      }
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }

    setIsSubmitting(true)
    try {
      await productPromotionService.upsertPromotion({
        product_uuid: selectedProduct!.uuid,
        discount_type: discountType,
        promo_price: discountType === 'fixed_price' ? priceNumber : null,
        discount_percentage: discountType === 'percentage' ? percentageNumber : null,
        starts_at: startsAt || null,
        expires_at: expiresAt || null,
      })
      setSelectedProduct(null)
      setDiscountType('fixed_price')
      setPromoPrice('')
      setDiscountPercentage('')
      setStartsAt('')
      setExpiresAt('')
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a promoção agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete(uuid: string) {
    setRemovingUuid(uuid)
    setFormError(null)
    try {
      await productPromotionService.deletePromotion(uuid)
      setPromotions((current) => current.filter((promo) => promo.uuid !== uuid))
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível remover esta promoção agora.'))
    } finally {
      setRemovingUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={{ ...SECTION_SX, mt: 3 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <SellOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Preço promocional "de/por"</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Enquanto ativa, a promoção sempre vence qualquer outro preço (inclusive desconto de categoria) no catálogo e no checkout.
      </Typography>

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))', md: 'repeat(5, minmax(0, 1fr))' }, gap: 2, mb: 2 }}>
        <LocalAutocomplete
          label="Produto"
          options={products}
          value={selectedProduct}
          onChange={setSelectedProduct}
          getOptionLabel={(product) => product.name}
          getOptionKey={(product) => product.uuid}
          error={Boolean(fieldErrors.product_uuid)}
          helperText={fieldErrors.product_uuid?.[0]}
        />
        <TextField
          select
          label="Tipo de desconto"
          value={discountType}
          onChange={(event) => setDiscountType(event.target.value as ProductPromotionDiscountType)}
        >
          {(Object.keys(PROMOTION_DISCOUNT_TYPE_LABELS) as ProductPromotionDiscountType[]).map((type) => (
            <MenuItem key={type} value={type}>
              {PROMOTION_DISCOUNT_TYPE_LABELS[type]}
            </MenuItem>
          ))}
        </TextField>
        {discountType === 'fixed_price' ? (
          <TextField
            label="Preço promocional"
            type="number"
            value={promoPrice}
            onChange={(event) => setPromoPrice(event.target.value)}
            error={Boolean(fieldErrors.promo_price)}
            helperText={fieldErrors.promo_price?.[0]}
            slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
          />
        ) : (
          <TextField
            label="Percentual de desconto"
            type="number"
            value={discountPercentage}
            onChange={(event) => setDiscountPercentage(event.target.value)}
            error={Boolean(fieldErrors.discount_percentage)}
            helperText={fieldErrors.discount_percentage?.[0]}
            slotProps={{ htmlInput: { min: 0.01, max: 100, step: '0.01' } }}
          />
        )}
        <TextField
          label="Início (opcional)"
          type="date"
          value={startsAt}
          onChange={(event) => setStartsAt(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="Fim (opcional)"
          type="date"
          value={expiresAt}
          onChange={(event) => setExpiresAt(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 3 }}>
        <Button variant="outlined" startIcon={<AddCircleOutlineIcon />} disabled={isSubmitting} onClick={() => void handleSubmit()}>
          {isSubmitting ? 'Salvando…' : 'Salvar promoção'}
        </Button>
      </Stack>

      <Divider sx={{ mb: 2 }} />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={120} />
      ) : (
        !loadError &&
        (promotions.length === 0 ? (
          <EmptyState
            icon={<SellOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
            title="Nenhuma promoção ativa"
            description="Configure um preço promocional pra destacar um produto na loja online."
          />
        ) : (
          <Stack spacing={1}>
            {promotions.map((promo) => (
              <Stack
                key={promo.uuid}
                direction="row"
                sx={{
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  p: 1.5,
                  ...SOFT_PANEL_SX,
                }}
              >
                <Typography sx={{ fontSize: 14, fontWeight: 500 }}>{promo.product.name}</Typography>
                <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                  {promo.discount_type === 'percentage' ? (
                    <Typography sx={{ fontSize: 14, fontWeight: 700, color: 'var(--mk-primary)' }}>
                      {String(promo.discount_percentage).replace('.', ',')}% OFF
                      {promo.effective_price !== undefined ? ` · ${formatCurrency(promo.effective_price)}` : ''}
                    </Typography>
                  ) : (
                    <Typography sx={{ fontSize: 14, fontWeight: 700, color: 'var(--mk-primary)' }}>
                      {formatCurrency(promo.promo_price ?? 0)}
                    </Typography>
                  )}
                  <IconButton
                    size="small"
                    aria-label={`Remover promoção de ${promo.product.name}`}
                    disabled={removingUuid === promo.uuid}
                    onClick={() => void handleDelete(promo.uuid)}
                  >
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              </Stack>
            ))}
          </Stack>
        ))
      )}
    </Paper>
  )
}

/**
 * Tela gated por `storefront:update` (ver `access/requirements.ts`) — só os
 * 3 CRUDs de registros próprios da loja online (taxas por bairro, cupons,
 * promoções). Horário de funcionamento e endereço da loja migraram para o
 * bloco "Horário e Endereço" do hub `/configuracoes` (2026-07-24) por serem
 * variável única/singleton, não CRUD.
 */
export function StoreBusinessSettingsPage() {
  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, width: '100%', minWidth: 0, flex: 1 }}>
      <PageHeader title="Loja online" subtitle="Entregas, cupons e promoções da sua loja online." />
      <DeliveryFeeSection />
      <CouponsSection />
      <PromotionsSection />
    </Box>
  )
}
