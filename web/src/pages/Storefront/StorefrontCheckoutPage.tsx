import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import ContentCopyIcon from '@mui/icons-material/ContentCopy'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  FormControlLabel,
  MenuItem,
  Paper,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ClientAddressFields, type ClientAddressValues } from '../../components/client/ClientAddressFields'
import { OtpIdentifyForm } from '../../components/storefront/OtpIdentifyForm'
import { Logo } from '../../components/ui/Logo'
import { useCartAbandonmentTelemetry } from '../../hooks/useCartAbandonmentTelemetry'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { getOrderTracking } from '../../services/orderTrackingService'
import * as portalAddressService from '../../services/portalAddressService'
import * as portalOrderService from '../../services/portalOrderService'
import * as storefrontCheckoutService from '../../services/storefrontCheckoutService'
import * as storefrontLocationService from '../../services/storefrontLocationService'
import * as storefrontService from '../../services/storefrontService'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import type { OrderPayment } from '../../types/order'
import {
  BELOW_MINIMUM_ORDER_CODE,
  COUPON_USAGE_LIMIT_REACHED_CODE,
  DELIVERY_AREA_NOT_SERVED_CODE,
  INSUFFICIENT_STOCK_CODE,
  INVALID_COUPON_CODE,
  STORE_CLOSED_CODE,
  STORE_PICKUP_UNAVAILABLE_CODE,
  type StorefrontCheckoutPayload,
} from '../../types/storefront'
import type { Bairro, Cidade, Estado, ReverseGeocodeResult } from '../../types/location'
import { formatCurrency } from '../../utils/format'

/** Distingue `GeolocationPositionError` (não estende `Error`) de erros de rede/API — mesmo helper de `ClientFormPage.tsx`. */
function isGeolocationPositionError(error: unknown): error is GeolocationPositionError {
  return typeof error === 'object' && error !== null && 'code' in error && 'PERMISSION_DENIED' in error
}

function geolocationErrorMessage(error: unknown): string {
  if (isGeolocationPositionError(error)) {
    if (error.code === error.PERMISSION_DENIED) {
      return 'Permissão de localização negada. Habilite o acesso à localização do navegador para usar este recurso.'
    }
    if (error.code === error.TIMEOUT) {
      return 'Não foi possível obter sua localização a tempo. Tente novamente.'
    }
    return 'Não foi possível obter sua localização agora (verifique se o site está em HTTPS). Preencha o endereço manualmente.'
  }
  return getApiErrorMessage(error, 'Não foi possível obter sua localização agora. Preencha o endereço manualmente.')
}

type DeliveryFeeStatus = 'idle' | 'loading' | 'available' | 'not-served' | 'error'
type CouponStatus = 'idle' | 'loading' | 'applied' | 'invalid'

const EMPTY_ADDRESS: ClientAddressValues = {
  estado_uuid: '',
  cidade_uuid: '',
  bairro_uuid: '',
  logradouro: '',
  numero: '',
  complemento: '',
  cep: '',
}

function PageShell({ slug, children }: { slug: string; children: React.ReactNode }) {
  const navigate = useNavigate()

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--pt-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 520 }}>
        <Button startIcon={<ArrowBackIcon />} color="inherit" onClick={() => navigate(`/loja/${slug}/carrinho`)} sx={{ ml: -1, mb: 1 }}>
          Voltar ao carrinho
        </Button>
        <Stack spacing={0.5} sx={{ alignItems: 'center', mb: 3 }}>
          <Logo variant="mark" size={36} />
        </Stack>
        {children}
        <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 4 }}>
          <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)', textAlign: 'center' }}>
            Checkout via PegaTicket — gestão clara para empresas em movimento.
          </Typography>
        </Stack>
      </Box>
    </Box>
  )
}

/**
 * Passo 3 (só quando o cliente escolhe "Pix agora"): pedido já foi criado
 * com sucesso — daqui em diante nenhuma falha pode deixar a tela quebrada,
 * o pedido existe de qualquer forma. Gera a cobrança ao montar; erro na
 * geração mostra retry + saída para o rastreio (pagar na entrega continua
 * possível). Sem polling automático (não pedido) — verificação é manual via
 * "Já paguei, verificar", que só relê `GET /rastreio/{uuid}` (público,
 * já reflete `is_paid` assim que o webhook do PSP confirmar).
 */
