import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import LinkOutlinedIcon from '@mui/icons-material/LinkOutlined'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import { Alert, Box, Button, Chip, Divider, Paper, Rating, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { QRCodeCanvas } from 'qrcode.react'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { createPortalLink, listSaleTickets } from '../../services/portalSaleService'
import { getSaleTracking } from '../../services/saleTrackingService'
import { rateSale, type SaleRatingResult } from '../../services/saleRatingService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { PortalTicket } from '../../types/portal'
import type { SaleTracking } from '../../types/saleTracking'
import { deriveSaleStatus, STATUS_TONE_COLORS } from '../../utils/saleStatus'
import { formatCurrency, formatDateBR, formatItemQuantity } from '../../utils/format'

function StatusBanner({ sale }: { sale: SaleTracking }) {
  const status = deriveSaleStatus(sale)
  const colors = STATUS_TONE_COLORS[status.tone]

  return (
    <Paper
      elevation={0}
      sx={{
        p: 2.5,
        ...ELEVATED_SURFACE_SX,
        bgcolor: colors.bg,
        border: '1px solid',
        borderColor: colors.fg,
        display: 'flex',
        alignItems: 'flex-start',
        gap: 1.5,
      }}
    >
      <Box sx={{ color: colors.fg, display: 'flex', mt: 0.25, flexShrink: 0, '& svg': { fontSize: 28 } }}>
        {status.icon}
      </Box>
      <Box sx={{ minWidth: 0 }}>
        <Typography sx={{ fontWeight: 700, fontSize: { xs: 16, sm: 17 }, color: colors.fg, lineHeight: 1.3 }}>
          {status.label}
        </Typography>
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5 }}>{status.caption}</Typography>
      </Box>
    </Paper>
  )
}

function LoadingSkeleton() {
  return (
    <Stack spacing={2}>
      <Skeleton variant="rounded" height={72} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
      <Skeleton variant="rounded" height={40} sx={{ borderRadius: 'var(--pt-radius-md)', width: '60%' }} />
      <Skeleton variant="rounded" height={140} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
      <Skeleton variant="rounded" height={64} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
    </Stack>
  )
}

function NotFoundState({ message }: { message: string }) {
  return (
    <Paper
      elevation={0}
      sx={{
        p: 4,
        textAlign: 'center',
        ...ELEVATED_SURFACE_SX,
      }}
    >
      <SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 0.75 }}>Venda não encontrada</Typography>
      <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>{message}</Typography>
    </Paper>
  )
}

function ItemsList({ items }: { items: SaleTracking['items'] }) {
  if (items.length === 0) {
    return (
      <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>Nenhum item registrado nesta compra.</Typography>
    )
  }

  return (
    <Stack spacing={1.25}>
      {items.map((item, index) => (
        <Box
          key={`${item.product_name}-${index}`}
          sx={{
            p: 1.5,
            ...SOFT_PANEL_SX,
          }}
        >
          <Typography sx={{ fontSize: 14.5, fontWeight: 600, wordBreak: 'break-word' }}>
            {item.product_name}
          </Typography>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', mt: 0.5, gap: 1 }}>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
              {formatItemQuantity(item.quantity, item.unit)} × {formatCurrency(item.unit_price)}
            </Typography>
            <Typography sx={{ fontSize: 14, fontWeight: 600, flexShrink: 0 }}>
              {formatCurrency(item.line_total)}
            </Typography>
          </Box>
        </Box>
      ))}
    </Stack>
  )
}

function InstallmentsList({ installments }: { installments: SaleTracking['installments'] }) {
  return (
    <Stack spacing={1.25}>
      {installments.map((installment) => (
        <Box
          key={installment.installment_number}
          sx={{
            p: 1.5,
            ...SOFT_PANEL_SX,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: 1,
          }}
        >
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 14, fontWeight: 600 }}>Parcela {installment.installment_number}</Typography>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
              Vence em {formatDateBR(installment.due_date)}
            </Typography>
          </Box>
          <Stack spacing={0.5} sx={{ alignItems: 'flex-end', flexShrink: 0 }}>
            <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{formatCurrency(installment.amount)}</Typography>
            <Chip
              size="small"
              label={installment.is_paid ? 'Paga' : 'Em aberto'}
              sx={{
                fontWeight: 600,
                height: 22,
                fontSize: 11.5,
                bgcolor: installment.is_paid
                  ? 'color-mix(in srgb, var(--pt-success) 14%, transparent)'
                  : 'color-mix(in srgb, var(--pt-muted) 14%, transparent)',
                color: installment.is_paid ? 'var(--pt-success)' : 'var(--pt-muted)',
              }}
            />
          </Stack>
        </Box>
      ))}
    </Stack>
  )
}

