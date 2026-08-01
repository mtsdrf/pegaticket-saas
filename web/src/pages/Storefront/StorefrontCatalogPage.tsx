import EmailOutlinedIcon from '@mui/icons-material/EmailOutlined'
import PhoneOutlinedIcon from '@mui/icons-material/PhoneOutlined'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import StarIcon from '@mui/icons-material/Star'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import WhatsAppIcon from '@mui/icons-material/WhatsApp'
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  InputAdornment,
  Pagination,
  Paper,
  Skeleton,
  Snackbar,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CategoryChipBar } from '../../components/storefront/CategoryChipBar'
import { EmptyState } from '../../components/layout/EmptyState'
import { EventCard } from '../../components/storefront/EventCard'
import { FloatingCheckoutBar, FLOATING_CHECKOUT_BAR_HEIGHT } from '../../components/storefront/FloatingCheckoutBar'
import { STOREFRONT_BOTTOM_NAV_HEIGHT } from '../../components/storefront/StorefrontBottomNav'
import { OtpLoginDialog } from '../../components/storefront/OtpLoginDialog'
import { Logo } from '../../components/ui/Logo'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { toggleFavorite } from '../../services/favoriteService'
import * as storefrontService from '../../services/storefrontService'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { StorefrontCategory, StorefrontEvent, StorefrontTenant } from '../../types/storefront'
import { isStoreOpenNow } from '../../utils/businessHours'

/** Badge "Aberto agora"/"Fechado no momento" + tempo estimado — calculado client-side a partir de `tenant.business_hours` (ver `utils/businessHours.ts`). */
function StoreStatusBadge({ tenant }: { tenant: StorefrontTenant }) {
  const isOpen = isStoreOpenNow(tenant.business_hours)

  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5 }}>
      <Chip
        size="small"
        label={isOpen ? 'Aberto agora' : 'Fechado no momento'}
        sx={{
          fontWeight: 700,
          fontSize: 12,
          color: isOpen ? 'var(--pt-success, #1b7a3d)' : 'var(--pt-warning, #a15c00)',
          bgcolor: isOpen ? 'color-mix(in srgb, #1b7a3d 14%, transparent)' : 'color-mix(in srgb, #a15c00 14%, transparent)',
        }}
      />
      {tenant.estimated_preparation_minutes !== null && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
          ~{tenant.estimated_preparation_minutes} min
        </Typography>
      )}
    </Stack>
  )
}

const PER_PAGE = 12

function CatalogSkeleton() {
  return (
    <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(min(45%, 220px), 1fr))', gap: 1.5 }}>
      {Array.from({ length: 6 }).map((_, index) => (
        <Skeleton key={index} variant="rounded" height={220} sx={{ borderRadius: 'var(--pt-radius-lg)' }} />
      ))}
    </Box>
  )
}

function digitsOnly(value: string) {
  return value.replace(/\D+/g, '')
}

/**
 * Catálogo público de eventos — substitui o catálogo de produtos do domínio
 * de comércio (migração PegaTicket, roadmap seção 4A). SIMPLIFICAÇÃO
 * DOCUMENTADA: a versão anterior tinha rail "Mais vendidos", ordenação por
 * preço/mais vendidos, filtro "em promoção" e alternância grid/lista — nenhum
 * desses conceitos existe no domínio de Event (sem preço de produto único,
 * sem promoção, sem histórico de vendas por item de catálogo aqui). Mantido:
 * busca por nome, categorias, paginação, favoritos.
 */
