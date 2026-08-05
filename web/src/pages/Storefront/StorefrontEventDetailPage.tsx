import AccessibleOutlinedIcon from '@mui/icons-material/AccessibleOutlined'
import AddIcon from '@mui/icons-material/Add'
import ArrowBackOutlinedIcon from '@mui/icons-material/ArrowBackOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import RemoveIcon from '@mui/icons-material/Remove'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import ZoomInIcon from '@mui/icons-material/ZoomIn'
import ZoomOutIcon from '@mui/icons-material/ZoomOut'
import { Alert, Box, Button, Chip, IconButton, Paper, Skeleton, Stack, TextField, Tooltip, Typography } from '@mui/material'
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { FloatingCheckoutBar, FLOATING_CHECKOUT_BAR_HEIGHT } from '../../components/storefront/FloatingCheckoutBar'
import { STOREFRONT_BOTTOM_NAV_HEIGHT } from '../../components/storefront/StorefrontBottomNav'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import * as storefrontHoldService from '../../services/storefrontHoldService'
import * as storefrontService from '../../services/storefrontService'
import * as ticketWaitlistService from '../../services/ticketWaitlistService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type {
  StorefrontAvailabilityEventProduct,
  StorefrontAvailabilityResult,
  StorefrontAvailabilitySeat,
  StorefrontAvailabilitySession,
  StorefrontAvailabilityTicketType,
  StorefrontEvent,
  StorefrontEventProduct,
  StorefrontQueueStatus,
  StorefrontTicketType,
} from '../../types/storefront'
import { formatCurrency } from '../../utils/format'

/** Fila virtual (roadmap Fase 7) — mesmo espírito de polling do OperationSnapshotCard (dashboard operacional). */
const QUEUE_POLL_INTERVAL_MS = 15_000

/**
 * CTA de lista de espera (roadmap inventário) — aparece só quando o tipo de
 * ingresso está esgotado (`available_quantity === 0`). Formulário curto
 * (nome + e-mail), mesmo padrão anti-bot de GuestInvitePage (honeypot +
 * tempo mínimo de preenchimento). Estado próprio por linha, não acopla ao
 * carrinho/estado da página inteira.
 */
function TicketTypeWaitlistCta({ slug, ticketTypeUuid }: { slug: string; ticketTypeUuid: string }) {
  const [isOpen, setIsOpen] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [website, setWebsite] = useState('')
  const formRenderedAtRef = useRef(new Date().toISOString())
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [isSubmitted, setIsSubmitted] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!name.trim() || !email.trim()) return

    setSubmitError(null)
    setIsSubmitting(true)
    try {
      await ticketWaitlistService.joinTicketTypeWaitlist(slug, {
        ticket_type_uuid: ticketTypeUuid,
        name: name.trim(),
        email: email.trim(),
        website,
        form_rendered_at: formRenderedAtRef.current,
      })
      setIsSubmitted(true)
    } catch (error) {
      setSubmitError(getApiErrorMessage(error, 'Não foi possível entrar na lista de espera agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isSubmitted) {
    return (
      <Typography sx={{ fontSize: 12, fontWeight: 600, color: 'var(--pt-success)', mt: 0.5 }}>
        Você entrou na lista de espera. Avisaremos por e-mail se houver vaga.
      </Typography>
    )
  }

  if (!isOpen) {
    return (
      <Button
        size="small"
        variant="text"
        onClick={() => setIsOpen(true)}
        sx={{ fontSize: 12, fontWeight: 600, textTransform: 'none', px: 0, minHeight: 44, justifyContent: 'flex-start' }}
      >
        Avise-me quando tiver vaga
      </Button>
    )
  }

  return (
    <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate sx={{ mt: 1 }}>
      {/* Honeypot anti-bot — invisível e fora da navegação por teclado/leitor de tela. */}
      <TextField
        label="Website"
        name="website"
        value={website}
        onChange={(event) => setWebsite(event.target.value)}
        tabIndex={-1}
        autoComplete="off"
        aria-hidden="true"
        sx={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0, overflow: 'hidden' }}
      />
      <Stack spacing={1}>
        {submitError && (
          <Alert severity="error" variant="outlined" sx={{ fontSize: 12 }}>
            {submitError}
          </Alert>
        )}
        <TextField
          size="small"
          label="Nome"
          value={name}
          onChange={(event) => setName(event.target.value)}
          fullWidth
          required
        />
        <TextField
          size="small"
          label="E-mail"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          fullWidth
          required
        />
        <Stack direction="row" spacing={1}>
          <Button
            type="submit"
            size="small"
            variant="contained"
            disabled={isSubmitting}
            sx={{ minHeight: 44 }}
          >
            {isSubmitting ? 'Enviando…' : 'Entrar na lista'}
          </Button>
          <Button size="small" variant="text" onClick={() => setIsOpen(false)} disabled={isSubmitting} sx={{ minHeight: 44 }}>
            Cancelar
          </Button>
        </Stack>
      </Stack>
    </Box>
  )
}