/**
 * CTA opcional (Fase 5.2, "Portal do cliente final") — não interfere na
 * visualização da compra atual, só oferece agregar esta compra a uma conta
 * do portal. Se o cliente já está logado no portal, vincula direto
 * (`POST /portal/links`, idempotente) e vai pra lista. Se não, manda pro
 * login do portal levando o `order_uuid` via querystring (`?vincular=`) —
 * `PortalLoginPage` completa o vínculo sozinha depois do `verify-otp`.
 */
function LinkOrdersSection({ saleUuid }: { saleUuid: string }) {
  const { isAuthenticated } = usePortalAuth()
  const navigate = useNavigate()
  const [isLinking, setIsLinking] = useState(false)
  const [linkError, setLinkError] = useState<string | null>(null)

  async function handleClick() {
    setLinkError(null)

    if (!isAuthenticated) {
      navigate(`/portal/entrar?vincular=${encodeURIComponent(saleUuid)}`)
      return
    }

    setIsLinking(true)
    try {
      await createPortalLink({ order_uuid: saleUuid })
      navigate('/portal/compras')
    } catch (error) {
      setLinkError(getApiErrorMessage(error, 'Não foi possível vincular esta compra agora. Tente novamente.'))
    } finally {
      setIsLinking(false)
    }
  }

  return (
    <Paper
      elevation={0}
      sx={{
        p: 2,
        ...SOFT_PANEL_SX,
        borderStyle: 'dashed',
      }}
    >
      <Chip
        label="Novidade"
        size="small"
        sx={{
          fontWeight: 700,
          height: 20,
          fontSize: 10.5,
          mb: 1,
          bgcolor: 'color-mix(in srgb, var(--pt-primary) 16%, transparent)',
          color: 'var(--pt-primary)',
        }}
      />
      <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 0.5 }}>Ver todas as minhas compras</Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 1.5 }}>
        Opcional: crie ou entre na sua conta PegaTicket e acompanhe, num só lugar, as compras de todas as lojas que
        você confirmar que são suas.
      </Typography>

      {linkError && (
        <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 1.5 }}>
          {linkError}
        </Alert>
      )}

      <Button
        onClick={handleClick}
        disabled={isLinking}
        variant="outlined"
        size="small"
        startIcon={<LinkOutlinedIcon />}
        fullWidth
      >
        {isLinking ? 'Vinculando…' : 'Ver todas as minhas compras'}
      </Button>
    </Paper>
  )
}

/**
 * Avaliação de venda entregue (Delivery Fase 4) — só aparece quando
 * `sale.is_delivered` E o `FinalCustomer` está autenticado no portal. Sem
 * endpoint de "já avaliei" separado: depois do envio, esconde o formulário e
 * mostra a nota enviada só para esta sessão (estado local, não persistido).
 */
