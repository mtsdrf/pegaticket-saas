import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import ContentCopyIcon from '@mui/icons-material/ContentCopy'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  FormControlLabel,
  InputAdornment,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { TurnstileWidget } from '../../components/security/TurnstileWidget'
import { Logo } from '../../components/ui/Logo'
import { useCartAbandonmentTelemetry } from '../../hooks/useCartAbandonmentTelemetry'
import { formatCountdown } from '../../hooks/useCountdown'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { getSaleTracking } from '../../services/saleTrackingService'
import * as storefrontSalePaymentService from '../../services/storefrontSalePaymentService'
import * as storefrontCheckoutService from '../../services/storefrontCheckoutService'
import * as storefrontHoldService from '../../services/storefrontHoldService'
import * as storefrontService from '../../services/storefrontService'
import { getStorefrontTracking } from '../../utils/marketingTracking'
import { sendFunnelEvent } from '../../utils/funnelTracking'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import type { SalePayment, SalePaymentCheckoutConfig, SalePaymentInstallmentOption } from '../../types/sale'
import { formatCpfCnpj, isValidCpfCnpj } from '../../utils/cpfCnpj'
import {
  BELOW_MINIMUM_ORDER_CODE,
  COUPON_USAGE_LIMIT_REACHED_CODE,
  INVALID_COUPON_CODE,
  type StorefrontCartItem,
  type StorefrontCheckoutPayload,
  type StorefrontCreateHoldPayload,
  type StorefrontInventoryHold,
} from '../../types/storefront'
import { formatCurrency } from '../../utils/format'
type CouponStatus = 'idle' | 'loading' | 'applied' | 'invalid'
type PaymentFlowMethod = 'pix' | 'credit_card' | 'debit_card'

type PaymentFlowPayer = {
  name: string
  email: string
  phone: string
  taxId: string
}

type PaymentFlowState = {
  saleUuid: string
  method: PaymentFlowMethod
  amount: string
  payer: PaymentFlowPayer
  slug: string
  eventSlug: string | null
  sessionId: string
}

type PagSeguroEncryptResult = {
  encryptedCard?: string
  hasErrors: boolean
  errors?: Array<{ message?: string }>
}

type PagSeguroSetUpPayload = {
  session: string
  env: 'SANDBOX' | 'PROD'
}

type PagSeguroAuthenticate3DSPayload = {
  customer: {
    name: string
    email: string
    phones: Array<{
      country: string
      area: string
      number: string
      type: 'MOBILE' | 'HOME' | 'WORK'
    }>
    taxId?: string
  }
  paymentMethod: {
    type: 'DEBIT_CARD'
    installments: number
    card: {
      number: string
      expMonth: string
      expYear: string
      holder: {
        name: string
      }
    }
  }
  amount: {
    value: number
    currency: 'BRL'
  }
  billingAddress: {
    line1: string
    line2?: string
    city: string
    regionCode: string
    country: 'BRA'
    postalCode: string
  }
  dataOnly: false
}

type PagSeguroAuthenticate3DSResult = {
  status?: string
  id?: string
}

type PagSeguroGlobal = {
  setUp: (payload: PagSeguroSetUpPayload) => void
  encryptCard: (payload: {
    publicKey: string
    holder: string
    number: string
    expMonth: string
    expYear: string
    securityCode: string
  }) => PagSeguroEncryptResult
  authenticate3DS: (payload: PagSeguroAuthenticate3DSPayload) => Promise<PagSeguroAuthenticate3DSResult>
}

declare global {
  interface Window {
    PagSeguro?: PagSeguroGlobal
  }
}

const HOLD_RENEW_INTERVAL_MS = 60_000
/** Avisos visuais de expiração da reserva (spec: aviso a 5min e a 1min). */
const HOLD_WARNING_SECONDS = 5 * 60
const HOLD_CRITICAL_SECONDS = 60

/**
 * O backend nunca devolve um `code` específico para hold inválido no submit
 * do checkout (sempre `HTTP_ERROR` genérico, ver `bootstrap/app.php` —
 * `abort(422, __('messages.inventory_hold.*'))` cai no handler de
 * HttpException, que não tem código próprio). A mensagem já vem traduzida do
 * backend; detectamos pelo texto pra saber quando vale a pena limpar o hold
 * indireto e oferecer nova reserva em vez de erro genérico de topo de tela.
 */
function isHoldInvalidMessage(message: string): boolean {
  const normalized = message.toLowerCase()
  return (
    normalized.includes('reserva temporária não está mais ativa') ||
    normalized.includes('não correspondem mais à reserva temporária')
  )
}

function buildHoldSignature(eventSlug: string | null, sessionToken: string, items: StorefrontCreateHoldPayload['items']): string {
  return JSON.stringify({ eventSlug, sessionToken, items })
}

function normalizeDigits(value: string): string {
  return value.replace(/\D+/g, '')
}