function PixPaymentPanel({ orderUuid }: { orderUuid: string }) {
  const navigate = useNavigate()
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [payment, setPayment] = useState<OrderPayment | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [isCheckingPayment, setIsCheckingPayment] = useState(false)
  const [checkMessage, setCheckMessage] = useState<string | null>(null)

  const loadCharge = useCallback(() => {
    setStatus('loading')
    setErrorMessage(null)
    setCheckMessage(null)
    portalOrderService
      .createOrderPixCharge(orderUuid)
      .then((result) => {
        setPayment(result)
        setStatus('ready')
      })
      .catch((error: unknown) => {
        setStatus('error')
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível gerar a cobrança Pix agora.'))
      })
  }, [orderUuid])

  useEffect(() => {
    loadCharge()
  }, [loadCharge])

  async function handleCopy() {
    const code = payment?.metadata?.qr_code
    if (!code) return
    try {
      await navigator.clipboard.writeText(code)
      setCopied(true)
      setTimeout(() => setCopied(false), 2500)
    } catch {
      setCopied(false)
    }
  }

  async function handleCheckPayment() {
    setIsCheckingPayment(true)
    setCheckMessage(null)
    try {
      const tracking = await getOrderTracking(orderUuid)
      if (tracking.is_paid) {
        navigate(`/rastreio/${orderUuid}`)
        return
      }
      setCheckMessage('Ainda não identificamos o pagamento. Aguarde alguns instantes após pagar e tente de novo.')
    } catch {
      setCheckMessage('Não foi possível verificar o pagamento agora. Tente novamente em instantes.')
    } finally {
      setIsCheckingPayment(false)
    }
  }

  const qrCode = payment?.metadata?.qr_code ?? null
  const qrCodeBase64 = payment?.metadata?.qr_code_base64 ?? null
  const ticketUrl = payment?.metadata?.ticket_url ?? null

  return (
    <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 } }}>
      <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 0.5 }}>Pagar com Pix</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2.5 }}>
        Seu pedido já foi confirmado. Escaneie o QR Code ou copie o código Pix para concluir o pagamento.
      </Typography>

      {status === 'loading' && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1.5}>
          <CircularProgress size={28} />
          <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Gerando cobrança Pix…</Typography>
        </Stack>
      )}

      {status === 'error' && (
        <Stack spacing={2}>
          <Alert severity="error" variant="outlined">
            {errorMessage}
          </Alert>
          <Button variant="contained" onClick={loadCharge}>
            Tentar gerar o Pix novamente
          </Button>
          <Button variant="text" onClick={() => navigate(`/rastreio/${orderUuid}`)}>
            Continuar sem pagar agora (pagar na entrega)
          </Button>
        </Stack>
      )}

      {status === 'ready' && (
        <Stack spacing={2.5}>
          {qrCode ? (
            <Box sx={{ ...SOFT_PANEL_SX, display: 'flex', justifyContent: 'center', p: 2 }}>
              <QRCodeSVG value={qrCode} size={200} />
            </Box>
          ) : qrCodeBase64 ? (
            <Box sx={{ display: 'flex', justifyContent: 'center' }}>
              <Box
                component="img"
                src={`data:image/png;base64,${qrCodeBase64}`}
                alt="QR Code Pix"
                sx={{ width: 200, height: 200, borderRadius: 'var(--pt-radius-lg)' }}
              />
            </Box>
          ) : ticketUrl ? (
            <Button component="a" href={ticketUrl} target="_blank" rel="noopener noreferrer" variant="outlined" fullWidth>
              Abrir cobrança Pix
            </Button>
          ) : (
            <Alert severity="warning" variant="outlined">
              A cobrança foi criada, mas ainda não recebemos os dados do QR Code. Você pode tentar novamente ou pagar na
              entrega.
            </Alert>
          )}

          {qrCode && (
            <Stack spacing={1}>
              <TextField
                label="Pix copia e cola"
                value={qrCode}
                slotProps={{ input: { readOnly: true } }}
                multiline
                minRows={2}
                fullWidth
                size="small"
              />
              <Button startIcon={<ContentCopyIcon />} variant="outlined" onClick={() => void handleCopy()}>
                {copied ? 'Código copiado!' : 'Copiar código Pix'}
              </Button>
            </Stack>
          )}

          {(qrCode || qrCodeBase64) && (
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
              Abra o app do seu banco, escolha pagar via Pix e escaneie o código ou cole o "Pix copia e cola". A
              confirmação acontece automaticamente assim que o pagamento for identificado.
            </Typography>
          )}

          {checkMessage && (
            <Alert severity="info" variant="outlined">
              {checkMessage}
            </Alert>
          )}

          <Button variant="contained" size="large" onClick={() => void handleCheckPayment()} disabled={isCheckingPayment} sx={{ minHeight: UI_SIZE.controlLarge }}>
            {isCheckingPayment ? 'Verificando…' : 'Já paguei, verificar pagamento'}
          </Button>
          <Button variant="text" onClick={() => navigate(`/rastreio/${orderUuid}`)}>
            Ver status do meu pedido
          </Button>
        </Stack>
      )}
    </Paper>
  )
}