function SaleRatingSection({ saleUuid }: { saleUuid: string }) {
  const [ratingValue, setRatingValue] = useState<number | null>(null)
  const [comment, setComment] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState<SaleRatingResult | null>(null)
  const [alreadyRated, setAlreadyRated] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!ratingValue) return

    setErrorMessage(null)
    setIsSubmitting(true)
    try {
      const result = await rateSale(saleUuid, { rating: ratingValue, comment: comment.trim() || undefined })
      setSubmitted(result)
    } catch (error) {
      if (error instanceof ApiRequestError && error.code === 'ORDER_ALREADY_RATED') {
        setAlreadyRated(true)
      } else if (error instanceof ApiRequestError && error.code === 'INVALID_ORDER_STATE') {
        setErrorMessage(getApiErrorMessage(error, 'Esta compra ainda não pode ser avaliada.'))
      } else {
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível enviar sua avaliação agora. Tente novamente.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (alreadyRated) {
    return (
      <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
        <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>Você já avaliou esta compra anteriormente.</Typography>
      </Paper>
    )
  }

  if (submitted) {
    return (
      <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
        <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 0.75 }}>Obrigado pela sua avaliação!</Typography>
        <Rating value={submitted.rating} readOnly size="small" />
        {submitted.comment && (
          <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.75, wordBreak: 'break-word' }}>
            "{submitted.comment}"
          </Typography>
        )}
      </Paper>
    )
  }

  return (
    <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
      <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 0.5 }}>Avalie sua compra</Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 1.5 }}>
        Conte pra gente como foi sua experiência com esta loja.
      </Typography>

      {errorMessage && (
        <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 1.5 }}>
          {errorMessage}
        </Alert>
      )}

      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <Stack spacing={1.5}>
          <Rating
            value={ratingValue}
            onChange={(_, value) => setRatingValue(value)}
            size="large"
          />
          <TextField
            label="Comentário (opcional)"
            value={comment}
            onChange={(event) => setComment(event.target.value.slice(0, 500))}
            multiline
            minRows={2}
            fullWidth
            size="small"
          />
          <Button type="submit" variant="contained" disabled={!ratingValue || isSubmitting} sx={{ alignSelf: 'flex-start' }}>
            {isSubmitting ? 'Enviando…' : 'Enviar avaliação'}
          </Button>
        </Stack>
      </Box>
    </Paper>
  )
}

function TicketCard({ ticket }: { ticket: PortalTicket }) {
  return (
    <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX, textAlign: 'center' }}>
      <Box
        sx={{
          display: 'inline-flex',
          p: 1.25,
          bgcolor: '#fff',
          borderRadius: 'var(--pt-radius-md)',
          mb: 1.5,
        }}
      >
        <QRCodeCanvas value={ticket.qr_token} size={148} marginSize={0} />
      </Box>

      {ticket.event?.name && (
        <Typography sx={{ fontSize: 15, fontWeight: 700, wordBreak: 'break-word' }}>{ticket.event.name}</Typography>
      )}
      {ticket.ticket_type?.name && (
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>{ticket.ticket_type.name}</Typography>
      )}
      {ticket.session?.name && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{ticket.session.name}</Typography>
      )}
      {ticket.seat && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 0.5, mt: 0.25 }}>
          <EventSeatOutlinedIcon sx={{ fontSize: 15 }} />
          {ticket.seat.label}
          {ticket.seat.sector_name ? ` — ${ticket.seat.sector_name}` : ''}
        </Typography>
      )}
      {ticket.attendee_name && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mt: 0.5 }}>{ticket.attendee_name}</Typography>
      )}

      <Divider sx={{ my: 1.25 }} />
      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Código: {ticket.code}</Typography>
    </Paper>
  )
}

/**
 * "Meus ingressos" — só busca quando o comprador está autenticado no Portal
 * (o endpoint `GET /portal/sales/{uuid}/tickets` exige posse via
 * `portal_customer()`, indisponível na rota pública de rastreio sem login).
 * Falha (ex.: pedido ainda não vinculado à conta) não bloqueia o resto da
 * tela de rastreio — a seção some silenciosamente, sem alerta de erro.
 */
function TicketsSection({ saleUuid }: { saleUuid: string }) {
  const [tickets, setTickets] = useState<PortalTicket[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    setIsLoading(true)
    listSaleTickets(saleUuid)
      .then((result) => {
        if (!cancelled) setTickets(result)
      })
      .catch(() => {
        if (!cancelled) setTickets(null)
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [saleUuid])

  if (isLoading) {
    return (
      <Stack spacing={1.5}>
        <Skeleton variant="rounded" height={20} sx={{ width: '40%' }} />
        <Skeleton variant="rounded" height={260} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
      </Stack>
    )
  }

  if (!tickets || tickets.length === 0) return null

  return (
    <Box>
      <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 1, display: 'flex', alignItems: 'center', gap: 0.75 }}>
        <ConfirmationNumberOutlinedIcon sx={{ fontSize: 18 }} />
        Meus ingressos
      </Typography>
      <Stack spacing={1.5}>
        {tickets.map((ticket) => (
          <TicketCard key={ticket.uuid} ticket={ticket} />
        ))}
      </Stack>
    </Box>
  )
}