function formatBrazilPhone(value: string): string {
  const digits = normalizeDigits(value).slice(0, 11)

  if (digits.length <= 2) return digits
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

function isValidCheckoutEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function isValidBrazilPhone(value: string): boolean {
  const digits = normalizeDigits(value)
  return digits.length === 10 || digits.length === 11
}

type CardBrand = 'amex' | 'diners' | 'discover' | 'elo' | 'hipercard' | 'jcb' | 'mastercard' | 'unionpay' | 'visa' | 'unknown'

type CardBrandRule = {
  brand: CardBrand
  label: string
  pattern: RegExp
  lengths: number[]
  cvvLengths: number[]
  format: number[]
}

const CARD_BRAND_RULES: CardBrandRule[] = [
  {
    brand: 'amex',
    label: 'Amex',
    pattern: /^3[47]/,
    lengths: [15],
    cvvLengths: [4],
    format: [4, 6, 5],
  },
  {
    brand: 'diners',
    label: 'Diners',
    pattern: /^(36|38|39|30[0-5])/,
    lengths: [14],
    cvvLengths: [3],
    format: [4, 6, 4],
  },
  {
    brand: 'discover',
    label: 'Discover',
    pattern: /^(6011|65|64[4-9])/,
    lengths: [16, 19],
    cvvLengths: [3],
    format: [4, 4, 4, 4, 3],
  },
  {
    brand: 'elo',
    label: 'Elo',
    pattern:
      /^(4011(78|79)|431274|438935|451416|457393|457631|457632|504175|627780|636297|636368|65003[1-3]|65003[5-9]|65004\d|65005[0-1]|65040[5-9]|6504[3-9]\d|6505\d{2}|65070\d|65071\d|65072[0-7]|6509\d{2}|65165[2-9]|6516[6-7]\d|6550\d{2})/,
    lengths: [16],
    cvvLengths: [3],
    format: [4, 4, 4, 4],
  },
  {
    brand: 'hipercard',
    label: 'Hipercard',
    pattern: /^(606282|3841)/,
    lengths: [13, 16, 19],
    cvvLengths: [3],
    format: [4, 4, 4, 4, 3],
  },
  {
    brand: 'jcb',
    label: 'JCB',
    pattern: /^35(2[89]|[3-8]\d)/,
    lengths: [16, 19],
    cvvLengths: [3],
    format: [4, 4, 4, 4, 3],
  },
  {
    brand: 'mastercard',
    label: 'Mastercard',
    pattern: /^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/,
    lengths: [16],
    cvvLengths: [3],
    format: [4, 4, 4, 4],
  },
  {
    brand: 'unionpay',
    label: 'UnionPay',
    pattern: /^62/,
    lengths: [16, 17, 18, 19],
    cvvLengths: [3],
    format: [4, 4, 4, 4, 3],
  },
  {
    brand: 'visa',
    label: 'Visa',
    pattern: /^4/,
    lengths: [13, 16, 19],
    cvvLengths: [3],
    format: [4, 4, 4, 4, 3],
  },
]

const DEFAULT_CARD_FORMAT = [4, 4, 4, 4, 3]
const DEFAULT_CARD_LENGTHS = [16]
const DEFAULT_CARD_CVV_LENGTHS = [3]
const CARD_BRAND_IMAGE_PATHS: Partial<Record<CardBrand, string>> = {
  amex: '/card-brands/amex.svg',
  diners: '/card-brands/diners.svg',
  discover: '/card-brands/discover.svg',
  elo: '/card-brands/elo.svg',
  hipercard: '/card-brands/hipercard.svg',
  jcb: '/card-brands/jcb.svg',
  mastercard: '/card-brands/mastercard.svg',
  unionpay: '/card-brands/unionpay.svg',
  visa: '/card-brands/visa.svg',
}

function detectCardBrand(value: string): CardBrandRule | null {
  const digits = normalizeDigits(value)
  return CARD_BRAND_RULES.find((rule) => rule.pattern.test(digits)) ?? null
}

function formatCardNumber(value: string): string {
  const digits = normalizeDigits(value)
  const brand = detectCardBrand(digits)
  const format = brand?.format ?? DEFAULT_CARD_FORMAT
  const maxLength = Math.max(...(brand?.lengths ?? DEFAULT_CARD_LENGTHS))
  const limited = digits.slice(0, maxLength)
  const groups: string[] = []
  let cursor = 0

  for (const groupSize of format) {
    if (cursor >= limited.length) break
    groups.push(limited.slice(cursor, cursor + groupSize))
    cursor += groupSize
  }

  if (cursor < limited.length) {
    groups.push(limited.slice(cursor))
  }

  return groups.join(' ').trim()
}

function isValidCardNumberLuhn(value: string): boolean {
  const digits = normalizeDigits(value)
  let sum = 0
  let shouldDouble = false

  for (let index = digits.length - 1; index >= 0; index -= 1) {
    let digit = Number(digits[index])
    if (shouldDouble) {
      digit *= 2
      if (digit > 9) digit -= 9
    }
    sum += digit
    shouldDouble = !shouldDouble
  }

  return digits.length > 0 && sum % 10 === 0
}

function formatCardPreviewNumber(value: string): string {
  const masked = `${normalizeDigits(value)}${'•'.repeat(16)}`.slice(0, 16)
  return masked.replace(/(.{4})/g, '$1 ').trim()
}

function normalizeCardExpYear(value: string): string {
  const digits = normalizeDigits(value)

  if (digits.length >= 4) {
    return digits.slice(0, 4)
  }

  if (digits.length === 2) {
    return `20${digits}`
  }

  return digits
}

function normalizeCardExpYearInput(value: string): string {
  const digits = normalizeDigits(value)

  if (digits.length >= 4) {
    return digits.slice(-2)
  }

  return digits.slice(0, 2)
}

/**
 * O PagBank cobra valor uniforme em todas as parcelas (juro já diluído
 * igualmente). Pra deixar claro pro comprador que a 1ª parcela equivale ao
 * valor à vista, recalculamos só para exibição: 1ª parcela = valor base ÷ N
 * (sem juros), e o juro total do plano é redistribuído entre as demais
 * (N-1) parcelas. A soma continua batendo com `option.total_amount`
 * (o que de fato é cobrado no cartão) — não muda a cobrança, só o texto.
 */
function computeInstallmentBreakdown(option: SalePaymentInstallmentOption): {
  firstInstallmentCents: number
  remainingInstallmentCents: number
  remainingCount: number
} {
  const baseAmountCents = option.total_amount - option.buyer_interest_total
  const firstInstallmentCents = Math.round(baseAmountCents / option.installments)
  const remainingCount = option.installments - 1
  const remainingInstallmentCents =
    remainingCount > 0 ? Math.round((option.total_amount - firstInstallmentCents) / remainingCount) : 0

  return { firstInstallmentCents, remainingInstallmentCents, remainingCount }
}

function formatInstallmentOptionLabel(option: SalePaymentInstallmentOption): string {
  const installmentValue = formatCurrency(option.installment_value / 100)
  const totalAmount = formatCurrency(option.total_amount / 100)

  if (option.interest_free || option.buyer_interest_total <= 0) {
    return `${option.installments}x de ${installmentValue} sem juros`
  }

  const { firstInstallmentCents, remainingInstallmentCents, remainingCount } = computeInstallmentBreakdown(option)

  if (remainingCount <= 0) {
    return `${option.installments}x de ${installmentValue} | total ${totalAmount}`
  }

  return `${option.installments}x — 1ª de ${formatCurrency(firstInstallmentCents / 100)} sem juros + ${remainingCount}x de ${formatCurrency(remainingInstallmentCents / 100)} com juros | total ${totalAmount}`
}

function formatPayButtonLabel(option: SalePaymentInstallmentOption | undefined): string {
  if (!option) return 'Pagar com cartão'

  const installmentValue = formatCurrency(option.installment_value / 100)

  if (option.installments <= 1) {
    return `Pagar ${installmentValue} no cartão`
  }

  if (option.interest_free || option.buyer_interest_total <= 0) {
    return `Pagar ${option.installments}x de ${installmentValue} sem juros`
  }

  const { firstInstallmentCents, remainingInstallmentCents, remainingCount } = computeInstallmentBreakdown(option)

  if (remainingCount <= 0) {
    return `Pagar ${formatCurrency(firstInstallmentCents / 100)} no cartão`
  }

  return `Pagar 1ª de ${formatCurrency(firstInstallmentCents / 100)} + ${remainingCount}x de ${formatCurrency(remainingInstallmentCents / 100)}`
}

function getCreditCardFieldErrors({
  payerTaxId,
  holderName,
  holderTaxId,
  cardNumber,
  expMonth,
  expYear,
  securityCode,
}: {
  payerTaxId: string
  holderName: string
  holderTaxId: string
  cardNumber: string
  expMonth: string
  expYear: string
  securityCode: string
}): Record<string, string> {
  const errors: Record<string, string> = {}
  const cardDigits = normalizeDigits(cardNumber)
  const expMonthDigits = normalizeDigits(expMonth)
  const expYearDigits = normalizeDigits(expYear)
  const cvvDigits = normalizeDigits(securityCode)
  const brand = detectCardBrand(cardDigits)
  const expectedLengths = brand?.lengths ?? DEFAULT_CARD_LENGTHS
  const expectedCvvLengths = brand?.cvvLengths ?? DEFAULT_CARD_CVV_LENGTHS

  if (!payerTaxId.trim()) {
    errors.payerTaxId = 'Informe o CPF/CNPJ do pagador.'
  } else if (!isValidCpfCnpj(payerTaxId)) {
    errors.payerTaxId = 'Informe um CPF/CNPJ válido.'
  }

  if (holderName.trim().length < 3) {
    errors.holderName = 'Informe o nome completo do titular.'
  }

  if (!holderTaxId.trim()) {
    errors.holderTaxId = 'Informe o CPF/CNPJ do titular.'
  } else if (!isValidCpfCnpj(holderTaxId)) {
    errors.holderTaxId = 'Informe um CPF/CNPJ válido.'
  }

  if (!cardDigits) {
    errors.cardNumber = 'Informe o número do cartão.'
  } else if (!expectedLengths.includes(cardDigits.length)) {
    errors.cardNumber = 'O número do cartão está incompleto.'
  } else if (!isValidCardNumberLuhn(cardDigits)) {
    errors.cardNumber = 'O número do cartão parece inválido.'
  }

  if (expMonthDigits.length !== 2) {
    errors.expMonth = 'Informe o mês com 2 dígitos.'
  } else {
    const month = Number(expMonthDigits)
    if (month < 1 || month > 12) {
      errors.expMonth = 'Informe um mês válido.'
    }
  }

  if (expYearDigits.length !== 2) {
    errors.expYear = 'Informe o ano com 2 dígitos.'
  }

  if (!errors.expMonth && !errors.expYear) {
    const month = Number(expMonthDigits)
    const year = Number(expYearDigits)
    const now = new Date()
    const currentYear = now.getFullYear() % 100
    const currentMonth = now.getMonth() + 1

    if (year < currentYear || (year === currentYear && month < currentMonth)) {
      errors.expYear = 'O cartão está vencido.'
    }
  }

  if (!cvvDigits) {
    errors.securityCode = 'Informe o código de segurança.'
  } else if (!expectedCvvLengths.includes(cvvDigits.length)) {
    const expected = expectedCvvLengths.join(' ou ')
    errors.securityCode = `Informe um CVV com ${expected} dígitos.`
  }

  return errors
}

function getHoldSecondsLeft(hold: StorefrontInventoryHold | null): number {
  if (!hold?.expires_at) {
    return Math.max(0, hold?.remaining_seconds ?? 0)
  }

  const expiresAt = Date.parse(hold.expires_at)
  if (Number.isNaN(expiresAt)) {
    return Math.max(0, hold.remaining_seconds)
  }

  return Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000))
}

let pagSeguroSdkPromise: Promise<void> | null = null

function loadPagSeguroSdk(scriptUrl: string): Promise<void> {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('window_unavailable'))
  }

  if (window.PagSeguro) {
    return Promise.resolve()
  }

  if (pagSeguroSdkPromise) {
    return pagSeguroSdkPromise
  }

  pagSeguroSdkPromise = new Promise<void>((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>('script[data-pagseguro-sdk="true"]')

    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', () => reject(new Error('sdk_load_failed')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = scriptUrl
    script.async = true
    script.dataset.pagseguroSdk = 'true'
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('sdk_load_failed'))
    document.body.appendChild(script)
  }).catch((error) => {
    pagSeguroSdkPromise = null
    throw error
  })

  return pagSeguroSdkPromise
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
        <Button startIcon={<ArrowBackIcon />} color="inherit" onClick={() => navigate(`/eventos/${slug}/carrinho`)} sx={{ ml: -1, mb: 1 }}>
          Voltar ao carrinho
        </Button>
        <Stack spacing={0.5} sx={{ alignItems: 'center', mb: 3 }}>
          <Logo variant="mark" size={36} />
        </Stack>
        {children}
        <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 4 }}>
          <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)', textAlign: 'center' }}>
            Checkout via PegaTicket — do acesso a experiencia, tudo em movimento.
          </Typography>
        </Stack>
      </Box>
    </Box>
  )
}

/**
 * Passo 3 (só quando o cliente escolhe "Pix agora"): a venda já foi criada
 * com sucesso — daqui em diante nenhuma falha pode deixar a tela quebrada,
 * a venda existe de qualquer forma. Gera a cobrança ao montar; erro na
 * geração mostra retry + saída para o rastreio (pagar na entrega continua
 * possível). Sem polling automático (não solicitado) — verificação é manual via
 * "Já paguei, verificar", que só relê `GET /rastreio/{uuid}` (público,
 * já reflete `is_paid` assim que o webhook do PSP confirmar).
 */