/** Passo 2: dados de entrega + endereço (`ClientAddressFields` reaproveitado) + resumo + confirmação. */
function DetailsAndReviewStep({ slug }: { slug: string }) {
  const navigate = useNavigate()
  const { items, totalAmount, clear } = useStorefrontCart()
  const { markCompleted } = useCartAbandonmentTelemetry(slug)

  const [clientName, setClientName] = useState('')
  const [clientLastName, setClientLastName] = useState('')
  const [clientPhone, setClientPhone] = useState('')
  const [notes, setNotes] = useState('')
  const [address, setAddress] = useState<ClientAddressValues>(EMPTY_ADDRESS)

  const [estados, setEstados] = useState<Estado[]>([])
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [isLoadingEstados, setIsLoadingEstados] = useState(true)
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)
  const [isLocatingGps, setIsLocatingGps] = useState(false)
  const [isCepLoading, setIsCepLoading] = useState(false)
  const [cepNotFoundMessage, setCepNotFoundMessage] = useState<string | null>(null)

  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [deliveryFeeStatus, setDeliveryFeeStatus] = useState<DeliveryFeeStatus>('idle')
  const [deliveryFee, setDeliveryFee] = useState<number | null>(null)

  const [couponCode, setCouponCode] = useState('')
  const [couponStatus, setCouponStatus] = useState<CouponStatus>('idle')
  const [couponMessage, setCouponMessage] = useState<string | null>(null)
  const [appliedDiscount, setAppliedDiscount] = useState(0)
  const [appliedCouponCode, setAppliedCouponCode] = useState<string | null>(null)

  // Checkout Pix (roadmap Fase B, item 1) — só oferecido se a loja aceita
  // Pix (`tenant_settings.accepted_payment_methods`). Escolha é só local:
  // o backend de checkout não recebe forma de pagamento, a cobrança Pix é
  // gerada à parte, depois do pedido criado (ver handleSubmit).
  const [acceptedPaymentMethods, setAcceptedPaymentMethods] = useState<PaymentMethod[]>([])
  // Radio "combinar na entrega" vs "Pagar com Pix agora" foi removido
  // temporariamente (sistema não processa pagamento ainda) — `paymentMethod`
  // fica fixo em 'delivery'. O bloco `if ((paymentMethod as string) === 'pix')` em
  // handleSubmit vira código dormente, não é dead-code deletável agora (é
  // feature desligada, não removida).
  const paymentMethod: 'delivery' | 'pix' = 'delivery'
  const [paidOrderUuid, setPaidOrderUuid] = useState<string | null>(null)

  // Retirada na loja / entrega (roadmap Delivery) — só oferecidas quando o
  // tenant habilitou `allow_store_pickup`/`allow_delivery` (mesma chamada que
  // já busca accepted_payment_methods acima, sem round-trip extra).
  const [allowStorePickup, setAllowStorePickup] = useState(false)
  const [allowDelivery, setAllowDelivery] = useState(true)
  const [fulfillmentType, setFulfillmentType] = useState<'delivery' | 'pickup'>('delivery')

  // Meio de pagamento pretendido (roadmap cupom por meio de pagamento) —
  // distinto do `paymentMethod` acima (que só decide "pagar Pix agora" vs
  // "combinar na entrega"): este campo é enviado ao backend pra validar
  // cupons restritos a um meio específico (`allowed_payment_methods`).
  const [intendedPaymentMethod, setIntendedPaymentMethod] = useState<PaymentMethod | ''>('')

  // Troco (roadmap "Método de pagamento + troco") — só faz sentido quando o
  // cliente pretende pagar em dinheiro; `changeForAmount` fica em string pra
  // permitir digitação livre, convertido só no payload do checkout.
  const [needsChange, setNeedsChange] = useState(false)
  const [changeForAmount, setChangeForAmount] = useState('')

  useEffect(() => {
    let cancelled = false
    storefrontService
      .getStorefront(slug)
      .then((tenant) => {
        if (!cancelled) {
          // `allow_delivery` é default true no backend — `?? true` cobre respostas
          // antigas/mocks sem o campo, pra não forçar retirada indevidamente.
          const tenantAllowDelivery = tenant.allow_delivery ?? true
          const tenantAllowStorePickup = tenant.allow_store_pickup ?? false
          setAcceptedPaymentMethods(tenant.accepted_payment_methods)
          setAllowStorePickup(tenantAllowStorePickup)
          setAllowDelivery(tenantAllowDelivery)
          // Quando só um dos dois métodos está ativo, não há escolha — força
          // o único método disponível (o RadioGroup abaixo só aparece quando
          // os dois estão ativos ao mesmo tempo).
          if (!tenantAllowDelivery) {
            setFulfillmentType('pickup')
          } else if (!tenantAllowStorePickup) {
            setFulfillmentType('delivery')
          }
        }
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [slug])

  // Pré-preenchimento a partir do cadastro já existente (roadmap Loja) —
  // Client/Endereco já vinculados a essa loja no 1º pedido (mesmo e-mail,
  // ver StorefrontCheckoutService::resolveClientUuid()). Reaproveita o
  // MESMO endpoint de "Meus endereços" do Portal (GET /portal/addresses),
  // filtrando pela loja atual — evita criar um endpoint novo só pra isso.
  // Falha silenciosa: sem cadastro anterior é o caminho normal (1ª
  // compra), não é erro. Só preenche campo que ainda está vazio — nunca
  // sobrescreve o que o cliente já digitou.
  useEffect(() => {
    let cancelled = false
    portalAddressService
      .listPortalAddresses()
      .then(async (addresses) => {
        if (cancelled) return
        const existing = addresses.find((item) => item.tenant_slug === slug)
        if (!existing) return

        if (existing.client_name) setClientName((current) => current || existing.client_name!)
        if (existing.client_phone) setClientPhone((current) => current || existing.client_phone!)

        const endereco = existing.endereco
        if (!endereco) return

        if (endereco.estado_uuid) {
          const cidadesList = await handleEstadoChange(endereco.estado_uuid)
          if (cancelled) return
          if (endereco.cidade_uuid && cidadesList.some((cidade) => cidade.uuid === endereco.cidade_uuid)) {
            const bairrosList = await handleCidadeChange(endereco.cidade_uuid)
            if (cancelled) return
            if (endereco.bairro_uuid && bairrosList.some((bairro) => bairro.uuid === endereco.bairro_uuid)) {
              handleBairroChange(endereco.bairro_uuid)
            }
          }
        }

        setAddress((current) => ({
          ...current,
          logradouro: current.logradouro.trim() ? current.logradouro : endereco.logradouro ?? current.logradouro,
          numero: current.numero.trim() ? current.numero : endereco.numero ?? current.numero,
          complemento: current.complemento.trim() ? current.complemento : endereco.complemento ?? current.complemento,
          cep: current.cep.trim() ? current.cep : endereco.cep ?? current.cep,
        }))
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug])

  // Prévia pública de cupom (Delivery Fase 3) — não checa limite por
  // cliente nem calcula frete grátis de verdade (ver
  // StorefrontCouponValidationResult), só o desconto geral estimado.
  async function handleApplyCoupon() {
    const code = couponCode.trim()
    if (!code) return
    setCouponStatus('loading')
    setCouponMessage(null)
    try {
      const result = await storefrontService.validateStorefrontCoupon(
        slug,
        code,
        items.map((item) => ({
          ticket_type_uuid: item.ticket_type_uuid,
          event_product_uuid: item.event_product_uuid,
          quantity: item.quantity,
        })),
      )
      setAppliedDiscount(result.discount_amount)
      setAppliedCouponCode(code)
      setCouponStatus('applied')
      setCouponMessage(
        result.discount_amount > 0
          ? `Cupom aplicado: -${formatCurrency(result.discount_amount)}`
          : 'Cupom aplicado — o desconto final é confirmado ao concluir o pedido.',
      )
    } catch (error) {
      setAppliedDiscount(0)
      setAppliedCouponCode(null)
      setCouponStatus('invalid')
      setCouponMessage(getApiErrorMessage(error, 'Não foi possível validar este cupom.'))
    }
  }

  function handleRemoveCoupon() {
    setCouponCode('')
    setCouponStatus('idle')
    setCouponMessage(null)
    setAppliedDiscount(0)
    setAppliedCouponCode(null)
  }

  // Consulta prévia de taxa por bairro (Delivery Fase 2) — dispara sempre
  // que o bairro escolhido muda, antes de confirmar o pedido (evita
  // surpresa/bloqueio só no submit final).
  useEffect(() => {
    if (!address.bairro_uuid) {
      setDeliveryFeeStatus('idle')
      setDeliveryFee(null)
      return
    }
    let cancelled = false
    setDeliveryFeeStatus('loading')
    setDeliveryFee(null)
    storefrontService
      .getStorefrontDeliveryFee(slug, address.bairro_uuid)
      .then((fee) => {
        if (cancelled) return
        setDeliveryFee(fee)
        setDeliveryFeeStatus('available')
      })
      .catch((error: unknown) => {
        if (cancelled) return
        if (error instanceof ApiRequestError && error.code === DELIVERY_AREA_NOT_SERVED_CODE) {
          setDeliveryFeeStatus('not-served')
        } else {
          setDeliveryFeeStatus('error')
        }
      })
    return () => {
      cancelled = true
    }
  }, [slug, address.bairro_uuid])

  useEffect(() => {
    let cancelled = false
    storefrontLocationService
      .getStorefrontEstados()
      .then((list) => {
        if (!cancelled) setEstados(list)
      })
      .catch(() => undefined)
      .finally(() => {
        if (!cancelled) setIsLoadingEstados(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  async function handleEstadoChange(estadoUuid: string): Promise<Cidade[]> {
    setAddress((current) => ({ ...current, estado_uuid: estadoUuid, cidade_uuid: '', bairro_uuid: '' }))
    setCidades([])
    setBairros([])
    if (!estadoUuid) return []
    setIsLoadingCidades(true)
    try {
      const list = await storefrontLocationService.getStorefrontCidades(estadoUuid)
      setCidades(list)
      return list
    } catch {
      setCidades([])
      return []
    } finally {
      setIsLoadingCidades(false)
    }
  }

  async function handleCidadeChange(cidadeUuid: string): Promise<Bairro[]> {
    setAddress((current) => ({ ...current, cidade_uuid: cidadeUuid, bairro_uuid: '' }))
    setBairros([])
    if (!cidadeUuid) return []
    setIsLoadingBairros(true)
    try {
      const list = await storefrontLocationService.getStorefrontBairros(cidadeUuid)
      setBairros(list)
      return list
    } catch {
      setBairros([])
      return []
    } finally {
      setIsLoadingBairros(false)
    }
  }

  function handleBairroChange(bairroUuid: string) {
    setAddress((current) => ({ ...current, bairro_uuid: bairroUuid }))
  }

  function handleAddressTextChange(field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) {
    setAddress((current) => ({ ...current, [field]: value }))
  }

  /**
   * Preenchimento em cascata compartilhado por CEP e geolocalização — cada
   * nível só é aplicado se o anterior bateu na lista recém-carregada
   * (mesmo padrão de `ClientFormPage::handleUseCurrentLocation`).
   * `numero`/`complemento` nunca são tocados (são exatamente os dados que
   * "faltam" pro usuário preencher).
   */
  async function applyResolvedAddress(result: ReverseGeocodeResult) {
    if (result.estado_uuid) {
      const cidadesList = await handleEstadoChange(result.estado_uuid)
      if (result.cidade_uuid && cidadesList.some((cidade) => cidade.uuid === result.cidade_uuid)) {
        const bairrosList = await handleCidadeChange(result.cidade_uuid)
        if (result.bairro_uuid && bairrosList.some((bairro) => bairro.uuid === result.bairro_uuid)) {
          handleBairroChange(result.bairro_uuid)
        }
      }
    }

    setAddress((current) => ({
      ...current,
      logradouro: !current.logradouro.trim() && result.logradouro ? result.logradouro : current.logradouro,
      cep: !current.cep.trim() && result.cep ? result.cep : current.cep,
    }))
  }

  /** "Não sei meu CEP" → geolocalização do navegador → reverse geocode → cascata. */
  async function handleUseCurrentLocation() {
    setFormError(null)

    if (!navigator.geolocation) {
      setFormError('Seu navegador não oferece suporte a geolocalização.')
      return
    }

    setIsLocatingGps(true)
    try {
      const position = await new Promise<GeolocationPosition>((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000, enableHighAccuracy: true })
      })
      const result = await storefrontLocationService.reverseGeocodeStorefront(position.coords.latitude, position.coords.longitude)
      await applyResolvedAddress(result)
    } catch (error) {
      setFormError(geolocationErrorMessage(error))
    } finally {
      setIsLocatingGps(false)
    }
  }

  // Consulta automática de CEP (ViaCEP) — dispara ao completar 8 dígitos,
  // preenche o que faltar via applyResolvedAddress(); não encontrado é um
  // aviso pequeno e não bloqueante, nunca um Alert de página inteira (o
  // usuário sempre pode preencher manualmente).
  const debouncedCep = useDebouncedValue(address.cep, 500)

  useEffect(() => {
    const digits = debouncedCep.replace(/\D/g, '')
    if (digits.length !== 8) {
      setCepNotFoundMessage(null)
      return
    }

    let cancelled = false
    setIsCepLoading(true)
    setCepNotFoundMessage(null)

    storefrontLocationService
      .lookupStorefrontCep(digits)
      .then((result) => {
        if (!cancelled) return applyResolvedAddress(result)
      })
      .catch((error: unknown) => {
        if (cancelled) return
        if (error instanceof ApiRequestError && error.code === 'CEP_NOT_FOUND') {
          setCepNotFoundMessage('CEP não encontrado — preencha o endereço manualmente.')
        }
      })
      .finally(() => {
        if (!cancelled) setIsCepLoading(false)
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedCep])

  function validate(): Record<string, string[]> {
    const errors: Record<string, string[]> = {}
    if (clientName.trim().length < 2) errors.client_name = ['Informe seu nome.']
    if (clientLastName.trim().length < 1) errors.client_last_name = ['Informe seu sobrenome.']
    if (clientPhone.trim().length < 8) errors.client_phone = ['Informe um telefone válido.']
    if (fulfillmentType === 'delivery') {
      if (!address.estado_uuid) errors.estado_uuid = ['Campo obrigatório.']
      if (!address.cidade_uuid) errors.cidade_uuid = ['Campo obrigatório.']
      if (!address.bairro_uuid) errors.bairro_uuid = ['Campo obrigatório.']
      if (!address.logradouro.trim()) errors.logradouro = ['Campo obrigatório.']
    }
    if (acceptedPaymentMethods.length > 0 && !intendedPaymentMethod) {
      errors.payment_method = ['Selecione uma forma de pagamento.']
    }
    return errors
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    const clientErrors = validate()
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }
    setFieldErrors({})
    setIsSubmitting(true)

    const payload: StorefrontCheckoutPayload = {
      items: items.map((item) => ({
        ticket_type_uuid: item.ticket_type_uuid,
        event_product_uuid: item.event_product_uuid,
        quantity: item.quantity,
        notes: item.notes?.trim() || undefined,
      })),
      client_name: clientName.trim(),
      client_last_name: clientLastName.trim(),
      client_phone: clientPhone.trim(),
      notes: notes.trim() || undefined,
      fulfillment_type: fulfillmentType,
      ...(fulfillmentType === 'delivery'
        ? {
            estado_uuid: address.estado_uuid,
            cidade_uuid: address.cidade_uuid,
            bairro_uuid: address.bairro_uuid,
            logradouro: address.logradouro.trim(),
            numero: address.numero.trim() || undefined,
            complemento: address.complemento.trim() || undefined,
            cep: address.cep.trim() || undefined,
          }
        : {}),
      coupon_code: appliedCouponCode ?? undefined,
      payment_method: intendedPaymentMethod || undefined,
      needs_change: (intendedPaymentMethod === 'cash' && needsChange) || undefined,
      change_for_amount:
        intendedPaymentMethod === 'cash' && needsChange ? Number(changeForAmount) : undefined,
    }

    try {
      const result = await storefrontCheckoutService.checkout(slug, payload)
      markCompleted()
      clear()
      if ((paymentMethod as string) === 'pix') {
        // Pedido já criado com sucesso a partir daqui — trocar de tela pra
        // gerar o Pix, nunca perder o pedido se a cobrança falhar (o
        // PixPaymentPanel sempre oferece "continuar sem pagar agora").
        setPaidOrderUuid(result.order.uuid)
      } else {
        navigate(`/rastreio/${result.order.uuid}`)
      }
    } catch (error) {
      if (error instanceof ApiRequestError && error.code === INSUFFICIENT_STOCK_CODE) {
        setFormError('Item sem estoque suficiente no momento. Ajuste as quantidades e tente novamente.')
      } else if (error instanceof ApiRequestError && error.code === INVALID_COUPON_CODE) {
        // Mesmo `code` cobre tanto "cupom inválido" quanto "cupom exige um
        // meio de pagamento específico" (coupon.payment_method_not_allowed)
        // — a mensagem já vem pronta e traduzida do backend. Mostrada perto
        // do cupom/meio de pagamento, não como erro genérico de topo de
        // página; mantém o código digitado pra reaplicar após ajustar o
        // meio de pagamento.
        setCouponStatus('invalid')
        setCouponMessage(error.message)
      } else if (
        error instanceof ApiRequestError &&
        (error.code === STORE_CLOSED_CODE ||
          error.code === BELOW_MINIMUM_ORDER_CODE ||
          error.code === DELIVERY_AREA_NOT_SERVED_CODE ||
          error.code === COUPON_USAGE_LIMIT_REACHED_CODE ||
          error.code === STORE_PICKUP_UNAVAILABLE_CODE)
      ) {
        // Mensagem já pronta pra exibir direto (traduzida no backend) —
        // mesmo padrão do INSUFFICIENT_STOCK_CODE acima, sem reformular.
        setFormError(error.message)
        if (error.code === DELIVERY_AREA_NOT_SERVED_CODE) {
          // Pode acontecer de novo aqui mesmo com a consulta prévia OK (ex.:
          // taxa removida entre a consulta e o submit) — reflete no estado
          // pra desabilitar o botão até o bairro ser reavaliado.
          setDeliveryFeeStatus('not-served')
          setDeliveryFee(null)
        }
        if (error.code === COUPON_USAGE_LIMIT_REACHED_CODE) {
          // Cupom válido na prévia mas rejeitado no submit final (ex.:
          // atingiu o limite entre a prévia e o envio) — remove pra não
          // travar o cliente tentando de novo com o mesmo código.
          handleRemoveCoupon()
        }
      } else {
        setFormError(getApiErrorMessage(error, 'Não foi possível confirmar seu pedido agora. Tente novamente.'))
        if (error instanceof ApiRequestError) setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (paidOrderUuid) {
    return <PixPaymentPanel orderUuid={paidOrderUuid} />
  }

  return (
    <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 2 }}>
          {fulfillmentType === 'pickup' ? 'Retirada e contato' : 'Dados de entrega'}
        </Typography>

        {formError && (
          <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 2.5 }}>
            {formError}
          </Alert>
        )}

        {allowStorePickup && allowDelivery && (
          <RadioGroup
            value={fulfillmentType}
            onChange={(event) => setFulfillmentType(event.target.value as 'delivery' | 'pickup')}
            sx={{ mb: 2.5 }}
          >
            <FormControlLabel
              value="delivery"
              control={<Radio />}
              label={
                <Box>
                  <Typography sx={{ fontSize: 14, fontWeight: 600 }}>Receber em casa</Typography>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Informe o endereço de entrega abaixo.</Typography>
                </Box>
              }
            />
            <FormControlLabel
              value="pickup"
              control={<Radio />}
              label={
                <Box>
                  <Typography sx={{ fontSize: 14, fontWeight: 600 }}>Retirar na loja</Typography>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Sem taxa de entrega — busque o pedido no endereço da loja.</Typography>
                </Box>
              }
            />
          </RadioGroup>
        )}

        <Stack spacing={2} sx={{ mb: 2.5 }}>
          <TextField
            label="Seu nome"
            value={clientName}
            onChange={(event) => setClientName(event.target.value)}
            error={Boolean(fieldErrors.client_name)}
            helperText={fieldErrors.client_name?.[0]}
            required
            fullWidth
          />
          <TextField
            label="Sobrenome"
            value={clientLastName}
            onChange={(event) => setClientLastName(event.target.value)}
            error={Boolean(fieldErrors.client_last_name)}
            helperText={fieldErrors.client_last_name?.[0]}
            required
            fullWidth
          />
          <TextField
            label="Telefone (com DDD)"
            value={clientPhone}
            onChange={(event) => setClientPhone(event.target.value)}
            error={Boolean(fieldErrors.client_phone)}
            helperText={fieldErrors.client_phone?.[0]}
            inputMode="tel"
            required
            fullWidth
          />
        </Stack>

        {fulfillmentType === 'pickup' ? (
          <Alert severity="info" variant="outlined">
            Sem endereço necessário — você vai retirar o pedido diretamente na loja assim que ele estiver pronto.
          </Alert>
        ) : isLoadingEstados ? (
          <Stack sx={{ py: 3, alignItems: 'center' }}>
            <CircularProgress size={24} />
          </Stack>
        ) : (
          <ClientAddressFields
            values={address}
            estados={estados}
            cidades={cidades}
            bairros={bairros}
            isLoadingCidades={isLoadingCidades}
            isLoadingBairros={isLoadingBairros}
            fieldErrors={fieldErrors}
            onEstadoChange={(value) => void handleEstadoChange(value)}
            onCidadeChange={(value) => void handleCidadeChange(value)}
            onBairroChange={handleBairroChange}
            onTextChange={handleAddressTextChange}
            onUseCurrentLocation={handleUseCurrentLocation}
            isLocating={isLocatingGps}
            isCepLoading={isCepLoading}
            locationButtonLabel="Não sei meu CEP? Usar minha localização"
            cepFirst
          />
        )}

        {fulfillmentType === 'delivery' && cepNotFoundMessage && (
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mt: -1.5, mb: 1.5 }}>{cepNotFoundMessage}</Typography>
        )}

        {fulfillmentType === 'delivery' && deliveryFeeStatus === 'loading' && (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mt: 1.5 }}>
            <CircularProgress size={16} />
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Calculando taxa de entrega…</Typography>
          </Stack>
        )}
        {fulfillmentType === 'delivery' && deliveryFeeStatus === 'not-served' && (
          <Alert severity="warning" variant="outlined" sx={{ mt: 1.5 }}>
            Não entregamos nesse bairro no momento.
          </Alert>
        )}
        {fulfillmentType === 'delivery' && deliveryFeeStatus === 'error' && (
          <Alert severity="error" variant="outlined" sx={{ mt: 1.5 }}>
            Não foi possível calcular a taxa de entrega agora. Tente selecionar o bairro novamente.
          </Alert>
        )}

        <TextField
          label="Observações (opcional)"
          value={notes}
          onChange={(event) => setNotes(event.target.value.slice(0, 500))}
          multiline
          minRows={2}
          fullWidth
          sx={{ mt: 2 }}
          helperText={`${notes.length}/500`}
        />
      </Paper>

      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 1.5 }}>Cupom de desconto</Typography>
        {couponStatus === 'applied' ? (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-primary)' }}>{appliedCouponCode}</Typography>
            <Button size="small" onClick={handleRemoveCoupon}>
              Remover
            </Button>
          </Stack>
        ) : (
          <Stack direction="row" spacing={1}>
            <TextField
              placeholder="Código do cupom"
              size="small"
              fullWidth
              value={couponCode}
              onChange={(event) => setCouponCode(event.target.value.toUpperCase())}
            />
            <Button
              variant="outlined"
              onClick={() => void handleApplyCoupon()}
              disabled={couponStatus === 'loading' || !couponCode.trim()}
              sx={{ flexShrink: 0 }}
            >
              {couponStatus === 'loading' ? 'Validando…' : 'Aplicar'}
            </Button>
          </Stack>
        )}
        {couponMessage && (
          <Alert severity={couponStatus === 'invalid' ? 'error' : 'success'} variant="outlined" sx={{ mt: 1.5 }}>
            {couponMessage}
          </Alert>
        )}
      </Paper>

      {acceptedPaymentMethods.length > 0 && (
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
          <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 1 }}>Forma de pagamento</Typography>

          <Box>
            <TextField
              select
              label="Como você pretende pagar"
              value={intendedPaymentMethod}
              onChange={(event) => {
                const value = event.target.value as PaymentMethod | ''
                setIntendedPaymentMethod(value)
                if (value !== 'cash') {
                  setNeedsChange(false)
                  setChangeForAmount('')
                }
              }}
              error={Boolean(fieldErrors.payment_method)}
              helperText={fieldErrors.payment_method?.[0] ?? 'Selecione como você vai pagar.'}
              required
              size="small"
              fullWidth
            >
              {acceptedPaymentMethods.map((method) => (
                <MenuItem key={method} value={method}>
                  {PAYMENT_METHOD_LABELS[method]}
                </MenuItem>
              ))}
            </TextField>

            {intendedPaymentMethod === 'cash' && (
              <Box sx={{ mt: 1.5 }}>
                <FormControlLabel
                  control={<Checkbox checked={needsChange} onChange={(event) => setNeedsChange(event.target.checked)} />}
                  label="Preciso de troco"
                />
                {needsChange && (
                  <TextField
                    label="Troco para quanto?"
                    type="number"
                    value={changeForAmount}
                    onChange={(event) => setChangeForAmount(event.target.value)}
                    size="small"
                    fullWidth
                    slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
                    sx={{ mt: 1 }}
                  />
                )}
              </Box>
            )}
          </Box>
        </Paper>
      )}

      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 1.5 }}>Resumo do pedido</Typography>
        <Stack spacing={1}>
          {items.map((item) => (
            <Box key={item.id}>
              <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
                <Typography sx={{ fontSize: 13.5, wordBreak: 'break-word' }}>
                  {item.quantity} × {item.name}
                </Typography>
                <Typography sx={{ fontSize: 13.5, fontWeight: 600, flexShrink: 0 }}>
                  {formatCurrency(item.unit_price * item.quantity)}
                </Typography>
              </Stack>
              {item.notes && (
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>Obs: {item.notes}</Typography>
              )}
            </Box>
          ))}
        </Stack>
        <Stack spacing={0.75} sx={{ mt: 2, pt: 2, borderTop: '1px solid var(--pt-border)' }}>
          <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Subtotal</Typography>
            <Typography sx={{ fontSize: 13.5 }}>{formatCurrency(totalAmount)}</Typography>
          </Stack>
          {fulfillmentType === 'pickup' ? (
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Taxa de entrega</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-primary)' }}>Retirada — sem taxa</Typography>
            </Stack>
          ) : (
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Taxa de entrega</Typography>
              <Typography sx={{ fontSize: 13.5 }}>
                {deliveryFeeStatus === 'available' && deliveryFee !== null ? formatCurrency(deliveryFee) : '—'}
              </Typography>
            </Stack>
          )}
          {couponStatus === 'applied' && appliedDiscount > 0 && (
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Desconto</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-primary)' }}>-{formatCurrency(appliedDiscount)}</Typography>
            </Stack>
          )}
          <Stack direction="row" sx={{ justifyContent: 'space-between', mt: 0.5 }}>
            <Typography sx={{ fontWeight: 700 }}>Total</Typography>
            <Typography sx={{ fontWeight: 700, fontSize: 18 }}>
              {formatCurrency(
                Math.max(
                  0,
                  totalAmount +
                    (fulfillmentType === 'delivery' && deliveryFeeStatus === 'available' && deliveryFee !== null
                      ? deliveryFee
                      : 0) -
                    (couponStatus === 'applied' ? appliedDiscount : 0),
                ),
              )}
            </Typography>
          </Stack>
        </Stack>
      </Paper>

      <Button
        type="submit"
        variant="contained"
        size="large"
        fullWidth
        disabled={
          isSubmitting ||
          (fulfillmentType === 'delivery' && Boolean(address.bairro_uuid) && deliveryFeeStatus !== 'available')
        }
        sx={{ minHeight: UI_SIZE.controlLarge }}
      >
        {isSubmitting ? 'Confirmando…' : (paymentMethod as string) === 'pix' ? 'Confirmar e gerar Pix' : 'Confirmar pedido'}
      </Button>
    </Box>
  )
}

export function StorefrontCheckoutPage() {
  const { slug } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const { isAuthenticated, isLoading } = usePortalAuth()
  const { items } = useStorefrontCart()

  if (!slug) return null

  if (items.length === 0) {
    return (
      <PageShell slug={slug}>
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 1 }}>Seu carrinho está vazio</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', mb: 2.5 }}>
            Volte ao catálogo e adicione produtos antes de finalizar o pedido.
          </Typography>
          <Button variant="contained" onClick={() => navigate(`/loja/${slug}`)}>
            Ver catálogo
          </Button>
        </Paper>
      </PageShell>
    )
  }

  if (isLoading) {
    return (
      <PageShell slug={slug}>
        <Stack sx={{ alignItems: 'center', py: 6 }}>
          <CircularProgress size={28} />
        </Stack>
      </PageShell>
    )
  }

  return (
    <PageShell slug={slug}>
      {isAuthenticated ? (
        <DetailsAndReviewStep slug={slug} />
      ) : (
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 3, sm: 4 } }}>
          <OtpIdentifyForm />
        </Paper>
      )}
    </PageShell>
  )
}