export function SaleTrackingPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const { isAuthenticated: isPortalAuthenticated } = usePortalAuth()

  const [order, setOrder] = useState<SaleTracking | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const REFRESH_INTERVAL_MS = 30000

  useEffect(() => {
    if (!uuid) {
      setErrorMessage('Link de rastreio inválido.')
      setIsLoading(false)
      return
    }

    let cancelled = false

    function fetchTracking(showLoading: boolean) {
      if (showLoading) setIsLoading(true)
      setErrorMessage(null)

      getSaleTracking(uuid!)
        .then((result) => {
          if (!cancelled) setOrder(result)
        })
        .catch((error) => {
          if (cancelled) return
          // Erro vindo da API (ex.: 404) já traz mensagem tratada por
          // `getApiErrorMessage`. Qualquer outra falha (rede, CORS, timeout)
          // é um `Error` genérico do axios (ex.: "Network Error") — nunca
          // repassar esse texto técnico pro cliente final, mostrar mensagem
          // fixa amigável em vez disso.
          setErrorMessage(
            error instanceof ApiRequestError
              ? getApiErrorMessage(error, 'Venda não encontrada ou link inválido.')
              : 'Não foi possível carregar os dados da compra agora. Verifique sua conexão e tente novamente.',
          )
        })
        .finally(() => {
          if (!cancelled && showLoading) setIsLoading(false)
        })
    }

    fetchTracking(true)
    // Situação do pedido muda sem o cliente recarregar a página (loja
    // aprova/despacha/entrega) — atualiza sozinho a cada 30s, sem reexibir
    // o skeleton de loading (só troca os dados quando a resposta chega).
    const interval = setInterval(() => fetchTracking(false), REFRESH_INTERVAL_MS)

    return () => {
      cancelled = true
      clearInterval(interval)
    }
  }, [uuid])

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        background:
          'var(--pt-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 480 }}>
        <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', mb: 3 }}>
          <Logo variant="mark" size={38} />
          {order?.tenant_name && (
            <Box sx={{ minWidth: 0 }}>
              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', lineHeight: 1.2 }}>Compra de</Typography>
              <Typography sx={{ fontSize: 15, fontWeight: 700, lineHeight: 1.3, wordBreak: 'break-word' }}>
                {order.tenant_name}
              </Typography>
            </Box>
          )}
        </Stack>

        {isLoading && <LoadingSkeleton />}

        {!isLoading && errorMessage && !order && <NotFoundState message={errorMessage} />}

        {!isLoading && order && (
          <Stack spacing={2.5}>
            <StatusBanner sale={order} />

            <Paper
              elevation={0}
              sx={{
                p: 2,
                ...ELEVATED_SURFACE_SX,
              }}
            >
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Cliente</Typography>
              <Typography sx={{ fontSize: 15, fontWeight: 600, wordBreak: 'break-word' }}>
                {order.final_customer_name}
              </Typography>
            </Paper>

            <Box>
              <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 1 }}>Itens da compra</Typography>
              <ItemsList items={order.items} />
            </Box>

            <Paper
              elevation={0}
              sx={{
                p: 2,
                ...SOFT_PANEL_SX,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
              }}
            >
              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>Total da compra</Typography>
              <Typography sx={{ fontSize: 20, fontWeight: 700 }}>{formatCurrency(order.total_amount)}</Typography>
            </Paper>

            {order.is_installment && order.installments.length > 0 && (
              <Box>
                <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 1 }}>Parcelas</Typography>
                <InstallmentsList installments={order.installments} />
              </Box>
            )}

            {isPortalAuthenticated && order.is_paid && <TicketsSection saleUuid={order.uuid} />}

            {order.is_delivered && isPortalAuthenticated && <SaleRatingSection saleUuid={order.uuid} />}

            <LinkOrdersSection saleUuid={order.uuid} />
          </Stack>
        )}

        <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 4, mb: 1 }}>
          <Logo variant="mark" size={20} />
          <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>
            Rastreio via PegaTicket — do acesso a experiencia, tudo em movimento.
          </Typography>
        </Stack>
      </Box>
    </Box>
  )
}