function PixPaymentPanel({
  saleUuid,
  slug,
  eventSlug,
  sessionId,
}: {
  saleUuid: string
  slug: string
  eventSlug: string | null
  sessionId: string
}) {
  const navigate = useNavigate()
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [payment, setPayment] = useState<SalePayment | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [isCheckingPayment, setIsCheckingPayment] = useState(false)
  const [checkMessage, setCheckMessage] = useState<string | null>(null)

  const loadCharge = useCallback(() => {
    setStatus('loading')
    setErrorMessage(null)
    setCheckMessage(null)
    storefrontSalePaymentService
      .createSalePixCharge(saleUuid)
      .then((result) => {
        setPayment(result)
        setStatus('ready')
      })
      .catch((error: unknown) => {
        setStatus('error')
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível gerar a cobrança Pix agora.'))
      })
  }, [saleUuid])

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
      const tracking = await getSaleTracking(saleUuid)
      if (tracking.is_paid) {
        if (eventSlug) sendFunnelEvent(slug, eventSlug, sessionId, 'payment_confirmed')
        navigate(`/compra/${saleUuid}`)
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
        Sua compra já foi confirmada. Escaneie o QR Code ou copie o código Pix para concluir o pagamento.
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
          <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
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
          <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
            Ver status da minha compra
          </Button>
        </Stack>
      )}
    </Paper>
  )
}