export function StorefrontCatalogPage() {
  const navigate = useNavigate()
  const { slug } = useParams<{ slug: string }>()
  const { isAuthenticated } = usePortalAuth()
  const { totalQuantity } = useStorefrontCart()

  const [tenant, setTenant] = useState<StorefrontTenant | null>(null)
  const [tenantError, setTenantError] = useState<string | null>(null)
  const [isLoadingTenant, setIsLoadingTenant] = useState(true)

  const [events, setEvents] = useState<StorefrontEvent[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [isLoadingEvents, setIsLoadingEvents] = useState(true)
  const [eventsError, setEventsError] = useState<string | null>(null)

  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebouncedValue(searchTerm, 400)

  const [categories, setCategories] = useState<StorefrontCategory[]>([])
  const [selectedCategoryUuid, setSelectedCategoryUuid] = useState<string | null>(null)

  const [otpDialogOpen, setOtpDialogOpen] = useState(false)
  const [pendingFavoriteEvent, setPendingFavoriteEvent] = useState<StorefrontEvent | null>(null)
  const [favoriteErrorMessage, setFavoriteErrorMessage] = useState<string | null>(null)

  function performFavoriteToggle(event: StorefrontEvent) {
    const applyToggle = (list: StorefrontEvent[]) =>
      list.map((item) => (item.uuid === event.uuid ? { ...item, is_favorited: !item.is_favorited } : item))

    setEvents(applyToggle)

    toggleFavorite(event.uuid).catch((error: unknown) => {
      const revertToggle = (list: StorefrontEvent[]) =>
        list.map((item) => (item.uuid === event.uuid ? { ...item, is_favorited: event.is_favorited } : item))
      setEvents(revertToggle)
      setFavoriteErrorMessage(getApiErrorMessage(error, 'Não foi possível atualizar seus favoritos agora.'))
    })
  }

  function handleToggleFavorite(event: StorefrontEvent) {
    if (!isAuthenticated) {
      setPendingFavoriteEvent(event)
      setOtpDialogOpen(true)
      return
    }
    performFavoriteToggle(event)
  }

  function handleFavoriteOtpAuthenticated() {
    setOtpDialogOpen(false)
    if (pendingFavoriteEvent) {
      performFavoriteToggle(pendingFavoriteEvent)
      setPendingFavoriteEvent(null)
    }
  }

  useEffect(() => {
    if (!slug) return
    let cancelled = false
    setIsLoadingTenant(true)
    setTenantError(null)
    storefrontService
      .getStorefront(slug)
      .then((result) => {
        if (!cancelled) setTenant(result)
      })
      .catch((error: unknown) => {
        if (!cancelled) setTenantError(getApiErrorMessage(error, 'Não foi possível carregar esta loja agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoadingTenant(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug])

  useEffect(() => {
    if (!slug || isLoadingTenant || !tenant || tenant.storefront_enabled === false) {
      setCategories([])
      return
    }
    let cancelled = false
    storefrontService
      .listStorefrontCategories(slug)
      .then((result) => {
        if (!cancelled) setCategories(result)
      })
      .catch(() => {
        // Barra de categorias é um complemento visual — some silenciosamente se falhar, não bloqueia o catálogo.
      })
    return () => {
      cancelled = true
    }
  }, [slug, isLoadingTenant, tenant])

  useEffect(() => {
    setPage(1)
  }, [debouncedSearch, selectedCategoryUuid])

  useEffect(() => {
    if (isLoadingTenant || !tenant) {
      return
    }

    if (tenant?.storefront_enabled === false) {
      setEvents([])
      setLastPage(1)
      setEventsError(null)
      setIsLoadingEvents(false)
      return
    }

    if (!slug) return
    let cancelled = false
    setIsLoadingEvents(true)
    setEventsError(null)
    storefrontService
      .listStorefrontEvents(slug, {
        name: debouncedSearch || undefined,
        event_category_uuid: selectedCategoryUuid ?? undefined,
        per_page: PER_PAGE,
        page,
      })
      .then((result) => {
        if (cancelled) return
        setEvents(result.items)
        setLastPage(result.pagination.last_page)
      })
      .catch((error: unknown) => {
        if (!cancelled) setEventsError(getApiErrorMessage(error, 'Não foi possível carregar os eventos agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoadingEvents(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug, debouncedSearch, selectedCategoryUuid, page, isLoadingTenant, tenant])

  if (tenantError) {
    return (
      <Box sx={{ minHeight: '100dvh', display: 'flex', alignItems: 'center', justifyContent: 'center', px: 2 }}>
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center', maxWidth: 420 }}>
          <SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)', mb: 1.5 }} />
          <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 0.75 }}>Loja não encontrada</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>{tenantError}</Typography>
        </Paper>
      </Box>
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
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', mb: 3 }}>
          {isLoadingTenant ? (
            <Skeleton variant="circular" width={48} height={48} />
          ) : (
            <Avatar
              src={tenant?.logo_url ?? undefined}
              variant="rounded"
              sx={{ ...SOFT_PANEL_SX, width: 48, height: 48 }}
            >
              <StorefrontOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
            </Avatar>
          )}
          <Box sx={{ minWidth: 0 }}>
            {isLoadingTenant ? (
              <Skeleton variant="text" width={160} height={28} />
            ) : (
              <Typography sx={{ fontSize: { xs: 19, sm: 22 }, fontWeight: 700, wordBreak: 'break-word' }}>
                {tenant?.name}
              </Typography>
            )}
            {isLoadingTenant ? (
              <Skeleton variant="text" width={100} height={20} />
            ) : tenant ? (
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5 }}>
                <StoreStatusBadge tenant={tenant} />
                {!tenant.storefront_enabled && <Chip size="small" label="Bilheteria online desativada" variant="outlined" />}
                {tenant.ratings_count > 0 && tenant.average_rating !== null && (
                  <Stack direction="row" spacing={0.25} sx={{ alignItems: 'center' }}>
                    <StarIcon sx={{ fontSize: 15, color: 'var(--pt-warning, #a15c00)' }} />
                    <Typography sx={{ fontSize: 12.5, fontWeight: 700 }}>
                      {tenant.average_rating.toFixed(1).replace('.', ',')}
                    </Typography>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>({tenant.ratings_count})</Typography>
                  </Stack>
                )}
              </Stack>
            ) : (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Eventos e ingressos</Typography>
            )}
          </Box>
        </Stack>

        {tenant && !tenant.storefront_enabled ? (
          <Stack spacing={2.5}>
            <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 } }}>
              <Stack spacing={1.5}>
                <Typography sx={{ fontSize: { xs: 22, sm: 26 }, fontWeight: 700 }}>
                  Esta empresa está usando esta página como canal de contato
                </Typography>
                <Typography sx={{ color: 'var(--pt-muted)' }}>
                  O catálogo de eventos e o checkout estão desativados no momento. Aqui você encontra os dados de contato e localização da empresa.
                </Typography>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                  {tenant.whatsapp ? (
                    <Button variant="contained" startIcon={<WhatsAppIcon fontSize="small" />} href={`https://wa.me/55${digitsOnly(tenant.whatsapp)}`} target="_blank" rel="noreferrer" sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
                      Falar no WhatsApp
                    </Button>
                  ) : null}
                  {tenant.mobile_phone || tenant.phone ? (
                    <Button variant="outlined" startIcon={<PhoneOutlinedIcon fontSize="small" />} href={`tel:${digitsOnly(tenant.mobile_phone ?? tenant.phone ?? '')}`} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
                      Ligar agora
                    </Button>
                  ) : null}
                  {tenant.email ? (
                    <Button variant="outlined" startIcon={<EmailOutlinedIcon fontSize="small" />} href={`mailto:${tenant.email}`} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
                      Enviar e-mail
                    </Button>
                  ) : null}
                </Stack>
              </Stack>
            </Paper>

            <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 } }}>
              <Stack spacing={1}>
                <Typography sx={{ fontSize: 16, fontWeight: 700 }}>Contato e localização</Typography>
                <Typography sx={{ color: 'var(--pt-text)' }}>{tenant.address ?? 'Endereço ainda não informado.'}</Typography>
                <Stack spacing={0.5}>
                  {tenant.phone ? <Typography sx={{ color: 'var(--pt-muted)' }}>Telefone: {tenant.phone}</Typography> : null}
                  {tenant.mobile_phone ? <Typography sx={{ color: 'var(--pt-muted)' }}>Celular: {tenant.mobile_phone}</Typography> : null}
                  {tenant.whatsapp ? <Typography sx={{ color: 'var(--pt-muted)' }}>WhatsApp: {tenant.whatsapp}</Typography> : null}
                  {tenant.email ? <Typography sx={{ color: 'var(--pt-muted)' }}>E-mail: {tenant.email}</Typography> : null}
                </Stack>
                <Button variant="text" onClick={() => navigate(`${slug ? `/loja/${slug}/perfil` : '/'}`)} sx={{ alignSelf: 'flex-start', px: 0 }}>
                  Ver endereço, horários e detalhes completos
                </Button>
              </Stack>
            </Paper>
          </Stack>
        ) : (
          <>
            <TextField
              placeholder="Buscar evento pelo nome"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              fullWidth
              size="medium"
              sx={{ ...SOFT_PANEL_SX, mb: 2.5 }}
              slotProps={{
                input: {
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
                    </InputAdornment>
                  ),
                },
              }}
            />

            {categories.length > 1 && (
              <CategoryChipBar categories={categories} selectedUuid={selectedCategoryUuid} onSelect={setSelectedCategoryUuid} />
            )}

            {eventsError && (
              <Alert severity="error" variant="outlined" sx={{ mb: 2.5 }}>
                {eventsError}
              </Alert>
            )}

            {isLoadingEvents && <CatalogSkeleton />}
          </>
        )}

        {tenant?.storefront_enabled !== false && !isLoadingEvents && !eventsError && events.length === 0 && (
          <EmptyState
            icon={<SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
            title="Nenhum evento encontrado"
            description={
              debouncedSearch
                ? 'Tente buscar por outro termo.'
                : 'Esta loja ainda não publicou eventos disponíveis.'
            }
          />
        )}

        {tenant?.storefront_enabled !== false && !isLoadingEvents && events.length > 0 && (
          <>
            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(min(45%, 220px), 1fr))',
                gap: 1.5,
              }}
            >
              {events.map((event) => (
                <EventCard
                  key={event.uuid}
                  event={event}
                  onClick={(clicked) => navigate(`/loja/${slug}/eventos/${clicked.slug}`)}
                  onToggleFavorite={handleToggleFavorite}
                />
              ))}
            </Box>

            {lastPage > 1 && (
              <Stack sx={{ mt: 3, alignItems: 'center' }}>
                <Pagination page={page} count={lastPage} onChange={(_, value) => setPage(value)} color="primary" shape="rounded" />
              </Stack>
            )}
          </>
        )}

        <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 5 }}>
          <Logo variant="mark" size={20} />
          <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>Loja via PegaTicket</Typography>
        </Stack>
      </Box>

      <OtpLoginDialog
        open={otpDialogOpen}
        onClose={() => {
          setOtpDialogOpen(false)
          setPendingFavoriteEvent(null)
        }}
        onAuthenticated={handleFavoriteOtpAuthenticated}
      />

      <Snackbar
        open={Boolean(favoriteErrorMessage)}
        autoHideDuration={4000}
        onClose={() => setFavoriteErrorMessage(null)}
        message={favoriteErrorMessage}
      />

      {slug && totalQuantity > 0 && <FloatingCheckoutBar slug={slug} />}
    </Box>
  )
}