/** Detalhe público de um evento (NOVO — não existia equivalente no catálogo de comércio) — lista `ticket_types`/`event_products` com controle de quantidade. */
export function StorefrontEventDetailPage() {
  const navigate = useNavigate()
  const { slug, eventSlug } = useParams<{ slug: string; eventSlug: string }>()
  const { items, totalQuantity, addTicketType, addAutoSeatSelection, addEventProduct, updateQuantity, sessionId } = useStorefrontCart()

  const [event, setEvent] = useState<StorefrontEvent | null>(null)
  const [availability, setAvailability] = useState<StorefrontAvailabilityResult | null>(null)
  const [queueStatus, setQueueStatus] = useState<StorefrontQueueStatus | null>(null)
  const [selectedSessionUuid, setSelectedSessionUuid] = useState<string | null>(null)
  const [selectedSeatTicketTypeUuid, setSelectedSeatTicketTypeUuid] = useState<string | null>(null)
  const [selectedSeatSector, setSelectedSeatSector] = useState<string>('all')
  const [autoSeatQuantity, setAutoSeatQuantity] = useState(1)
  const [seatAvailabilityFilter, setSeatAvailabilityFilter] = useState<'all' | 'available'>('all')
  const [accessibleSeatsOnly, setAccessibleSeatsOnly] = useState(false)
  const [mapZoom, setMapZoom] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [isLoadingAvailability, setIsLoadingAvailability] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [availabilityError, setAvailabilityError] = useState<string | null>(null)

  const eventCartItems = useMemo(
    () => items.filter((item) => item.event_slug === eventSlug),
    [items, eventSlug],
  )
  const eventCartSessionUuids = useMemo(
    () => Array.from(new Set(eventCartItems.map((item) => item.session_uuid?.trim()).filter(Boolean))),
    [eventCartItems],
  )
  const cartHasMixedSessions = eventCartSessionUuids.length > 1
  const lockedCartSessionUuid = eventCartSessionUuids.length === 1 ? eventCartSessionUuids[0] ?? null : null
  const selectedSession = useMemo(
    () => availability?.sessions.find((session) => session.uuid === selectedSessionUuid) ?? null,
    [availability?.sessions, selectedSessionUuid],
  )
  const displayedTicketTypes = useMemo(
    () => availability?.ticket_types ?? event?.ticket_types ?? [],
    [availability?.ticket_types, event?.ticket_types],
  )
  const displayedEventProducts = useMemo(
    () => availability?.event_products ?? event?.event_products ?? [],
    [availability?.event_products, event?.event_products],
  )
  const seatRequiredTicketTypes = displayedTicketTypes.filter(
    (ticketType): ticketType is StorefrontAvailabilityTicketType =>
      'requires_seat_selection' in ticketType && ticketType.requires_seat_selection,
  )
  const simpleTicketTypes = displayedTicketTypes.filter(
    (ticketType) => !('requires_seat_selection' in ticketType) || !ticketType.requires_seat_selection,
  )
  const selectedSeatTicketType = seatRequiredTicketTypes.find((ticketType) => ticketType.uuid === selectedSeatTicketTypeUuid) ?? null
  const displayedSeats = useMemo(() => availability?.seats ?? [], [availability?.seats])
  const venueMap = availability?.event.venue ?? null
  const seatSectorOptions = useMemo(
    () => Array.from(new Set(displayedSeats.map((seat) => seat.sector_name?.trim()).filter(Boolean))),
    [displayedSeats],
  )
  const filteredDisplayedSeats = useMemo(
    () =>
      displayedSeats.filter((seat) => {
        if (selectedSeatSector !== 'all' && (seat.sector_name?.trim() ?? '') !== selectedSeatSector) {
          return false
        }

        if (seatAvailabilityFilter === 'available' && seat.availability_status !== 'disponivel') {
          return false
        }

        if (accessibleSeatsOnly && !seat.is_accessible) {
          return false
        }

        return true
      }),
    [displayedSeats, selectedSeatSector, seatAvailabilityFilter, accessibleSeatsOnly],
  )
  const autoSectorAvailableCount = useMemo(
    () =>
      selectedSeatSector === 'all'
        ? 0
        : displayedSeats.filter(
            (seat) =>
              (seat.sector_name?.trim() ?? '') === selectedSeatSector &&
              seat.kind === 'assento' &&
              seat.availability_status === 'disponivel',
          ).length,
    [displayedSeats, selectedSeatSector],
  )
  const canRenderSeatMap = Boolean(
    venueMap &&
      venueMap.width &&
      venueMap.height &&
      filteredDisplayedSeats.some((seat) => seat.pos_x !== null && seat.pos_y !== null),
  )
  const mapCanvasWidth = venueMap?.width ? Math.max(320, Math.round(venueMap.width * mapZoom)) : 320
  const mapCanvasHeight = venueMap?.height ? Math.max(220, Math.round(venueMap.height * mapZoom)) : 220

  useEffect(() => {
    if (!slug || !eventSlug) return
    let cancelled = false
    setIsLoading(true)
    setLoadError(null)
    storefrontService
      .getStorefrontEvent(slug, eventSlug)
      .then((result) => {
        if (!cancelled) setEvent(result)
      })
      .catch((error: unknown) => {
        if (!cancelled) setLoadError(getApiErrorMessage(error, 'Não foi possível carregar este evento agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug, eventSlug])

  useEffect(() => {
    if (!slug || !eventSlug) return
    let cancelled = false

    setIsLoadingAvailability(true)
    setAvailabilityError(null)

    storefrontHoldService
      .getStorefrontAvailability(slug, eventSlug, selectedSessionUuid ?? undefined)
      .then((result) => {
        if (cancelled) return
        setAvailability(result)

        if (!result.requires_session_selection) {
          setSelectedSessionUuid(null)
          return
        }

        const availableSessionUuids = result.sessions.map((session) => session.uuid)
        const preferredSessionUuid =
          lockedCartSessionUuid && availableSessionUuids.includes(lockedCartSessionUuid)
            ? lockedCartSessionUuid
            : result.selected_session_uuid ??
              (availableSessionUuids.length === 1 ? availableSessionUuids[0] : null)

        if (preferredSessionUuid !== selectedSessionUuid) {
          setSelectedSessionUuid(preferredSessionUuid)
        }
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setAvailabilityError(getApiErrorMessage(error, 'Não foi possível consultar a disponibilidade agora.'))
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoadingAvailability(false)
      })

    return () => {
      cancelled = true
    }
  }, [slug, eventSlug, selectedSessionUuid, lockedCartSessionUuid])

  const highDemandMode = availability?.event.high_demand_mode ?? false

  // Fila virtual para alta demanda (roadmap Fase 7) — só entra em ação
  // quando a disponibilidade já confirmou `high_demand_mode=true` para
  // este evento; eventos comuns nunca chamam este endpoint.
  useEffect(() => {
    if (!slug || !eventSlug || !highDemandMode) {
      setQueueStatus(null)
      return
    }

    let cancelled = false

    function poll() {
      if (!slug || !eventSlug) return
      storefrontHoldService
        .getQueueStatus(slug, eventSlug, sessionId)
        .then((result) => {
          if (!cancelled) setQueueStatus(result)
        })
        .catch(() => undefined)
    }

    poll()
    const interval = window.setInterval(poll, QUEUE_POLL_INTERVAL_MS)

    return () => {
      cancelled = true
      window.clearInterval(interval)
    }
  }, [slug, eventSlug, highDemandMode, sessionId])

  const canPurchase = !highDemandMode || queueStatus?.status === 'admitted'

  useEffect(() => {
    if (seatRequiredTicketTypes.length === 0) {
      setSelectedSeatTicketTypeUuid(null)
      return
    }

    const firstAvailableUuid = seatRequiredTicketTypes[0]?.uuid ?? null

    setSelectedSeatTicketTypeUuid((current) => {
      if (current && seatRequiredTicketTypes.some((ticketType) => ticketType.uuid === current)) {
        return current
      }

      return firstAvailableUuid
    })
  }, [seatRequiredTicketTypes])

  useEffect(() => {
    setAutoSeatQuantity(1)
  }, [selectedSeatSector, selectedSeatTicketTypeUuid])

  useEffect(() => {
    setSelectedSeatSector('all')
    setSeatAvailabilityFilter('all')
    setMapZoom(1)
  }, [selectedSeatTicketTypeUuid, selectedSessionUuid])

  function findCartItem(itemUuid: string, kind: 'ticket_type' | 'event_product', seatUuid?: string | null) {
    return eventCartItems.find((item) =>
      kind === 'ticket_type'
        ? item.ticket_type_uuid === itemUuid &&
          (item.session_uuid ?? null) === (selectedSessionUuid ?? null) &&
          (item.seat_uuid ?? null) === (seatUuid ?? null)
        : item.event_product_uuid === itemUuid &&
          (item.session_uuid ?? null) === (selectedSessionUuid ?? null) &&
          (item.seat_uuid ?? null) === (seatUuid ?? null),
    )
  }

  function renderQuantityControl(
    itemUuid: string,
    kind: 'ticket_type' | 'event_product',
    onAdd: () => void,
    label: string,
    disabled = false,
    seatUuid?: string | null,
    maxQuantity?: number | null,
  ) {
    const cartItem = findCartItem(itemUuid, kind, seatUuid)
    const quantity = cartItem?.quantity ?? 0
    const reachedMax = maxQuantity !== null && maxQuantity !== undefined && quantity >= maxQuantity

    if (quantity === 0) {
      return (
        <Button variant="outlined" size="small" startIcon={<AddIcon />} onClick={onAdd} sx={{ minHeight: 44 }} disabled={disabled}>
          Adicionar
        </Button>
      )
    }

    return (
      <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
        <IconButton
          size="small"
          aria-label={`Diminuir quantidade de ${label}`}
          onClick={() => cartItem && updateQuantity(cartItem.id, quantity - 1)}
          sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
          disabled={disabled}
        >
          <RemoveIcon fontSize="small" />
        </IconButton>
        <Typography sx={{ fontWeight: 700, minWidth: 24, textAlign: 'center' }}>{quantity}</Typography>
        <IconButton
          size="small"
          aria-label={`Aumentar quantidade de ${label}`}
          onClick={() => cartItem && updateQuantity(cartItem.id, quantity + 1)}
          sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
          disabled={disabled || reachedMax}
        >
          <AddIcon fontSize="small" />
        </IconButton>
      </Stack>
    )
  }

  function isExclusiveSeat(seat: StorefrontAvailabilitySeat) {
    return seat.kind === 'mesa' || seat.kind === 'camarote'
  }

  function isSharedCapacitySeat(seat: StorefrontAvailabilitySeat, seatCapacity: number) {
    return seat.kind === 'area' && seatCapacity > 1
  }

  function getSeatSelectionState(seat: StorefrontAvailabilitySeat, ticketType: StorefrontAvailabilityTicketType) {
    const cartItem = findCartItem(ticketType.uuid, 'ticket_type', seat.uuid)
    const currentQuantity = cartItem?.quantity ?? 0
    const seatCapacity = Math.max(1, seat.capacity ?? 1)
    const serverAvailableQuantity = Math.max(0, seat.available_quantity)
    const maxSelectableQuantity = Math.min(seatCapacity, Math.max(currentQuantity, serverAvailableQuantity))
    const isExclusive = isExclusiveSeat(seat)
    const isShared = isSharedCapacitySeat(seat, seatCapacity)
    const isSingleUnitSeat = seat.kind === 'assento' || seatCapacity === 1

    return {
      cartItem,
      currentQuantity,
      seatCapacity,
      serverAvailableQuantity,
      maxSelectableQuantity,
      isExclusive,
      isShared,
      isSingleUnitSeat,
    }
  }

  function renderTicketTypeRow(
    ticketType: StorefrontTicketType | StorefrontAvailabilityTicketType,
    options?: { fromAvailability?: boolean; session?: StorefrontAvailabilitySession | null },
  ) {
    const isSaleClosed =
      'status' in ticketType ? ticketType.status !== 'ativo' : ('available_quantity' in ticketType ? ticketType.available_quantity <= 0 : false)
    const price = 'effective_price' in ticketType ? ticketType.effective_price : ticketType.price
    const isDisabled =
      cartHasMixedSessions ||
      (availability?.requires_session_selection && !selectedSessionUuid) ||
      isLoadingAvailability ||
      !canPurchase
    return (
      <Paper key={ticketType.uuid} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{ticketType.name}</Typography>
            {ticketType.description && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{ticketType.description}</Typography>
            )}
            <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-primary)', mt: 0.25 }}>
              {formatCurrency(price)}
            </Typography>
            {'available_quantity' in ticketType && (
              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>
                {ticketType.available_quantity > 0 ? `${ticketType.available_quantity} disponível(is)` : 'Esgotado'}
              </Typography>
            )}
            {'available_quantity' in ticketType && ticketType.available_quantity <= 0 && slug && (
              <TicketTypeWaitlistCta slug={slug} ticketTypeUuid={ticketType.uuid} />
            )}
          </Box>
          {isSaleClosed ? (
            <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-muted)' }}>Indisponível</Typography>
          ) : (
            renderQuantityControl(
              ticketType.uuid,
              'ticket_type',
              () =>
                event &&
                addTicketType(
                  event,
                  {
                    ...ticketType,
                    price,
                    image_url: 'image_url' in ticketType ? ticketType.image_url : null,
                    quantity_available:
                      'available_quantity' in ticketType ? ticketType.available_quantity : ticketType.quantity_available,
                    status: 'status' in ticketType ? ticketType.status : 'ativo',
                  } as StorefrontTicketType,
                  1,
                  options?.session ? { uuid: options.session.uuid, name: options.session.name } : null,
                ),
              ticketType.name,
              isDisabled,
              null,
            )
          )}
        </Stack>
      </Paper>
    )
  }

  function renderEventProductRow(
    eventProduct: StorefrontEventProduct | StorefrontAvailabilityEventProduct,
    session?: StorefrontAvailabilitySession | null,
  ) {
    const isSaleClosed =
      'status' in eventProduct ? eventProduct.status !== 'ativo' : ('available_quantity' in eventProduct ? eventProduct.available_quantity <= 0 : false)
    const price = eventProduct.price
    const isDisabled =
      cartHasMixedSessions ||
      (availability?.requires_session_selection && !selectedSessionUuid) ||
      isLoadingAvailability ||
      !canPurchase
    return (
      <Paper key={eventProduct.uuid} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{eventProduct.name}</Typography>
            {eventProduct.description && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{eventProduct.description}</Typography>
            )}
            <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-primary)', mt: 0.25 }}>
              {formatCurrency(price)}
            </Typography>
            {'available_quantity' in eventProduct && (
              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>
                {eventProduct.available_quantity > 0
                  ? `${eventProduct.available_quantity} disponível(is)`
                  : 'Sem disponibilidade no momento'}
              </Typography>
            )}
          </Box>
          {isSaleClosed ? (
            <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-muted)' }}>Indisponível</Typography>
          ) : (
            renderQuantityControl(
              eventProduct.uuid,
              'event_product',
              () =>
                event &&
                addEventProduct(
                  event,
                  {
                    ...eventProduct,
                    price,
                    quantity_available:
                      'available_quantity' in eventProduct ? eventProduct.available_quantity : eventProduct.quantity_available,
                    requires_plate: 'requires_plate' in eventProduct ? eventProduct.requires_plate : false,
                    requires_model: 'requires_model' in eventProduct ? eventProduct.requires_model : false,
                    requires_color: 'requires_color' in eventProduct ? eventProduct.requires_color : false,
                    status: 'status' in eventProduct ? eventProduct.status : 'ativo',
                  } as StorefrontEventProduct,
                  1,
                  session ? { uuid: session.uuid, name: session.name } : null,
                ),
              eventProduct.name,
              isDisabled,
              null,
            )
          )}
        </Stack>
      </Paper>
    )
  }

  function renderSeatRow(seat: StorefrontAvailabilitySeat, ticketType: StorefrontAvailabilityTicketType) {
    const { cartItem, currentQuantity, seatCapacity, serverAvailableQuantity, maxSelectableQuantity, isSingleUnitSeat, isExclusive, isShared } =
      getSeatSelectionState(seat, ticketType)
    const isSelected = currentQuantity > 0
    const isAvailable = serverAvailableQuantity > 0
    const isDisabled =
      cartHasMixedSessions ||
      isLoadingAvailability ||
      !isAvailable ||
      !canPurchase ||
      (availability?.requires_session_selection && !selectedSessionUuid)

    return (
      <Paper
        key={seat.uuid}
        variant="outlined"
        sx={{
          ...SOFT_PANEL_SX,
          p: 1.25,
          borderColor: isSelected ? 'var(--pt-primary)' : 'var(--pt-border)',
          opacity: isDisabled && !isSelected ? 0.6 : 1,
        }}
      >
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ minWidth: 0 }}>
            <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
              <Typography sx={{ fontWeight: 700, fontSize: 14 }}>{seat.label}</Typography>
              {seat.is_accessible && (
                <Tooltip title="Lugar acessível" arrow>
                  <AccessibleOutlinedIcon fontSize="small" sx={{ color: 'var(--pt-primary)' }} />
                </Tooltip>
              )}
            </Stack>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
              {[seat.sector_name, seat.kind].filter(Boolean).join(' • ') || 'Lugar marcado'}
            </Typography>
            <Typography sx={{ fontSize: 12, color: isAvailable ? 'var(--pt-muted)' : 'var(--pt-danger)', mt: 0.25 }}>
              {isSelected
                ? isSingleUnitSeat
                  ? 'Selecionado no carrinho'
                  : isExclusive
                    ? `Reserva exclusiva para ${seatCapacity} pessoa(s)`
                    : `${currentQuantity} de ${seatCapacity} vaga(s) selecionada(s)`
                : seat.availability_status === 'reservado'
                  ? 'Reservado neste momento'
                  : seat.availability_status === 'indisponivel'
                    ? 'Indisponível'
                    : isSingleUnitSeat
                      ? 'Disponível'
                      : isExclusive
                        ? `Reserva exclusiva para ${seatCapacity} pessoa(s)`
                        : `${serverAvailableQuantity} de ${seatCapacity} vaga(s) disponível(is)`}
            </Typography>
          </Box>

          {isSelected && isSingleUnitSeat ? (
            <Button variant="contained" color="inherit" onClick={() => updateQuantity(cartItem!.id, 0)} sx={{ minHeight: 44 }}>
              Remover
            </Button>
          ) : isSelected && isExclusive ? (
            <Button variant="contained" color="inherit" onClick={() => updateQuantity(cartItem!.id, 0)} sx={{ minHeight: 44 }}>
              Remover
            </Button>
          ) : isSelected ? (
            renderQuantityControl(
              ticketType.uuid,
              'ticket_type',
              () => undefined,
              `${ticketType.name} - ${seat.label}`,
              isDisabled,
              seat.uuid,
              maxSelectableQuantity,
            )
          ) : (
            <Button
              variant="outlined"
              onClick={() =>
                event &&
                addTicketType(
                  event,
                  {
                    uuid: ticketType.uuid,
                    name: ticketType.name,
                    description: ticketType.description,
                    price: ticketType.effective_price,
                    image_url: null,
                    quantity_available: ticketType.available_quantity,
                    min_per_order: ticketType.min_per_order,
                    max_per_order: ticketType.max_per_order,
                    sales_start_at: ticketType.sales_start_at,
                    sales_end_at: ticketType.sales_end_at,
                    status: 'ativo',
                  },
                  isExclusive ? seatCapacity : 1,
                  selectedSession ? { uuid: selectedSession.uuid, name: selectedSession.name } : null,
                  {
                    uuid: seat.uuid,
                    label: seat.label,
                    sector_name: seat.sector_name,
                    kind: seat.kind,
                    capacity: seat.capacity,
                  },
                )
              }
              disabled={isDisabled}
              sx={{ minHeight: 44 }}
            >
              {isSingleUnitSeat ? 'Selecionar' : isExclusive ? 'Reservar completo' : isShared ? 'Adicionar vagas' : 'Selecionar'}
            </Button>
          )}
        </Stack>
      </Paper>
    )
  }

  function renderSeatMapNode(seat: StorefrontAvailabilitySeat, ticketType: StorefrontAvailabilityTicketType) {
    if (!venueMap?.width || !venueMap?.height || seat.pos_x === null || seat.pos_y === null) {
      return null
    }

    const { cartItem, currentQuantity, seatCapacity, serverAvailableQuantity, maxSelectableQuantity, isSingleUnitSeat, isExclusive } =
      getSeatSelectionState(seat, ticketType)
    const isSelected = currentQuantity > 0
    const isAvailable = serverAvailableQuantity > 0
    const isDisabled =
      cartHasMixedSessions ||
      isLoadingAvailability ||
      !isAvailable ||
      !canPurchase ||
      (availability?.requires_session_selection && !selectedSessionUuid)

    const left = `${(seat.pos_x / venueMap.width) * 100}%`
    const top = `${(seat.pos_y / venueMap.height) * 100}%`
    const size = seat.kind === 'mesa' || seat.kind === 'camarote' ? 34 : 26

    let background = 'var(--pt-surface-raised-bg)'
    let color = 'var(--pt-text)'
    let border = '1px solid var(--pt-border)'

    if (isSelected) {
      background = 'var(--pt-primary)'
      color = '#fff'
      border = '1px solid var(--pt-primary)'
    } else if (seat.availability_status === 'reservado') {
      background = 'rgba(245, 158, 11, 0.14)'
      color = '#b45309'
      border = '1px solid rgba(245, 158, 11, 0.45)'
    } else if (seat.availability_status === 'indisponivel') {
      background = 'rgba(239, 68, 68, 0.12)'
      color = '#b91c1c'
      border = '1px solid rgba(239, 68, 68, 0.38)'
    }

    if (seat.is_accessible && !isSelected) {
      border = '2px solid var(--pt-primary)'
    }

    return (
      <Tooltip
        key={seat.uuid}
        title={`${seat.label}${seat.sector_name ? ` • ${seat.sector_name}` : ''}${seat.is_accessible ? ' • acessível' : ''}`}
        arrow
        placement="top"
      >
        <Box
          component="button"
          type="button"
          onClick={() => {
            if (isSelected && (isSingleUnitSeat || isExclusive)) {
              updateQuantity(cartItem!.id, 0)
              return
            }

            if (isSelected && !isSingleUnitSeat) {
              if (cartItem && currentQuantity < maxSelectableQuantity) {
                updateQuantity(cartItem.id, currentQuantity + 1)
              }
              return
            }

            if (isDisabled || !event) return

            addTicketType(
              event,
              {
                uuid: ticketType.uuid,
                name: ticketType.name,
                description: ticketType.description,
                price: ticketType.effective_price,
                image_url: null,
                quantity_available: ticketType.available_quantity,
                min_per_order: ticketType.min_per_order,
                max_per_order: ticketType.max_per_order,
                sales_start_at: ticketType.sales_start_at,
                sales_end_at: ticketType.sales_end_at,
                status: 'ativo',
              },
              isExclusive ? seatCapacity : 1,
              selectedSession ? { uuid: selectedSession.uuid, name: selectedSession.name } : null,
              {
                uuid: seat.uuid,
                label: seat.label,
                sector_name: seat.sector_name,
                kind: seat.kind,
                capacity: seat.capacity,
              },
            )
          }}
          disabled={(isDisabled && !isSelected) || (!isSingleUnitSeat && !isExclusive && currentQuantity >= maxSelectableQuantity && isSelected)}
          sx={{
            position: 'absolute',
            left,
            top,
            transform: 'translate(-50%, -50%)',
            width: size,
            height: size,
            borderRadius: seat.kind === 'mesa' ? '30%' : '999px',
            border,
            background,
            color,
            fontSize: 10,
            fontWeight: 800,
            lineHeight: 1,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: isSelected ? '0 10px 24px rgba(15, 118, 110, 0.22)' : '0 6px 14px rgba(15, 23, 42, 0.10)',
            cursor: isDisabled && !isSelected ? 'not-allowed' : 'pointer',
            opacity: isDisabled && !isSelected ? 0.7 : 1,
            transition: 'transform 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease',
            '&:hover': {
              transform: 'translate(-50%, -50%) scale(1.08)',
            },
          }}
        >
          {isSingleUnitSeat ? seat.label.slice(0, 3).toUpperCase() : isExclusive ? 'EXC' : `${currentQuantity || serverAvailableQuantity}/${seatCapacity}`}
        </Box>
      </Tooltip>
    )
  }

  function getSeatTicketSummary(ticketType: StorefrontAvailabilityTicketType) {
    const matchingItems = eventCartItems.filter(
      (item) =>
        item.ticket_type_uuid === ticketType.uuid &&
        (item.session_uuid ?? null) === (selectedSessionUuid ?? null) &&
        Boolean(item.seat_uuid),
    )

    const selectedPlaces = matchingItems.reduce((sum, item) => sum + item.quantity, 0)
    const selectedUnits = matchingItems.length

    if (selectedPlaces <= 0) {
      return 'Escolha os lugares individualmente'
    }

    if (selectedPlaces === selectedUnits) {
      return `${selectedUnits} lugar(es) selecionado(s)`
    }

    return `${selectedPlaces} acesso(s) reservado(s) em ${selectedUnits} lugar(es)`
  }

  function zoomIn() {
    setMapZoom((current) => Math.min(2.5, Number((current + 0.25).toFixed(2))))
  }

  function zoomOut() {
    setMapZoom((current) => Math.max(1, Number((current - 0.25).toFixed(2))))
  }

  function resetMapView() {
    setMapZoom(1)
    setSelectedSeatSector('all')
    setSeatAvailabilityFilter('all')
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background: 'var(--pt-page-background)',
        pb:
          totalQuantity > 0
            ? `calc(${STOREFRONT_BOTTOM_NAV_HEIGHT}px + ${FLOATING_CHECKOUT_BAR_HEIGHT}px + env(safe-area-inset-bottom, 0px))`
            : 4,
      }}
    >
      <Box sx={{ maxWidth: 960, mx: 'auto', px: { xs: 2, sm: 3 }, py: { xs: 3, sm: 4 } }}>
        <Button
          startIcon={<ArrowBackOutlinedIcon />}
          onClick={() => navigate(`/eventos/${slug}`)}
          sx={{ mb: 2, minHeight: 44 }}
        >
          Voltar ao catálogo
        </Button>

        {isLoading && (
          <Stack spacing={2}>
            <Skeleton variant="rounded" height={220} sx={{ borderRadius: 'var(--pt-radius-lg)' }} />
            <Skeleton variant="text" width={240} height={32} />
            <Skeleton variant="rounded" height={80} />
          </Stack>
        )}

        {!isLoading && loadError && (
          <EmptyState
            icon={<EventOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
            title="Evento não encontrado"
            description={loadError}
          />
        )}

        {!isLoading && !loadError && event && (
          <Stack spacing={2.5}>
            <Box
              sx={{
                width: '100%',
                aspectRatio: '16 / 9',
                ...SOFT_PANEL_SX,
                borderRadius: 'var(--pt-radius-lg)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
              }}
            >
              {event.cover_image_url ? (
                <Box
                  component="img"
                  src={event.cover_image_url}
                  alt={event.name}
                  sx={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
              ) : (
                <EventOutlinedIcon sx={{ fontSize: 56, color: 'var(--pt-muted)' }} />
              )}
            </Box>

            <Box>
              <Typography sx={{ fontSize: { xs: 22, sm: 26 }, fontWeight: 700 }}>{event.name}</Typography>
              <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mt: 0.5 }}>
                <EventOutlinedIcon sx={{ fontSize: 17, color: 'var(--pt-muted)' }} />
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                  {new Date(event.starts_at.replace(' ', 'T')).toLocaleString('pt-BR', {
                    dateStyle: 'long',
                    timeStyle: 'short',
                  })}
                </Typography>
              </Stack>
              {event.location_name && (
                <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mt: 0.25 }}>
                  <PlaceOutlinedIcon sx={{ fontSize: 17, color: 'var(--pt-muted)' }} />
                  <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                    {event.location_name}
                    {event.location_address ? ` — ${event.location_address}` : ''}
                  </Typography>
                </Stack>
              )}
              {event.venue && (
                <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mt: 0.25 }}>
                  <PlaceOutlinedIcon sx={{ fontSize: 17, color: 'var(--pt-muted)' }} />
                  <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                    Mapa do local disponível em {event.venue.name}
                  </Typography>
                </Stack>
              )}
            </Box>

            {event.description_full && (
              <Typography sx={{ fontSize: 14, color: 'var(--pt-text)', whiteSpace: 'pre-wrap' }}>
                {event.description_full}
              </Typography>
            )}

            {cartHasMixedSessions && (
              <Alert severity="warning" variant="outlined">
                Este carrinho já tem itens de mais de uma sessão deste evento. Para evitar conflito de disponibilidade,
                finalize ou limpe uma das sessões antes de continuar.
              </Alert>
            )}

            {lockedCartSessionUuid && selectedSession && (
              <Alert severity="info" variant="outlined">
                Seu carrinho atual está vinculado à sessão "{selectedSession.name}". Os novos itens deste evento serão
                adicionados nessa mesma sessão.
              </Alert>
            )}

            {availabilityError && (
              <Alert severity="warning" variant="outlined">
                {availabilityError}
              </Alert>
            )}

            {highDemandMode && queueStatus && queueStatus.status !== 'admitted' && (
              <Alert severity="info" variant="outlined" sx={{ alignItems: 'flex-start' }}>
                <Typography sx={{ fontWeight: 700, fontSize: 14 }}>
                  Este evento está com procura muito alta agora.
                </Typography>
                <Typography sx={{ fontSize: 13.5, mt: 0.25 }}>
                  {queueStatus.status === 'expired'
                    ? 'Sua vez na fila expirou. Atualize a página para entrar novamente.'
                    : queueStatus.position
                      ? `Você está na posição ${queueStatus.position} da fila${
                          queueStatus.waiting_ahead > 0 ? ` (${queueStatus.waiting_ahead} pessoa(s) na sua frente)` : ''
                        }. Assim que for sua vez, a compra libera automaticamente aqui.`
                      : 'Aguarde, estamos organizando sua entrada na fila.'}
                </Typography>
              </Alert>
            )}

            {availability?.requires_session_selection && (
              <Stack spacing={1.25}>
                <Typography sx={{ fontWeight: 700 }}>Escolha a sessão</Typography>
                {isLoadingAvailability && availability?.sessions.length === 0 ? (
                  <Skeleton variant="rounded" height={88} sx={{ borderRadius: 'var(--pt-radius-lg)' }} />
                ) : (
                  <Stack spacing={1}>
                    {availability?.sessions.map((session) => {
                      const isSelected = session.uuid === selectedSessionUuid
                      const isLockedToAnotherSession =
                        Boolean(lockedCartSessionUuid) && lockedCartSessionUuid !== session.uuid

                      return (
                        <Paper
                          key={session.uuid}
                          variant="outlined"
                          sx={{
                            ...SOFT_PANEL_SX,
                            p: 1.5,
                            borderColor: isSelected ? 'var(--pt-primary)' : 'var(--pt-border)',
                            opacity: isLockedToAnotherSession ? 0.55 : 1,
                          }}
                        >
                          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                            <Box sx={{ minWidth: 0 }}>
                              <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{session.name}</Typography>
                              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                                {session.starts_at
                                  ? new Date(session.starts_at.replace(' ', 'T')).toLocaleString('pt-BR', {
                                      dateStyle: 'medium',
                                      timeStyle: 'short',
                                    })
                                  : 'Horário a confirmar'}
                              </Typography>
                            </Box>
                            <Button
                              variant={isSelected ? 'contained' : 'outlined'}
                              onClick={() => setSelectedSessionUuid(session.uuid)}
                              disabled={isLockedToAnotherSession || isLoadingAvailability}
                            >
                              {isSelected ? 'Selecionada' : 'Escolher'}
                            </Button>
                          </Stack>
                        </Paper>
                      )
                    })}
                  </Stack>
                )}
              </Stack>
            )}

            {simpleTicketTypes.length > 0 && (
              <Stack spacing={1.5}>
                <Typography sx={{ fontWeight: 700 }}>Ingressos</Typography>
                {availability?.requires_session_selection && !selectedSessionUuid ? (
                  <Alert severity="info" variant="outlined">
                    Selecione uma sessão para ver os ingressos disponíveis.
                  </Alert>
                ) : (
                  <Stack spacing={1}>
                    {simpleTicketTypes.map((ticketType) =>
                      renderTicketTypeRow(ticketType, { fromAvailability: Boolean(availability), session: selectedSession }),
                    )}
                  </Stack>
                )}
              </Stack>
            )}

            {seatRequiredTicketTypes.length > 0 && (
              <Stack spacing={1.5}>
                <Typography sx={{ fontWeight: 700 }}>Lugares marcados</Typography>
                {availability?.requires_session_selection && !selectedSessionUuid ? (
                  <Alert severity="info" variant="outlined">
                    Selecione uma sessão para escolher seus assentos.
                  </Alert>
                ) : (
                  <>
                    <Stack spacing={1}>
                      {seatRequiredTicketTypes.map((ticketType) => {
                        const isSelected = ticketType.uuid === selectedSeatTicketTypeUuid

                        return (
                          <Paper
                            key={ticketType.uuid}
                            variant="outlined"
                            sx={{
                              ...SOFT_PANEL_SX,
                              p: 1.5,
                              borderColor: isSelected ? 'var(--pt-primary)' : 'var(--pt-border)',
                            }}
                          >
                            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                              <Box sx={{ minWidth: 0 }}>
                                <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{ticketType.name}</Typography>
                                {ticketType.description && (
                                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{ticketType.description}</Typography>
                                )}
                                <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-primary)', mt: 0.25 }}>
                                  {formatCurrency(ticketType.effective_price)}
                                </Typography>
                                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>
                                  {getSeatTicketSummary(ticketType)}
                                </Typography>
                              </Box>
                              <Button
                                variant={isSelected ? 'contained' : 'outlined'}
                                onClick={() => setSelectedSeatTicketTypeUuid(ticketType.uuid)}
                                disabled={cartHasMixedSessions || isLoadingAvailability}
                              >
                                {isSelected ? 'Ativo' : 'Escolher lugares'}
                              </Button>
                            </Stack>
                          </Paper>
                        )
                      })}
                    </Stack>

                    {selectedSeatTicketType && (
                      <Stack spacing={1}>
                        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                          Lugares disponíveis para {selectedSeatTicketType.name}
                        </Typography>
                        <Stack spacing={1}>
                          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                            <Chip
                              label="Todos os setores"
                              clickable
                              color={selectedSeatSector === 'all' ? 'primary' : 'default'}
                              variant={selectedSeatSector === 'all' ? 'filled' : 'outlined'}
                              onClick={() => setSelectedSeatSector('all')}
                            />
                            {seatSectorOptions.map((sector) => (
                              <Chip
                                key={sector}
                                label={sector}
                                clickable
                                color={selectedSeatSector === sector ? 'primary' : 'default'}
                                variant={selectedSeatSector === sector ? 'filled' : 'outlined'}
                                onClick={() => setSelectedSeatSector(sector)}
                              />
                            ))}
                          </Stack>

                          {selectedSeatSector !== 'all' && (
                            <Paper variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.25 }}>
                              <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                                <Typography sx={{ fontSize: 13, fontWeight: 600 }}>
                                  Melhor lugar automático em {selectedSeatSector}
                                </Typography>
                                <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
                                  <IconButton
                                    size="small"
                                    onClick={() => setAutoSeatQuantity((quantity) => Math.max(1, quantity - 1))}
                                    disabled={autoSeatQuantity <= 1}
                                    sx={{ ...SOFT_PANEL_SX }}
                                  >
                                    <RemoveIcon fontSize="small" />
                                  </IconButton>
                                  <Typography sx={{ minWidth: 20, textAlign: 'center' }}>{autoSeatQuantity}</Typography>
                                  <IconButton
                                    size="small"
                                    onClick={() =>
                                      setAutoSeatQuantity((quantity) => Math.min(Math.max(1, autoSectorAvailableCount), quantity + 1))
                                    }
                                    disabled={autoSeatQuantity >= autoSectorAvailableCount}
                                    sx={{ ...SOFT_PANEL_SX }}
                                  >
                                    <AddIcon fontSize="small" />
                                  </IconButton>
                                </Stack>
                                <Button
                                  variant="contained"
                                  size="small"
                                  disabled={
                                    !event ||
                                    !selectedSeatTicketType ||
                                    autoSectorAvailableCount < autoSeatQuantity ||
                                    cartHasMixedSessions ||
                                    (availability?.requires_session_selection && !selectedSessionUuid)
                                  }
                                  onClick={() => {
                                    if (!event || !selectedSeatTicketType) return
                                    addAutoSeatSelection(
                                      event,
                                      {
                                        uuid: selectedSeatTicketType.uuid,
                                        name: selectedSeatTicketType.name,
                                        description: selectedSeatTicketType.description,
                                        price: selectedSeatTicketType.effective_price,
                                        image_url: null,
                                        quantity_available: selectedSeatTicketType.available_quantity,
                                        min_per_order: selectedSeatTicketType.min_per_order,
                                        max_per_order: selectedSeatTicketType.max_per_order,
                                        sales_start_at: selectedSeatTicketType.sales_start_at,
                                        sales_end_at: selectedSeatTicketType.sales_end_at,
                                        status: 'ativo',
                                      },
                                      autoSeatQuantity,
                                      selectedSeatSector,
                                      selectedSession ? { uuid: selectedSession.uuid, name: selectedSession.name } : null,
                                    )
                                  }}
                                  sx={{ minHeight: 36 }}
                                >
                                  Adicionar {autoSeatQuantity} lugar(es)
                                </Button>
                                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                                  {autoSectorAvailableCount} livre(s) neste setor
                                </Typography>
                              </Stack>
                            </Paper>
                          )}

                          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', alignItems: 'center' }}>
                            <Chip
                              label="Mostrar tudo"
                              clickable
                              color={seatAvailabilityFilter === 'all' ? 'primary' : 'default'}
                              variant={seatAvailabilityFilter === 'all' ? 'filled' : 'outlined'}
                              onClick={() => setSeatAvailabilityFilter('all')}
                            />
                            <Chip
                              label="Só disponíveis"
                              clickable
                              color={seatAvailabilityFilter === 'available' ? 'primary' : 'default'}
                              variant={seatAvailabilityFilter === 'available' ? 'filled' : 'outlined'}
                              onClick={() => setSeatAvailabilityFilter('available')}
                            />
                            <Chip
                              icon={<AccessibleOutlinedIcon fontSize="small" />}
                              label="Acessível"
                              clickable
                              color={accessibleSeatsOnly ? 'primary' : 'default'}
                              variant={accessibleSeatsOnly ? 'filled' : 'outlined'}
                              onClick={() => setAccessibleSeatsOnly((current) => !current)}
                            />
                            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                              {filteredDisplayedSeats.length} lugar(es) visível(is)
                            </Typography>
                          </Stack>
                        </Stack>

                        {canRenderSeatMap && venueMap ? (
                          <Paper variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.25, overflow: 'hidden' }}>
                            <Stack spacing={1.25}>
                              <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
                                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                                  Toque em um ponto do mapa para selecionar ou remover um lugar.
                                </Typography>
                                <Stack direction="row" spacing={0.5}>
                                  <Tooltip title="Diminuir zoom" arrow>
                                    <span>
                                      <IconButton size="small" onClick={zoomOut} disabled={mapZoom <= 1} sx={{ ...SOFT_PANEL_SX }}>
                                        <ZoomOutIcon fontSize="small" />
                                      </IconButton>
                                    </span>
                                  </Tooltip>
                                  <Tooltip title="Aumentar zoom" arrow>
                                    <span>
                                      <IconButton size="small" onClick={zoomIn} disabled={mapZoom >= 2.5} sx={{ ...SOFT_PANEL_SX }}>
                                        <ZoomInIcon fontSize="small" />
                                      </IconButton>
                                    </span>
                                  </Tooltip>
                                  <Tooltip title="Redefinir visualização" arrow>
                                    <span>
                                      <IconButton size="small" onClick={resetMapView} sx={{ ...SOFT_PANEL_SX }}>
                                        <RestartAltIcon fontSize="small" />
                                      </IconButton>
                                    </span>
                                  </Tooltip>
                                </Stack>
                              </Stack>

                              <Box
                                sx={{
                                  position: 'relative',
                                  width: '100%',
                                  overflow: 'auto',
                                  borderRadius: 'var(--pt-radius-lg)',
                                  border: '1px solid var(--pt-border)',
                                  background: 'rgba(15, 23, 42, 0.02)',
                                  maxHeight: { xs: 360, md: 520 },
                                }}
                              >
                                <Box
                                  sx={{
                                    position: 'relative',
                                    width: mapCanvasWidth,
                                    height: mapCanvasHeight,
                                  borderRadius: 'var(--pt-radius-lg)',
                                  background: venueMap.background_image_url
                                    ? `linear-gradient(rgba(15, 23, 42, 0.03), rgba(15, 23, 42, 0.08)), url(${venueMap.background_image_url}) center / cover no-repeat`
                                    : 'linear-gradient(135deg, rgba(15, 118, 110, 0.06), rgba(15, 23, 42, 0.04))',
                                }}
                              >
                                {!venueMap.background_image_url && (
                                  <Stack
                                    sx={{
                                      position: 'absolute',
                                      inset: 0,
                                      alignItems: 'center',
                                      justifyContent: 'center',
                                      color: 'var(--pt-muted)',
                                      pointerEvents: 'none',
                                    }}
                                  >
                                    <EventSeatOutlinedIcon sx={{ fontSize: 36 }} />
                                    <Typography sx={{ fontSize: 12.5 }}>Mapa sem imagem de fundo</Typography>
                                  </Stack>
                                )}

                                {filteredDisplayedSeats.map((seat) => renderSeatMapNode(seat, selectedSeatTicketType))}
                                </Box>
                              </Box>

                              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', color: 'var(--pt-muted)' }}>
                                <Typography sx={{ fontSize: 12 }}>Disponível</Typography>
                                <Box sx={{ width: 14, height: 14, borderRadius: 999, border: '1px solid var(--pt-border)', bgcolor: 'var(--pt-surface-raised-bg)' }} />
                                <Typography sx={{ fontSize: 12 }}>Selecionado</Typography>
                                <Box sx={{ width: 14, height: 14, borderRadius: 999, bgcolor: 'var(--pt-primary)' }} />
                                <Typography sx={{ fontSize: 12 }}>Reservado</Typography>
                                <Box sx={{ width: 14, height: 14, borderRadius: 999, border: '1px solid rgba(245, 158, 11, 0.45)', bgcolor: 'rgba(245, 158, 11, 0.14)' }} />
                                <Typography sx={{ fontSize: 12 }}>Indisponível</Typography>
                                <Box sx={{ width: 14, height: 14, borderRadius: 999, border: '1px solid rgba(239, 68, 68, 0.38)', bgcolor: 'rgba(239, 68, 68, 0.12)' }} />
                              </Stack>
                            </Stack>
                          </Paper>
                        ) : null}
                        {displayedSeats.length === 0 ? (
                          <Alert severity="warning" variant="outlined">
                            Ainda não há lugares publicados para este mapa.
                          </Alert>
                        ) : filteredDisplayedSeats.length === 0 ? (
                          <Alert severity="info" variant="outlined">
                            Nenhum lugar corresponde aos filtros atuais. Ajuste o setor ou a disponibilidade para continuar.
                          </Alert>
                        ) : (
                          <Stack spacing={1}>
                            {canRenderSeatMap ? (
                              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                                Lista de apoio para leitura rápida dos lugares.
                              </Typography>
                            ) : null}
                            {filteredDisplayedSeats.map((seat) => renderSeatRow(seat, selectedSeatTicketType))}
                          </Stack>
                        )}
                      </Stack>
                    )}
                  </>
                )}
              </Stack>
            )}

            {displayedEventProducts.length > 0 && (
              <Stack spacing={1.5}>
                <Typography sx={{ fontWeight: 700 }}>Adicionais</Typography>
                {availability?.requires_session_selection && !selectedSessionUuid ? (
                  <Alert severity="info" variant="outlined">
                    Selecione uma sessão para liberar os adicionais desta sessão.
                  </Alert>
                ) : (
                  <Stack spacing={1}>{displayedEventProducts.map((eventProduct) => renderEventProductRow(eventProduct, selectedSession))}</Stack>
                )}
              </Stack>
            )}

            {simpleTicketTypes.length === 0 &&
              seatRequiredTicketTypes.length === 0 &&
              (!availability?.requires_session_selection || Boolean(selectedSessionUuid)) && (
              <EmptyState
                icon={<EventOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
                title="Nenhum ingresso disponível"
                description="Este evento ainda não tem tipos de ingresso publicados para venda."
              />
            )}
          </Stack>
        )}
      </Box>

      {slug && totalQuantity > 0 && <FloatingCheckoutBar slug={slug} />}
    </Box>
  )
}