function CreditCardPaymentPanel({
  saleUuid,
  amount,
  payer,
  slug,
  eventSlug,
  sessionId,
}: {
  saleUuid: string
  amount: string
  payer: PaymentFlowPayer
  slug: string
  eventSlug: string | null
  sessionId: string
}) {
  const navigate = useNavigate()
  const [config, setConfig] = useState<SalePaymentCheckoutConfig | null>(null)
  const [isLoadingConfig, setIsLoadingConfig] = useState(true)
  const [configError, setConfigError] = useState<string | null>(null)
  const [sdkReady, setSdkReady] = useState(false)

  const [payerTaxId, setPayerTaxId] = useState(payer.taxId)
  const [holderName, setHolderName] = useState(payer.name)
  const [holderTaxId, setHolderTaxId] = useState(payer.taxId)
  const [cardNumber, setCardNumber] = useState('')
  const [expMonth, setExpMonth] = useState('')
  const [expYear, setExpYear] = useState('')
  const [securityCode, setSecurityCode] = useState('')
  const [installments, setInstallments] = useState('1')
  const [installmentOptions, setInstallmentOptions] = useState<SalePaymentInstallmentOption[]>([])
  const [isLoadingInstallmentOptions, setIsLoadingInstallmentOptions] = useState(false)
  const [installmentOptionsError, setInstallmentOptionsError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isCardBackVisible, setIsCardBackVisible] = useState(false)
  const cardBrand = useMemo(() => detectCardBrand(cardNumber), [cardNumber])
  const cardBrandImageSrc = cardBrand ? CARD_BRAND_IMAGE_PATHS[cardBrand.brand] : undefined
  const cardBin = useMemo(() => normalizeDigits(cardNumber).slice(0, 6), [cardNumber])
  const fallbackInstallmentOptions = useMemo<SalePaymentInstallmentOption[]>(() => {
    const totalAmountCents = Math.round(Number(amount || '0') * 100)

    return [{
      installments: 1,
      installment_value: totalAmountCents,
      interest_free: true,
      total_amount: totalAmountCents,
      currency: 'BRL',
      buyer_interest_total: 0,
      buyer_interest_installments: 0,
    }]
  }, [amount])
  const availableInstallmentOptions = installmentOptions.length > 0 ? installmentOptions : fallbackInstallmentOptions
  const selectedInstallmentOption = useMemo(
    () => availableInstallmentOptions.find((option) => String(option.installments) === installments) ?? availableInstallmentOptions[0],
    [availableInstallmentOptions, installments],
  )

  useEffect(() => {
    setIsLoadingConfig(true)
    setConfigError(null)

    storefrontSalePaymentService
      .getSalePaymentCheckoutConfig(saleUuid)
      .then(async (result) => {
        setConfig(result)

        if (!result.available || !result.public_key || !result.sdk_script_url) {
          throw new Error('checkout_unavailable')
        }

        await loadPagSeguroSdk(result.sdk_script_url)
        setSdkReady(true)
      })
      .catch((error: unknown) => {
        setConfigError(getApiErrorMessage(error, 'Não foi possível preparar o pagamento com cartão agora.'))
      })
      .finally(() => setIsLoadingConfig(false))
  }, [saleUuid])

  useEffect(() => {
    if (!sdkReady || !config?.available) return

    if (cardBin.length < 6) {
      setInstallmentOptions([])
      setInstallmentOptionsError(null)
      setInstallments('1')
      return
    }

    let cancelled = false
    setIsLoadingInstallmentOptions(true)
    setInstallmentOptionsError(null)

    storefrontSalePaymentService
      .getSalePaymentInstallmentOptions(saleUuid, cardBin, Number(amount || '0'))
      .then((result) => {
        if (cancelled) return
        const options = result.options.length > 0 ? result.options : fallbackInstallmentOptions
        setInstallmentOptions(options)
        setInstallments((current) =>
          options.some((option) => String(option.installments) === current) ? current : String(options[0]?.installments ?? 1),
        )
      })
      .catch((error) => {
        if (cancelled) return
        setInstallmentOptions([])
        setInstallmentOptionsError(getApiErrorMessage(error, 'Não foi possível consultar as condições de parcelamento agora.'))
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoadingInstallmentOptions(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [amount, cardBin, config?.available, fallbackInstallmentOptions, saleUuid, sdkReady])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    const errors = getCreditCardFieldErrors({
      payerTaxId,
      holderName,
      holderTaxId,
      cardNumber,
      expMonth,
      expYear,
      securityCode,
    })

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }

    setFieldErrors({})

    if (!config?.public_key || !window.PagSeguro) {
      setFormError('O checkout do cartão ainda não está pronto. Atualize a página e tente novamente.')
      return
    }

    setIsSubmitting(true)

    try {
      const encrypted = window.PagSeguro.encryptCard({
        publicKey: config.public_key,
        holder: holderName.trim(),
        number: cardNumber.replace(/\D+/g, ''),
        expMonth: expMonth.replace(/\D+/g, ''),
        expYear: normalizeCardExpYear(expYear),
        securityCode: securityCode.replace(/\D+/g, ''),
      })

      if (encrypted.hasErrors || !encrypted.encryptedCard) {
        const firstError = encrypted.errors?.[0]?.message?.trim()
        throw new Error(firstError || 'Não foi possível criptografar os dados do cartão.')
      }

      await storefrontSalePaymentService.createSalePaymentCharge(saleUuid, {
        method: 'credit_card',
        payer_tax_id: payerTaxId.replace(/\D+/g, ''),
        payer_name: payer.name,
        payer_email: payer.email,
        payer_phone: payer.phone,
        card: {
          encrypted: encrypted.encryptedCard,
          holder_name: holderName.trim(),
          holder_tax_id: holderTaxId.replace(/\D+/g, ''),
          installments: Number(installments),
          buyer_interest_total: selectedInstallmentOption?.buyer_interest_total ?? 0,
          buyer_interest_installments: selectedInstallmentOption?.buyer_interest_installments ?? 0,
        },
      })

      if (eventSlug) sendFunnelEvent(slug, eventSlug, sessionId, 'payment_confirmed')
      navigate(`/compra/${saleUuid}`)
    } catch (error) {
      setFormError(getApiErrorMessage(error, error instanceof Error ? error.message : 'Não foi possível processar o cartão agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 } }}>
      <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 0.5 }}>Pagar com cartão de crédito</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2.5 }}>
        Seus dados são criptografados no navegador antes do envio ao PagBank.
      </Typography>

      {isLoadingConfig && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1.5}>
          <CircularProgress size={28} />
          <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Preparando ambiente seguro do cartão…</Typography>
        </Stack>
      )}

      {!isLoadingConfig && configError && (
        <Stack spacing={2}>
          <Alert severity="error" variant="outlined">
            {configError}
          </Alert>
          <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
            Ver status da compra
          </Button>
        </Stack>
      )}

      {!isLoadingConfig && !configError && (
        <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Stack spacing={2}>
            {!sdkReady && (
              <Alert severity="warning" variant="outlined">
                O SDK de pagamento ainda está carregando. Aguarde alguns instantes.
              </Alert>
            )}

            {formError && (
              <Alert severity="error" variant="outlined">
                {formError}
              </Alert>
            )}

            <Box sx={{ perspective: '1400px', width: '100%', maxWidth: 560, mx: 'auto' }}>
              <Box
                sx={{
                  position: 'relative',
                  width: '100%',
                  aspectRatio: '85.6 / 53.98',
                  transformStyle: 'preserve-3d',
                  isolation: 'isolate',
                }}
              >
                <Box
                  sx={{
                    position: 'absolute',
                    inset: 0,
                    overflow: 'hidden',
                    backfaceVisibility: 'hidden',
                    WebkitBackfaceVisibility: 'hidden',
                    transform: isCardBackVisible ? 'rotateY(-180deg)' : 'rotateY(0deg)',
                    transition: 'transform 520ms cubic-bezier(0.22, 1, 0.36, 1)',
                    borderRadius: '3.7%',
                    border: '1px solid rgba(211, 241, 232, 0.12)',
                    background:
                      'radial-gradient(circle at top right, rgba(140, 255, 224, 0.28), transparent 28%), linear-gradient(135deg, #115441 0%, #0f3d31 38%, #0b2620 100%)',
                    color: '#f3fbf7',
                    padding: '5.8%',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'flex-start',
                    gap: 0.35,
                    boxShadow: '0 24px 60px rgba(6, 28, 23, 0.34)',
                    zIndex: isCardBackVisible ? 1 : 2,
                    containerType: 'inline-size',
                  }}
                >
                  <Box
                    sx={{
                      position: 'absolute',
                      inset: 0,
                      background:
                        'linear-gradient(120deg, transparent 0%, rgba(255,255,255,0.08) 36%, transparent 54%)',
                      pointerEvents: 'none',
                      zIndex: 0,
                    }}
                  />
                  <Stack
                    direction="row"
                    sx={{ position: 'relative', zIndex: 1, justifyContent: 'space-between', alignItems: 'flex-start' }}
                  >
                    <Stack spacing={1.6}>
                      <Typography sx={{ fontSize: 'clamp(11px, 2.9cqw, 12px)', letterSpacing: '0.18em', textTransform: 'uppercase', opacity: 0.78 }}>
                        PegaTicket
                      </Typography>
                      <Box
                        sx={{
                          width: '14.4cqw',
                          aspectRatio: '12.3 / 8',
                          borderRadius: '18%',
                          background:
                            'linear-gradient(135deg, rgba(255, 219, 128, 0.92) 0%, rgba(194, 148, 58, 0.92) 100%)',
                          boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.35)',
                          position: 'relative',
                        }}
                      >
                        <Box
                          sx={{
                            position: 'absolute',
                            inset: '15% 30%',
                            borderTop: '1px solid rgba(88, 53, 8, 0.35)',
                            borderBottom: '1px solid rgba(88, 53, 8, 0.35)',
                          }}
                        />
                      </Box>
                    </Stack>
                    <Box sx={{ width: '14.4cqw', aspectRatio: '12.3 / 8' }} />
                  </Stack>

                  <Typography
                    sx={{
                      position: 'relative',
                      zIndex: 1,
                      fontSize: 'clamp(22px, 7.3cqw, 28px)',
                      letterSpacing: '0.14em',
                      fontWeight: 600,
                      mt: '8.2cqw',
                      mb: '-12px',
                      lineHeight: 1.05,
                    }}
                  >
                    {formatCardPreviewNumber(cardNumber)}
                  </Typography>

                  <Stack
                    direction="row"
                    spacing={3.25}
                    sx={{ position: 'relative', zIndex: 1, mt: 'auto', pr: '22%', alignItems: 'flex-end' }}
                  >
                    <Box sx={{ minWidth: 0, flex: '1 1 auto', maxWidth: 'calc(100% - 14cqw)' }}>
                      <Typography sx={{ fontSize: 'clamp(10px, 2.55cqw, 11px)', letterSpacing: '0.12em', textTransform: 'uppercase', opacity: 0.68 }}>
                        Titular
                      </Typography>
                      <Typography
                        noWrap
                        sx={{
                          fontSize: 'clamp(12px, 3.35cqw, 14px)',
                          fontWeight: 600,
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {holderName.trim() || 'Nome do titular'}
                      </Typography>
                    </Box>
                    <Box sx={{ flexShrink: 0 }}>
                      <Typography sx={{ fontSize: 'clamp(10px, 2.55cqw, 11px)', letterSpacing: '0.12em', textTransform: 'uppercase', opacity: 0.68 }}>
                        Validade
                      </Typography>
                      <Typography sx={{ fontSize: 'clamp(12px, 3.35cqw, 14px)', fontWeight: 600 }}>
                        {(normalizeDigits(expMonth).slice(0, 2) || 'MM')}/{(normalizeDigits(expYear).slice(0, 2) || 'AA')}
                      </Typography>
                    </Box>
                  </Stack>

                  <Box
                    sx={{
                      position: 'absolute',
                      right: '5.8%',
                      bottom: '5.8%',
                      width: '15%',
                      display: 'flex',
                      alignItems: 'flex-end',
                      justifyContent: 'flex-end',
                      zIndex: 1,
                    }}
                  >
                    {cardBrandImageSrc ? (
                      <Box
                        component="img"
                        src={cardBrandImageSrc}
                        alt={cardBrand?.label ?? 'Bandeira do cartão'}
                        sx={{
                          width: '100%',
                          height: 'auto',
                          objectFit: 'contain',
                          objectPosition: 'right bottom',
                          filter: 'drop-shadow(0 8px 18px rgba(3, 14, 11, 0.26))',
                          flexShrink: 0,
                        }}
                      />
                    ) : (
	                        <Box
	                          sx={{
	                            width: '100%',
	                            aspectRatio: '1.4 / 1',
	                            borderRadius: '10%',
	                            display: { xs: 'none', sm: 'inline-flex' },
	                            alignItems: 'center',
	                            justifyContent: 'center',
	                            border: '1px solid rgba(243, 251, 247, 0.18)',
	                            background: 'rgba(255, 255, 255, 0.06)',
	                            color: 'rgba(243, 251, 247, 0.84)',
	                            fontSize: 11,
	                            letterSpacing: '0.08em',
	                            textTransform: 'uppercase',
	                            flexShrink: 0,
	                          }}
	                        >
                          Bandeira
                        </Box>
                      )}
                    </Box>
                </Box>

                <Box
                  sx={{
                    position: 'absolute',
                    inset: 0,
                    overflow: 'hidden',
                    backfaceVisibility: 'hidden',
                    WebkitBackfaceVisibility: 'hidden',
                    transform: isCardBackVisible ? 'rotateY(0deg)' : 'rotateY(180deg)',
                    transition: 'transform 520ms cubic-bezier(0.22, 1, 0.36, 1)',
                    borderRadius: '3.7%',
                    border: '1px solid rgba(211, 241, 232, 0.12)',
                    background:
                      'radial-gradient(circle at bottom left, rgba(140, 255, 224, 0.16), transparent 32%), linear-gradient(140deg, #0d2822 0%, #112c27 58%, #0f1d1a 100%)',
                    color: '#f3fbf7',
                    boxShadow: '0 24px 60px rgba(6, 28, 23, 0.34)',
                    zIndex: isCardBackVisible ? 2 : 1,
                  }}
                >
                  <Box
                    sx={{
                      position: 'absolute',
                      inset: 0,
                      background:
                        'linear-gradient(140deg, #0d2822 0%, #112c27 58%, #0f1d1a 100%)',
                      zIndex: 0,
                    }}
                  />
                  <Box sx={{ height: '26%', background: '#09110f', mt: '2%', position: 'relative', zIndex: 1 }} />
                  <Box sx={{ px: '5.8%', pt: '6%', position: 'relative', zIndex: 1 }}>
                    <Typography sx={{ mb: 1, fontSize: 'clamp(10px, 2.55cqw, 11px)', letterSpacing: '0.14em', textTransform: 'uppercase', opacity: 0.64 }}>
                      Código de segurança
                    </Typography>
                    <Box
                      sx={{
                        borderRadius: '10px',
                        background: 'linear-gradient(180deg, #f7f4ed 0%, #ece8df 100%)',
                        color: '#1f2f29',
                        px: 1.5,
                        py: 1.15,
                        display: 'flex',
                        justifyContent: 'flex-end',
                        alignItems: 'center',
                        fontSize: 'clamp(16px, 4.2cqw, 18px)',
                        fontWeight: 700,
                        letterSpacing: '0.22em',
                        minHeight: 48,
                      }}
                    >
                      {securityCode || '•••'}
                    </Box>
                  </Box>
                  <Typography sx={{ px: '5.8%', pt: '3%', position: 'relative', zIndex: 1, fontSize: 'clamp(11px, 2.85cqw, 12px)', lineHeight: 1.5, color: 'rgba(243, 251, 247, 0.72)' }}>
                    Confira o número, o nome do titular e o CVV exatamente como aparecem no cartão antes de concluir o pagamento.
                  </Typography>
                </Box>
              </Box>
            </Box>

            <TextField
              label="CPF/CNPJ do pagador"
              value={payerTaxId}
              onChange={(event) => {
                setPayerTaxId(formatCpfCnpj(event.target.value))
                setFieldErrors((current) => ({ ...current, payerTaxId: '' }))
              }}
              size="small"
              fullWidth
              required
              error={Boolean(fieldErrors.payerTaxId)}
              helperText={fieldErrors.payerTaxId}
            />
            <TextField
              label="Nome do titular do cartão"
              value={holderName}
              onChange={(event) => {
                setHolderName(event.target.value)
                setFieldErrors((current) => ({ ...current, holderName: '' }))
              }}
              size="small"
              fullWidth
              required
              autoComplete="cc-name"
              error={Boolean(fieldErrors.holderName)}
              helperText={fieldErrors.holderName}
            />
            <TextField
              label="CPF/CNPJ do titular"
              value={holderTaxId}
              onChange={(event) => {
                setHolderTaxId(formatCpfCnpj(event.target.value))
                setFieldErrors((current) => ({ ...current, holderTaxId: '' }))
              }}
              size="small"
              fullWidth
              required
              error={Boolean(fieldErrors.holderTaxId)}
              helperText={fieldErrors.holderTaxId}
            />
            <TextField
              label="Número do cartão"
              value={cardNumber}
              onChange={(event) => {
                setCardNumber(formatCardNumber(event.target.value))
                setFieldErrors((current) => ({ ...current, cardNumber: '' }))
              }}
              size="small"
              fullWidth
              required
              autoComplete="cc-number"
              inputMode="numeric"
              error={Boolean(fieldErrors.cardNumber)}
              helperText={fieldErrors.cardNumber ?? 'Aceita colar o número com espaços ou traços.'}
              slotProps={{
                input: {
                  endAdornment: cardBrand ? <InputAdornment position="end">{cardBrand.label}</InputAdornment> : undefined,
                },
              }}
            />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <TextField
                label="Mês"
                value={expMonth}
                onChange={(event) => {
                  setExpMonth(normalizeDigits(event.target.value).slice(0, 2))
                  setFieldErrors((current) => ({ ...current, expMonth: '' }))
                }}
                size="small"
                fullWidth
                required
                autoComplete="cc-exp-month"
                inputMode="numeric"
                error={Boolean(fieldErrors.expMonth)}
                helperText={fieldErrors.expMonth}
              />
              <TextField
                label="Ano"
                value={expYear}
                onChange={(event) => {
                  setExpYear(normalizeCardExpYearInput(event.target.value))
                  setFieldErrors((current) => ({ ...current, expYear: '' }))
                }}
                size="small"
                fullWidth
                required
                autoComplete="cc-exp-year"
                inputMode="numeric"
                error={Boolean(fieldErrors.expYear)}
                helperText={fieldErrors.expYear}
              />
              <TextField
                label="CVV"
                value={securityCode}
                onChange={(event) => {
                  const maxLength = Math.max(...(cardBrand?.cvvLengths ?? DEFAULT_CARD_CVV_LENGTHS))
                  setSecurityCode(normalizeDigits(event.target.value).slice(0, maxLength))
                  setFieldErrors((current) => ({ ...current, securityCode: '' }))
                }}
                onFocus={() => setIsCardBackVisible(true)}
                onBlur={() => setIsCardBackVisible(false)}
                size="small"
                fullWidth
                required
                autoComplete="cc-csc"
                inputMode="numeric"
                error={Boolean(fieldErrors.securityCode)}
                helperText={fieldErrors.securityCode}
              />
            </Stack>
            <TextField
              select
              label="Parcelas"
              value={installments}
              onChange={(event) => setInstallments(event.target.value)}
              size="small"
              fullWidth
              disabled={isLoadingInstallmentOptions}
              helperText={
                installmentOptionsError
                ?? (cardBin.length < 6
                  ? 'Digite os 6 primeiros números do cartão para consultar juros e parcelamento.'
                  : isLoadingInstallmentOptions
                    ? 'Consultando condições de parcelamento no PagBank…'
                    : selectedInstallmentOption && !selectedInstallmentOption.interest_free && selectedInstallmentOption.buyer_interest_total > 0
                      ? (() => {
                          const { firstInstallmentCents, remainingInstallmentCents, remainingCount } =
                            computeInstallmentBreakdown(selectedInstallmentOption)

                          return remainingCount > 0
                            ? `1ª parcela de ${formatCurrency(firstInstallmentCents / 100)} sem juros; demais ${remainingCount}x de ${formatCurrency(remainingInstallmentCents / 100)} com juros (total de juros: ${formatCurrency(selectedInstallmentOption.buyer_interest_total / 100)}).`
                            : `Juros cobrados do comprador: ${formatCurrency(selectedInstallmentOption.buyer_interest_total / 100)}`
                        })()
                      : 'Sem juros para o comprador.')
              }
            >
              {availableInstallmentOptions.map((option) => (
                <MenuItem key={option.installments} value={String(option.installments)}>
                  {formatInstallmentOptionLabel(option)}
                </MenuItem>
              ))}
            </TextField>

            <Button
              variant="contained"
              size="large"
              type="submit"
              disabled={isSubmitting || !sdkReady}
              sx={{ minHeight: UI_SIZE.controlLarge }}
            >
              {isSubmitting ? 'Processando…' : formatPayButtonLabel(selectedInstallmentOption)}
            </Button>
            <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
              Ver status da compra
            </Button>
          </Stack>
        </Box>
      )}
    </Paper>
  )
}

