import AddIcon from '@mui/icons-material/Add'
import ArrowBackOutlinedIcon from '@mui/icons-material/ArrowBackOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import RemoveIcon from '@mui/icons-material/Remove'
import { Box, Button, IconButton, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { FloatingCheckoutBar, FLOATING_CHECKOUT_BAR_HEIGHT } from '../../components/storefront/FloatingCheckoutBar'
import { STOREFRONT_BOTTOM_NAV_HEIGHT } from '../../components/storefront/StorefrontBottomNav'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import * as storefrontService from '../../services/storefrontService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { StorefrontEvent, StorefrontEventProduct, StorefrontTicketType } from '../../types/storefront'
import { formatCurrency } from '../../utils/format'

/** Detalhe público de um evento (NOVO — não existia equivalente no catálogo de comércio) — lista `ticket_types`/`event_products` com controle de quantidade. */
export function StorefrontEventDetailPage() {
  const navigate = useNavigate()
  const { slug, eventSlug } = useParams<{ slug: string; eventSlug: string }>()
  const { totalQuantity, getQuantity, addTicketType, addEventProduct, updateQuantity } = useStorefrontCart()

  const [event, setEvent] = useState<StorefrontEvent | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

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

  function renderQuantityControl(uuid: string, onAdd: () => void, label: string) {
    const quantity = getQuantity(uuid)

    if (quantity === 0) {
      return (
        <Button variant="outlined" size="small" startIcon={<AddIcon />} onClick={onAdd} sx={{ minHeight: 44 }}>
          Adicionar
        </Button>
      )
    }

    return (
      <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
        <IconButton
          size="small"
          aria-label={`Diminuir quantidade de ${label}`}
          onClick={() => updateQuantity(uuid, quantity - 1)}
          sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
        >
          <RemoveIcon fontSize="small" />
        </IconButton>
        <Typography sx={{ fontWeight: 700, minWidth: 24, textAlign: 'center' }}>{quantity}</Typography>
        <IconButton
          size="small"
          aria-label={`Aumentar quantidade de ${label}`}
          onClick={() => updateQuantity(uuid, quantity + 1)}
          sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
        >
          <AddIcon fontSize="small" />
        </IconButton>
      </Stack>
    )
  }

  function renderTicketTypeRow(ticketType: StorefrontTicketType) {
    const isSaleClosed = ticketType.status !== 'ativo'
    return (
      <Paper key={ticketType.uuid} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{ticketType.name}</Typography>
            {ticketType.description && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{ticketType.description}</Typography>
            )}
            <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-primary)', mt: 0.25 }}>
              {formatCurrency(ticketType.price)}
            </Typography>
          </Box>
          {isSaleClosed ? (
            <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-muted)' }}>Indisponível</Typography>
          ) : (
            renderQuantityControl(ticketType.uuid, () => event && addTicketType(event, ticketType), ticketType.name)
          )}
        </Stack>
      </Paper>
    )
  }

  function renderEventProductRow(eventProduct: StorefrontEventProduct) {
    const isSaleClosed = eventProduct.status !== 'ativo'
    return (
      <Paper key={eventProduct.uuid} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700, fontSize: 14.5 }}>{eventProduct.name}</Typography>
            {eventProduct.description && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{eventProduct.description}</Typography>
            )}
            <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-primary)', mt: 0.25 }}>
              {formatCurrency(eventProduct.price)}
            </Typography>
          </Box>
          {isSaleClosed ? (
            <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-muted)' }}>Indisponível</Typography>
          ) : (
            renderQuantityControl(eventProduct.uuid, () => event && addEventProduct(event, eventProduct), eventProduct.name)
          )}
        </Stack>
      </Paper>
    )
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
          onClick={() => navigate(`/loja/${slug}`)}
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
            </Box>

            {event.description_full && (
              <Typography sx={{ fontSize: 14, color: 'var(--pt-text)', whiteSpace: 'pre-wrap' }}>
                {event.description_full}
              </Typography>
            )}

            {(event.ticket_types?.length ?? 0) > 0 && (
              <Stack spacing={1.5}>
                <Typography sx={{ fontWeight: 700 }}>Ingressos</Typography>
                <Stack spacing={1}>{event.ticket_types!.map(renderTicketTypeRow)}</Stack>
              </Stack>
            )}

            {(event.event_products?.length ?? 0) > 0 && (
              <Stack spacing={1.5}>
                <Typography sx={{ fontWeight: 700 }}>Adicionais</Typography>
                <Stack spacing={1}>{event.event_products!.map(renderEventProductRow)}</Stack>
              </Stack>
            )}

            {(event.ticket_types?.length ?? 0) === 0 && (
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
