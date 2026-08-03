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
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { OtpIdentifyForm } from '../../components/storefront/OtpIdentifyForm'
import { Logo } from '../../components/ui/Logo'
import { useCartAbandonmentTelemetry } from '../../hooks/useCartAbandonmentTelemetry'
import { formatCountdown } from '../../hooks/useCountdown'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { getSaleTracking } from '../../services/saleTrackingService'
import * as portalSaleService from '../../services/portalSaleService'
import * as storefrontCheckoutService from '../../services/storefrontCheckoutService'
import * as storefrontHoldService from '../../services/storefrontHoldService'
import * as storefrontService from '../../services/storefrontService'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import type { SalePayment } from '../../types/sale'
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
function PixPaymentPanel({ saleUuid }: { saleUuid: string }) {
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
    portalSaleService
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
        navigate(`/rastreio/${saleUuid}`)
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
          <Button variant="text" onClick={() => navigate(`/rastreio/${saleUuid}`)}>
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
          <Button variant="text" onClick={() => navigate(`/rastreio/${saleUuid}`)}>
            Ver status da minha compra
          </Button>
        </Stack>
      )}
    </Paper>
  )
}

/** Passo 2: dados de contato + resumo + confirmação. */
function DetailsAndReviewStep({ slug }: { slug: string }) {
  const navigate = useNavigate()
  const { items, totalAmount, clear, sessionId } = useStorefrontCart()
  const { markCompleted } = useCartAbandonmentTelemetry(slug)

  const [clientName, setClientName] = useState('')
  const [clientLastName, setClientLastName] = useState('')
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

  // Checkout Pix (roadmap Fase B, item 1) — só oferecido se a loja aceita
  // Pix (`tenant_settings.accepted_payment_methods`). Escolha é só local:
  // o backend de checkout não recebe forma de pagamento, a cobrança Pix é
  // gerada à parte, depois da venda criada (ver handleSubmit).
  const [acceptedPaymentMethods, setAcceptedPaymentMethods] = useState<PaymentMethod[]>([])
  // Radio "combinar na entrega" vs "Pagar com Pix agora" foi removido
  // temporariamente (sistema não processa pagamento ainda) — `paymentMethod`
  // fica fixo em 'delivery'. O bloco `if ((paymentMethod as string) === 'pix')` em
  // handleSubmit vira código dormente, não é dead-code deletável agora (é
  // feature desligada, não removida).
  const paymentMethod: 'delivery' | 'pix' = 'delivery'
  const [paidSaleUuid, setPaidSaleUuid] = useState<string | null>(null)

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
        })

        createdHoldUuid = createdHold.uuid

        if (cancelled) {
          storefrontHoldService.releaseHoldBestEffort(slug, createdHold.uuid, sessionId)
          return
        }

        setHold(createdHold)
        setHoldSecondsLeft(getHoldSecondsLeft(createdHold))
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
  }, [slug, sessionId, items.length, holdContext.eligible, holdContext.eventSlug, holdContext.sessionUuid, holdContext.message, holdPayloadItems, holdRetryNonce, holdSignature])

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
    if (clientPhone.trim().length < 8) errors.client_phone = ['Informe um telefone válido.']
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
      client_phone: clientPhone.trim(),
      notes: notes.trim() || undefined,
      coupon_code: appliedCouponCode ?? undefined,
      payment_method: intendedPaymentMethod || undefined,
      needs_change: (intendedPaymentMethod === 'cash' && needsChange) || undefined,
      change_for_amount:
        intendedPaymentMethod === 'cash' && needsChange ? Number(changeForAmount) : undefined,
    }

    try {
      const result = await storefrontCheckoutService.checkout(slug, payload)
      shouldReleaseHoldRef.current = false
      markCompleted()
      clear()
      if ((paymentMethod as string) === 'pix') {
        // Venda já criada com sucesso a partir daqui — trocar de tela pra
        // gerar o Pix, nunca perder a compra se a cobrança falhar (o
        // PixPaymentPanel sempre oferece "continuar sem pagar agora").
      setPaidSaleUuid(result.sale.uuid)
      } else {
        navigate(`/rastreio/${result.sale.uuid}`)
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

  if (paidSaleUuid) {
    return <PixPaymentPanel saleUuid={paidSaleUuid} />
  }

  return (
    <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
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
            onChange={(event) => setClientPhone(event.target.value)}
            error={Boolean(fieldErrors.client_phone)}
            helperText={fieldErrors.client_phone?.[0]}
            inputMode="tel"
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
        {isSubmitting ? 'Confirmando…' : (paymentMethod as string) === 'pix' ? 'Confirmar e gerar Pix' : 'Confirmar compra'}
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
            Volte ao catálogo e adicione produtos antes de finalizar a compra.
          </Typography>
          <Button variant="contained" onClick={() => navigate(`/eventos/${slug}`)}>
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