function DebitCardPaymentPanel({
  saleUuid,
  amount,
  payer,
  slug,
  eventSlug,
  sessionId,
}: {
  saleUuid: string
  amount: string
  payer: PaymentFlowPayer
  slug: string
  eventSlug: string | null
  sessionId: string
}) {
  const navigate = useNavigate()
  const [config, setConfig] = useState<SalePaymentCheckoutConfig | null>(null)
  const [isLoadingConfig, setIsLoadingConfig] = useState(true)
  const [configError, setConfigError] = useState<string | null>(null)
  const [sdkReady, setSdkReady] = useState(false)

  const [payerTaxId, setPayerTaxId] = useState(payer.taxId)
  const [holderName, setHolderName] = useState(payer.name)
  const [holderTaxId, setHolderTaxId] = useState(payer.taxId)
  const [payerPhone, setPayerPhone] = useState(payer.phone)
  const [cardNumber, setCardNumber] = useState('')
  const [expMonth, setExpMonth] = useState('')
  const [expYear, setExpYear] = useState('')
  const [securityCode, setSecurityCode] = useState('')
  const [addressLine1, setAddressLine1] = useState('')
  const [addressNumber, setAddressNumber] = useState('')
  const [addressComplement, setAddressComplement] = useState('')
  const [addressDistrict, setAddressDistrict] = useState('')
  const [addressCity, setAddressCity] = useState('')
  const [addressState, setAddressState] = useState('')
  const [addressZipCode, setAddressZipCode] = useState('')
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingConfig(true)
    setConfigError(null)

    storefrontSalePaymentService
      .getSalePaymentCheckoutConfig(saleUuid)
      .then(async (result) => {
        setConfig(result)

        if (!result.available || !result.public_key || !result.sdk_script_url || !result.three_ds_session || !result.environment) {
          throw new Error('checkout_unavailable')
        }

        await loadPagSeguroSdk(result.sdk_script_url)

        if (!window.PagSeguro) {
          throw new Error('sdk_unavailable')
        }

        window.PagSeguro.setUp({
          session: result.three_ds_session,
          env: result.environment,
        })

        setSdkReady(true)
      })
      .catch((error: unknown) => {
        setConfigError(getApiErrorMessage(error, 'Não foi possível preparar o débito com cartão agora.'))
      })
      .finally(() => setIsLoadingConfig(false))
  }, [saleUuid])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    if (!config?.public_key || !window.PagSeguro) {
      setFormError('O checkout do débito ainda não está pronto. Atualize a página e tente novamente.')
      return
    }

    setIsSubmitting(true)

    try {
      const normalizedPhone = payerPhone.replace(/\D+/g, '')
      const normalizedTaxId = payerTaxId.replace(/\D+/g, '')
      const normalizedCardNumber = cardNumber.replace(/\D+/g, '')
      const normalizedZipCode = addressZipCode.replace(/\D+/g, '')

      if (normalizedPhone.length < 10) {
        throw new Error('Informe um telefone celular válido para autenticar o cartão.')
      }

      if (
        addressLine1.trim().length < 3 ||
        addressNumber.trim().length < 1 ||
        addressDistrict.trim().length < 2 ||
        addressCity.trim().length < 2 ||
        addressState.trim().length !== 2 ||
        normalizedZipCode.length !== 8
      ) {
        throw new Error('Preencha o endereço de cobrança completo para autenticar o cartão.')
      }

      const encrypted = window.PagSeguro.encryptCard({
        publicKey: config.public_key,
        holder: holderName.trim(),
        number: normalizedCardNumber,
        expMonth: expMonth.replace(/\D+/g, ''),
        expYear: normalizeCardExpYear(expYear),
        securityCode: securityCode.replace(/\D+/g, ''),
      })

      if (encrypted.hasErrors || !encrypted.encryptedCard) {
        const firstError = encrypted.errors?.[0]?.message?.trim()
        throw new Error(firstError || 'Não foi possível criptografar os dados do cartão.')
      }

      const threeDsResult = await window.PagSeguro.authenticate3DS({
        customer: {
          name: payer.name,
          email: payer.email,
          taxId: normalizedTaxId,
          phones: [
            {
              country: '55',
              area: normalizedPhone.slice(0, 2),
              number: normalizedPhone.slice(2),
              type: 'MOBILE',
            },
          ],
        },
        paymentMethod: {
          type: 'DEBIT_CARD',
          installments: 1,
          card: {
            number: normalizedCardNumber,
            expMonth: expMonth.replace(/\D+/g, ''),
            expYear: normalizeCardExpYear(expYear),
            holder: {
              name: holderName.trim(),
            },
          },
        },
        amount: {
          value: Math.round(Number(amount) * 100),
          currency: 'BRL',
        },
        billingAddress: {
          line1: `${addressLine1.trim()}, ${addressNumber.trim()} - ${addressDistrict.trim()}`,
          line2: addressComplement.trim() || undefined,
          city: addressCity.trim(),
          regionCode: addressState.trim().toUpperCase(),
          country: 'BRA',
          postalCode: normalizedZipCode,
        },
        dataOnly: false,
      })

      if (threeDsResult.status !== 'AUTH_FLOW_COMPLETED' || !threeDsResult.id) {
        throw new Error('A autenticação do débito não foi concluída. Tente outro cartão ou finalize com Pix.')
      }

      await storefrontSalePaymentService.createSalePaymentCharge(saleUuid, {
        method: 'debit_card',
        payer_tax_id: normalizedTaxId,
        payer_name: payer.name,
        payer_email: payer.email,
        payer_phone: normalizedPhone,
        card: {
          encrypted: encrypted.encryptedCard,
          holder_name: holderName.trim(),
          holder_tax_id: holderTaxId.replace(/\D+/g, ''),
        },
        authentication_method: {
          type: 'THREEDS',
          id: threeDsResult.id,
        },
      })

      if (eventSlug) sendFunnelEvent(slug, eventSlug, sessionId, 'payment_confirmed')
      navigate(`/compra/${saleUuid}`)
    } catch (error) {
      setFormError(getApiErrorMessage(error, error instanceof Error ? error.message : 'Não foi possível processar o débito agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 } }}>
      <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 0.5 }}>Cartão de débito</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2.5 }}>
        O PagBank valida o débito com autenticação 3DS antes de concluir a cobrança.
      </Typography>

      {isLoadingConfig && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1.5}>
          <CircularProgress size={28} />
          <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Preparando autenticação segura do débito…</Typography>
        </Stack>
      )}

      {!isLoadingConfig && configError && (
        <Stack spacing={2}>
          <Alert severity="error" variant="outlined">
            {configError}
          </Alert>
          <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
            Ver status da compra
          </Button>
        </Stack>
      )}

      {!isLoadingConfig && !configError && (
        <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Stack spacing={2}>
            {!sdkReady && (
              <Alert severity="warning" variant="outlined">
                O ambiente seguro do PagBank ainda está carregando. Aguarde alguns instantes.
              </Alert>
            )}

            {formError && (
              <Alert severity="error" variant="outlined">
                {formError}
              </Alert>
            )}

            <TextField label="CPF/CNPJ do pagador" value={payerTaxId} onChange={(event) => setPayerTaxId(formatCpfCnpj(event.target.value))} size="small" fullWidth required />
            <TextField
              label="Telefone do pagador"
              value={payerPhone}
              onChange={(event) => setPayerPhone(event.target.value)}
              size="small"
              fullWidth
              required
              inputMode="numeric"
            />
            <TextField label="Nome do titular do cartão" value={holderName} onChange={(event) => setHolderName(event.target.value)} size="small" fullWidth required />
            <TextField label="CPF/CNPJ do titular" value={holderTaxId} onChange={(event) => setHolderTaxId(formatCpfCnpj(event.target.value))} size="small" fullWidth required />
            <TextField label="Número do cartão" value={cardNumber} onChange={(event) => setCardNumber(event.target.value)} size="small" fullWidth required />

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <TextField label="Mês" value={expMonth} onChange={(event) => setExpMonth(event.target.value)} size="small" fullWidth required />
              <TextField label="Ano" value={expYear} onChange={(event) => setExpYear(event.target.value)} size="small" fullWidth required />
              <TextField label="CVV" value={securityCode} onChange={(event) => setSecurityCode(event.target.value)} size="small" fullWidth required />
            </Stack>

            <Typography sx={{ fontSize: 14, fontWeight: 600, pt: 0.5 }}>Endereço de cobrança</Typography>

            <TextField label="Rua / avenida" value={addressLine1} onChange={(event) => setAddressLine1(event.target.value)} size="small" fullWidth required />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <TextField label="Número" value={addressNumber} onChange={(event) => setAddressNumber(event.target.value)} size="small" fullWidth required />
              <TextField label="Complemento" value={addressComplement} onChange={(event) => setAddressComplement(event.target.value)} size="small" fullWidth />
            </Stack>
            <TextField label="Bairro" value={addressDistrict} onChange={(event) => setAddressDistrict(event.target.value)} size="small" fullWidth required />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <TextField label="Cidade" value={addressCity} onChange={(event) => setAddressCity(event.target.value)} size="small" fullWidth required />
              <TextField label="UF" value={addressState} onChange={(event) => setAddressState(event.target.value)} size="small" fullWidth required />
              <TextField label="CEP" value={addressZipCode} onChange={(event) => setAddressZipCode(event.target.value)} size="small" fullWidth required />
            </Stack>

            <Button variant="contained" size="large" type="submit" disabled={isSubmitting || !sdkReady} sx={{ minHeight: UI_SIZE.controlLarge }}>
              {isSubmitting ? 'Autenticando…' : 'Pagar com débito'}
            </Button>
            <Button variant="text" onClick={() => navigate(`/compra/${saleUuid}`)}>
              Ver status da compra
            </Button>
          </Stack>
        </Box>
      )}
    </Paper>
  )
}

function PaymentStepPanel({ flow }: { flow: PaymentFlowState }) {
  if (flow.method === 'pix') {
    return (
      <PixPaymentPanel
        saleUuid={flow.saleUuid}
        slug={flow.slug}
        eventSlug={flow.eventSlug}
        sessionId={flow.sessionId}
      />
    )
  }

  if (flow.method === 'credit_card') {
    return (
      <CreditCardPaymentPanel
        saleUuid={flow.saleUuid}
        amount={flow.amount}
        payer={flow.payer}
        slug={flow.slug}
        eventSlug={flow.eventSlug}
        sessionId={flow.sessionId}
      />
    )
  }

  return (
    <DebitCardPaymentPanel
      saleUuid={flow.saleUuid}
      amount={flow.amount}
      payer={flow.payer}
      slug={flow.slug}
      eventSlug={flow.eventSlug}
      sessionId={flow.sessionId}
    />
  )
}

/** Passo 2: dados de contato + resumo + confirmação. */
function DetailsAndReviewStep({ slug }: { slug: string }) {
  const navigate = useNavigate()
  const { items, totalAmount, clear, sessionId } = useStorefrontCart()
  const { markCompleted } = useCartAbandonmentTelemetry(slug)

  // Funil de conversão (roadmap A2) — "iniciou checkout" dispara uma vez ao
  // entrar neste passo, para o primeiro evento presente no carrinho.
  const hasSentCheckoutStartedRef = useRef(false)
  useEffect(() => {
    if (hasSentCheckoutStartedRef.current) return
    const firstEventSlug = items.find((item) => item.event_slug)?.event_slug
    if (firstEventSlug) {
      sendFunnelEvent(slug, firstEventSlug, sessionId, 'checkout_started')
      hasSentCheckoutStartedRef.current = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, sessionId, items])

  const [clientName, setClientName] = useState('')
  const [clientLastName, setClientLastName] = useState('')
  const [clientEmail, setClientEmail] = useState('')
  const [clientPhone, setClientPhone] = useState('')
  const [notes, setNotes] = useState('')

  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [couponCode, setCouponCode] = useState('')
  const [couponStatus, setCouponStatus] = useState<CouponStatus>('idle')
  const [couponMessage, setCouponMessage] = useState<string | null>(null)
  const [appliedDiscount, setAppliedDiscount] = useState(0)
  const [appliedCouponCode, setAppliedCouponCode] = useState<string | null>(null)

  // O checkout público já persiste `payment_method` na venda e o backend
  // expõe endpoints próprios para configuração/cobrança do PSP por venda.
  // Aqui a UI decide se segue direto pro rastreio (dinheiro) ou se abre o
  // passo de pagamento online (Pix/crédito/débito).
  const [acceptedPaymentMethods, setAcceptedPaymentMethods] = useState<PaymentMethod[]>([])
  const [paymentFlow, setPaymentFlow] = useState<PaymentFlowState | null>(null)

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
  // Participantes por item de ingresso (spec 5.10 Etapa 2) — opcional,
  // "participante 1, 2, 3..." conforme a quantidade de cada ticket_type no
  // carrinho. Só faz sentido para ticket_type_uuid (adicional/estacionamento
  // não tem participante). Chave = StorefrontCartItem.id.
  const [participantsByItem, setParticipantsByItem] = useState<
    Record<string, Array<{ name: string; document: string }>>
  >({})
  const ticketParticipantItems = useMemo(
    () => items.filter((item): item is StorefrontCartItem & { ticket_type_uuid: string } => Boolean(item.ticket_type_uuid)),
    [items],
  )

  function getParticipant(itemId: string, index: number): { name: string; document: string } {
    return participantsByItem[itemId]?.[index] ?? { name: '', document: '' }
  }

  function setParticipant(itemId: string, index: number, quantity: number, field: 'name' | 'document', value: string) {
    setParticipantsByItem((current) => {
      const existing = current[itemId] ?? []
      const next = Array.from({ length: quantity }, (_, i) => existing[i] ?? { name: '', document: '' })
      next[index] = { ...next[index], [field]: value }
      return { ...current, [itemId]: next }
    })
  }

  const [hold, setHold] = useState<StorefrontInventoryHold | null>(null)
  const [holdSecondsLeft, setHoldSecondsLeft] = useState(0)
  const [isPreparingHold, setIsPreparingHold] = useState(false)
  const [holdError, setHoldError] = useState<string | null>(null)
  const [holdRetryNonce, setHoldRetryNonce] = useState(0)
  const shouldReleaseHoldRef = useRef(true)
  const lastReleasedHoldUuidRef = useRef<string | null>(null)
  // Cloudflare Turnstile (camada adicional ao honeypot/tempo mínimo já
  // aplicados na criação de hold, ver App\Services\Security\
  // TurnstileVerificationService). VITE_TURNSTILE_SITE_KEY vazio (default
  // até as credenciais serem configuradas) = hasTurnstileSiteKey false, a
  // reserva segue criada imediatamente como hoje, sem esperar token.
  const hasTurnstileSiteKey = Boolean(import.meta.env.VITE_TURNSTILE_SITE_KEY)
  const [turnstileToken, setTurnstileToken] = useState<string | undefined>(undefined)

  const holdPayloadItems = useMemo<StorefrontCreateHoldPayload['items']>(
    () =>
      items.map((item) => ({
        ticket_type_uuid: item.ticket_type_uuid,
        event_product_uuid: item.event_product_uuid,
        seat_uuid: item.seat_uuid ?? undefined,
        sector_name: !item.seat_uuid && item.seat_sector_name ? item.seat_sector_name : undefined,
        quantity: item.quantity,
      })),
    [items],
  )

  const holdContext = useMemo(() => {
    const eventSlugs = Array.from(new Set(items.map((item) => item.event_slug?.trim()).filter(Boolean)))
    const hasLegacyItems = items.some((item) => !item.event_slug?.trim())
    const sessionUuids = Array.from(new Set(items.map((item) => item.session_uuid?.trim()).filter(Boolean)))
    const hasMixedSessionState = items.some((item) => {
      const normalized = item.session_uuid?.trim() || null
      return normalized !== sessionUuids[0] && (normalized !== null || sessionUuids.length > 0)
    })

    if (hasLegacyItems) {
      return {
        eligible: false,
        eventSlug: null,
        sessionUuid: null,
        message:
          'Seu carrinho foi montado com itens de uma versão anterior. Volte ao catálogo, atualize os itens e tente novamente para reservar os ingressos.',
      }
    }

    if (eventSlugs.length !== 1) {
      return {
        eligible: false,
        eventSlug: null,
        sessionUuid: null,
        message:
          'A reserva temporária ainda funciona por evento. Para garantir disponibilidade, finalize um evento por vez.',
      }
    }

    if (hasMixedSessionState) {
      return {
        eligible: false,
        eventSlug: null,
        sessionUuid: null,
        message:
          'Seu carrinho mistura itens de sessões diferentes. Finalize uma sessão por vez para continuar com a reserva temporária.',
      }
    }

    return {
      eligible: true,
      eventSlug: eventSlugs[0] ?? null,
      sessionUuid: sessionUuids[0] ?? null,
      message: null,
    }
  }, [items])

  const holdSignature = useMemo(
    () => buildHoldSignature(holdContext.eventSlug, sessionId, holdPayloadItems),
    [holdContext.eventSlug, holdPayloadItems, sessionId],
  )

  const hasActiveHold = Boolean(hold && hold.status === 'reservado' && holdSecondsLeft > 0)

  useEffect(() => {
    let cancelled = false
    storefrontService
      .getStorefront(slug)
      .then((tenant) => {
        if (!cancelled) {
          setAcceptedPaymentMethods(tenant.accepted_payment_methods)
        }
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [slug])

  useEffect(() => {
    shouldReleaseHoldRef.current = true
  }, [])

  useEffect(() => {
    if (!holdContext.eligible || !holdContext.eventSlug || items.length === 0) {
      setHold(null)
      setHoldSecondsLeft(0)
      setIsPreparingHold(false)
      setHoldError(holdContext.message)
      return
    }

    // Aguarda o token do Turnstile só quando o widget está de fato ativo
    // (VITE_TURNSTILE_SITE_KEY configurada); sem isso, a reserva é criada
    // imediatamente como já acontecia antes desta camada existir.
    if (hasTurnstileSiteKey && !turnstileToken) {
      setIsPreparingHold(true)
      return
    }

    let cancelled = false
    let createdHoldUuid: string | null = null

    async function syncHold() {
      setIsPreparingHold(true)
      setHoldError(null)
      setHold(null)
      setHoldSecondsLeft(0)

      try {
        const createdHold = await storefrontHoldService.createHold(slug, holdContext.eventSlug as string, {
          session_token: sessionId,
          session_uuid: holdContext.sessionUuid ?? undefined,
          items: holdPayloadItems,
          affiliate_code: getStorefrontTracking(slug)?.affiliate_code ?? undefined,
          turnstile_token: turnstileToken,
        })

        createdHoldUuid = createdHold.uuid

        if (cancelled) {
          storefrontHoldService.releaseHoldBestEffort(slug, createdHold.uuid, sessionId)
          return
        }

        setHold(createdHold)
        setHoldSecondsLeft(getHoldSecondsLeft(createdHold))
        if (holdContext.eventSlug) {
          sendFunnelEvent(slug, holdContext.eventSlug, sessionId, 'hold_created')
        }
      } catch (error) {
        if (cancelled) return
        setHold(null)
        setHoldSecondsLeft(0)
        setHoldError(
          getApiErrorMessage(error, 'Não foi possível reservar seus itens agora. Revise o carrinho e tente novamente.'),
        )
      } finally {
        if (!cancelled) setIsPreparingHold(false)
      }
    }

    void syncHold()

    return () => {
      cancelled = true
      if (!createdHoldUuid || !shouldReleaseHoldRef.current || lastReleasedHoldUuidRef.current === createdHoldUuid) return
      lastReleasedHoldUuidRef.current = createdHoldUuid
      storefrontHoldService.releaseHoldBestEffort(slug, createdHoldUuid, sessionId)
    }
  }, [
    slug,
    sessionId,
    items.length,
    holdContext.eligible,
    holdContext.eventSlug,
    holdContext.sessionUuid,
    holdContext.message,
    holdPayloadItems,
    holdRetryNonce,
    holdSignature,
    hasTurnstileSiteKey,
    turnstileToken,
  ])

  useEffect(() => {
    if (!hold?.uuid) return

    const interval = window.setInterval(() => {
      setHoldSecondsLeft(getHoldSecondsLeft(hold))
    }, 1000)

    return () => window.clearInterval(interval)
  }, [hold])

  useEffect(() => {
    if (!hold?.uuid || !holdContext.eligible || !holdContext.eventSlug) return

    const interval = window.setInterval(() => {
      storefrontHoldService
        .renewHold(slug, hold.uuid, sessionId)
        .then((renewedHold) => {
          setHold(renewedHold)
          setHoldSecondsLeft(getHoldSecondsLeft(renewedHold))
          setHoldError(null)
        })
        .catch((error: unknown) => {
          setHoldError(getApiErrorMessage(error, 'Não foi possível renovar sua reserva temporária.'))
        })
    }, HOLD_RENEW_INTERVAL_MS)

    return () => window.clearInterval(interval)
  }, [slug, sessionId, hold?.uuid, holdContext.eligible, holdContext.eventSlug, holdContext.sessionUuid])

  useEffect(() => {
    if (!hold?.uuid) return

    const releaseCurrentHold = () => {
      if (!shouldReleaseHoldRef.current || lastReleasedHoldUuidRef.current === hold.uuid) return
      lastReleasedHoldUuidRef.current = hold.uuid
      storefrontHoldService.releaseHoldBestEffort(slug, hold.uuid, sessionId)
    }

    window.addEventListener('pagehide', releaseCurrentHold)
    return () => window.removeEventListener('pagehide', releaseCurrentHold)
  }, [slug, sessionId, hold?.uuid])

  useEffect(() => {
    if (!hold?.uuid) return
    if (holdSecondsLeft > 0) return

    // Reserva expirou de fato (não só erro de rede) — invalida o checkout e
    // leva o cliente de volta pro carrinho com aviso claro, em vez de deixar
    // a tela de checkout "viva" com um hold morto (spec item 2).
    shouldReleaseHoldRef.current = false
    setHold(null)
    navigate(`/eventos/${slug}/carrinho`, {
      state: {
        holdExpiredMessage:
          'Sua reserva temporária expirou antes da finalização. Revise seus itens e tente novamente.',
      },
    })
  }, [hold?.uuid, holdSecondsLeft, navigate, slug])

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
          : 'Cupom aplicado — o desconto final é confirmado ao concluir a compra.',
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

  function validate(): Record<string, string[]> {
    const errors: Record<string, string[]> = {}
    if (clientName.trim().length < 2) errors.client_name = ['Informe seu nome.']
    if (clientLastName.trim().length < 1) errors.client_last_name = ['Informe seu sobrenome.']
    if (!isValidCheckoutEmail(clientEmail)) errors.client_email = ['Informe um e-mail válido.']
    if (!isValidBrazilPhone(clientPhone)) errors.client_phone = ['Informe um telefone com DDD e 8 ou 9 dígitos.']
    if (acceptedPaymentMethods.length > 0 && !intendedPaymentMethod) {
      errors.payment_method = ['Selecione uma forma de pagamento.']
    }
    return errors
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    if (holdContext.eligible && !hasActiveHold) {
      setFormError('Sua reserva temporária não está ativa no momento. Gere uma nova reserva antes de confirmar a compra.')
      return
    }

    const clientErrors = validate()
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }
    setFieldErrors({})
    setIsSubmitting(true)

    const tracking = getStorefrontTracking(slug)

    const payload: StorefrontCheckoutPayload = {
      items: items.map((item) => {
        const rawParticipants = item.ticket_type_uuid ? participantsByItem[item.id] : undefined
        const participants = rawParticipants
          ?.map((participant) => ({
            name: participant.name.trim() || undefined,
            document: participant.document.trim() || undefined,
          }))
          .filter((participant) => participant.name || participant.document)

        return {
          ticket_type_uuid: item.ticket_type_uuid,
          event_product_uuid: item.event_product_uuid,
          quantity: item.quantity,
          notes: item.notes?.trim() || undefined,
          participants: participants && participants.length > 0 ? participants : undefined,
        }
      }),
      hold_uuid: holdContext.eligible ? hold?.uuid : undefined,
      session_token: holdContext.eligible ? sessionId : undefined,
      client_name: clientName.trim(),
      client_last_name: clientLastName.trim(),
      client_email: clientEmail.trim().toLowerCase(),
      client_phone: normalizeDigits(clientPhone),
      notes: notes.trim() || undefined,
      coupon_code: appliedCouponCode ?? undefined,
      payment_method: intendedPaymentMethod || undefined,
      needs_change: (intendedPaymentMethod === 'cash' && needsChange) || undefined,
      change_for_amount:
        intendedPaymentMethod === 'cash' && needsChange ? Number(changeForAmount) : undefined,
      // Fallback de afiliado (só usado quando o checkout não passou por um hold —
      // o caminho principal já é o hold carregar o affiliate_code, ver syncHold acima)
      // + UTM de campanha, ambos vindos da atribuição salva em localStorage.
      affiliate_code: !holdContext.eligible ? tracking?.affiliate_code ?? undefined : undefined,
      utm_source: tracking?.utm_source ?? undefined,
      utm_medium: tracking?.utm_medium ?? undefined,
      utm_campaign: tracking?.utm_campaign ?? undefined,
    }

    try {
      const result = await storefrontCheckoutService.checkout(slug, payload)
      shouldReleaseHoldRef.current = false
      markCompleted()
      const funnelEventSlug = items.find((item) => item.event_slug)?.event_slug ?? null

      if (intendedPaymentMethod === 'pix' || intendedPaymentMethod === 'credit_card' || intendedPaymentMethod === 'debit_card') {
        setPaymentFlow({
          saleUuid: result.sale.uuid,
          method: intendedPaymentMethod,
          amount: String(Math.max(0, totalAmount - (couponStatus === 'applied' ? appliedDiscount : 0))),
          payer: {
            name: `${clientName.trim()} ${clientLastName.trim()}`.trim(),
            email: clientEmail.trim().toLowerCase(),
            phone: normalizeDigits(clientPhone),
            taxId: '',
          },
          slug,
          eventSlug: funnelEventSlug,
          sessionId,
        })
      } else {
        // Meio de pagamento offline (ex.: dinheiro) — sem passo de
        // confirmação adicional a instrumentar, então "pagamento
        // confirmado" é registrado aqui mesmo (aproximação deliberada).
        if (funnelEventSlug) {
          sendFunnelEvent(slug, funnelEventSlug, sessionId, 'payment_confirmed')
        }
        clear()
        navigate(`/compra/${result.sale.uuid}`)
      }
    } catch (error) {
      if (error instanceof ApiRequestError && error.code === INVALID_COUPON_CODE) {
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
        (error.code === BELOW_MINIMUM_ORDER_CODE ||
          error.code === COUPON_USAGE_LIMIT_REACHED_CODE)
      ) {
        // Mensagem já pronta pra exibir direto (traduzida no backend),
        // sem reformular.
        setFormError(error.message)
        if (error.code === COUPON_USAGE_LIMIT_REACHED_CODE) {
          // Cupom válido na prévia mas rejeitado no submit final (ex.:
          // atingiu o limite entre a prévia e o envio) — remove pra não
          // travar o cliente tentando de novo com o mesmo código.
          handleRemoveCoupon()
        }
      } else if (error instanceof ApiRequestError && error.status === 422 && isHoldInvalidMessage(error.message)) {
        // Hold expirou/ficou desatualizado exatamente no submit (422
        // `inventory_hold.not_active`/`checkout_mismatch`) — mensagem já
        // amigável e traduzida do backend; limpamos o hold local (já morto
        // no servidor, nada a liberar) e deixamos o botão "Gerar nova
        // reserva" pronto para o cliente tentar de novo sem sair da tela.
        setHold(null)
        setHoldSecondsLeft(0)
        setHoldError(error.message)
        setFormError(error.message)
      } else {
        setFormError(getApiErrorMessage(error, 'Não foi possível confirmar sua compra agora. Tente novamente.'))
        if (error instanceof ApiRequestError) setFieldErrors(error.errors)
      }
      shouldReleaseHoldRef.current = true
    } finally {
      setIsSubmitting(false)
    }
  }

  if (paymentFlow) {
    return <PaymentStepPanel flow={paymentFlow} />
  }

  return (
    <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
      {/* Cloudflare Turnstile invisível — camada adicional de anti-bot na
          criação da reserva temporária (ver App\Services\Security\
          TurnstileVerificationService). Sem VITE_TURNSTILE_SITE_KEY
          configurada o componente não renderiza nada. */}
      <TurnstileWidget onVerify={setTurnstileToken} onExpire={() => setTurnstileToken(undefined)} size="invisible" />
      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Stack spacing={1.25}>
          <Typography sx={{ fontSize: 15, fontWeight: 700 }}>Reserva temporária</Typography>

          {holdContext.eligible ? (
            <>
              {hasActiveHold && hold ? (
                <Alert
                  severity={
                    holdSecondsLeft <= HOLD_CRITICAL_SECONDS
                      ? 'error'
                      : holdSecondsLeft <= HOLD_WARNING_SECONDS
                        ? 'warning'
                        : 'success'
                  }
                  variant="outlined"
                >
                  {holdSecondsLeft <= HOLD_CRITICAL_SECONDS
                    ? `Sua reserva expira em menos de 1 minuto (${formatCountdown(holdSecondsLeft)})! Finalize agora ou seus itens serão liberados.`
                    : holdSecondsLeft <= HOLD_WARNING_SECONDS
                      ? `Sua reserva expira em ${formatCountdown(holdSecondsLeft)}. Finalize o quanto antes.`
                      : `Seus itens estão reservados por mais ${formatCountdown(holdSecondsLeft)}.`}
                </Alert>
              ) : isPreparingHold ? (
                <Alert severity="info" variant="outlined">
                  Reservando seus itens agora. Aguarde alguns instantes antes de confirmar a compra.
                </Alert>
              ) : (
                <Alert severity="warning" variant="outlined">
                  {holdError ?? 'Sua reserva temporária não está ativa no momento.'}
                </Alert>
              )}

              {(holdError || !hasActiveHold) && (
                <Button variant="outlined" onClick={() => setHoldRetryNonce((current) => current + 1)} disabled={isPreparingHold}>
                  {isPreparingHold ? 'Gerando reserva…' : 'Gerar nova reserva'}
                </Button>
              )}
            </>
          ) : (
            <Alert severity="info" variant="outlined">
              {holdContext.message}
            </Alert>
          )}
        </Stack>
      </Paper>

      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 2 }}>
          Retirada e contato
        </Typography>

        {formError && (
          <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 2.5 }}>
            {formError}
          </Alert>
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
            onChange={(event) => setClientPhone(formatBrazilPhone(event.target.value))}
            error={Boolean(fieldErrors.client_phone)}
            helperText={fieldErrors.client_phone?.[0]}
            inputMode="numeric"
            required
            fullWidth
          />
          <TextField
            label="E-mail"
            value={clientEmail}
            onChange={(event) => setClientEmail(event.target.value)}
            error={Boolean(fieldErrors.client_email)}
            helperText={fieldErrors.client_email?.[0]}
            inputMode="email"
            required
            fullWidth
          />
        </Stack>

        <Alert severity="info" variant="outlined">
          Sem endereço necessário. Este checkout registra apenas os dados de contato do comprador e a retirada dos ingressos.
        </Alert>

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

      {ticketParticipantItems.length > 0 && (
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
          <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 0.5 }}>Participantes (opcional)</Typography>
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 2 }}>
            Informe nome e documento de quem vai usar cada ingresso. Deixe em branco para usar os dados do comprador.
          </Typography>
          <Stack spacing={2.5}>
            {ticketParticipantItems.map((item) => (
              <Box key={item.id}>
                <Typography sx={{ fontSize: 13.5, fontWeight: 600, mb: 1 }}>
                  {item.name}
                  {item.seat_label ? ` — ${item.seat_label}` : ''}
                </Typography>
                <Stack spacing={1.25}>
                  {Array.from({ length: item.quantity }, (_, index) => {
                    const participant = getParticipant(item.id, index)
                    return (
                      <Stack key={index} direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                        <TextField
                          label={`Participante ${index + 1} — nome`}
                          value={participant.name}
                          onChange={(event) => setParticipant(item.id, index, item.quantity, 'name', event.target.value)}
                          size="small"
                          fullWidth
                        />
                        <TextField
                          label="Documento"
                          value={participant.document}
                          onChange={(event) => setParticipant(item.id, index, item.quantity, 'document', event.target.value)}
                          size="small"
                          fullWidth
                        />
                      </Stack>
                    )
                  })}
                </Stack>
              </Box>
            ))}
          </Stack>
        </Paper>
      )}

      <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2.5, sm: 3 }, mb: 2.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 1.5 }}>Resumo da compra</Typography>
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
              {item.session_name && (
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>Sessão: {item.session_name}</Typography>
              )}
              {item.seat_label && (
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>
                  Lugar: {item.seat_label}
                  {item.seat_sector_name ? ` • ${item.seat_sector_name}` : ''}
                </Typography>
              )}
            </Box>
          ))}
        </Stack>
        <Stack spacing={0.75} sx={{ mt: 2, pt: 2, borderTop: '1px solid var(--pt-border)' }}>
          <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Subtotal</Typography>
            <Typography sx={{ fontSize: 13.5 }}>{formatCurrency(totalAmount)}</Typography>
          </Stack>
          <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Entrega</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-primary)' }}>Retirada no local</Typography>
          </Stack>
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
                  totalAmount - (couponStatus === 'applied' ? appliedDiscount : 0),
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
        disabled={isSubmitting || (holdContext.eligible && (isPreparingHold || !hasActiveHold))}
        sx={{ minHeight: UI_SIZE.controlLarge }}
      >
        {isSubmitting ? 'Confirmando…' : intendedPaymentMethod === 'pix' ? 'Confirmar e gerar Pix' : 'Confirmar compra'}
      </Button>
    </Box>
  )
}

export function StorefrontCheckoutPage() {
  const { slug } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const { items } = useStorefrontCart()

  if (!slug) return null

  if (items.length === 0) {
    return (
      <PageShell slug={slug}>
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 1 }}>Seu carrinho está vazio</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', mb: 2.5 }}>
            Volte ao catálogo e adicione produtos antes de finalizar a compra.
          </Typography>
          <Button variant="contained" onClick={() => navigate(`/eventos/${slug}`)}>
            Ver catálogo
          </Button>
        </Paper>
      </PageShell>
    )
  }

  return (
    <PageShell slug={slug}>
      <DetailsAndReviewStep slug={slug} />
    </PageShell>
  )
}
