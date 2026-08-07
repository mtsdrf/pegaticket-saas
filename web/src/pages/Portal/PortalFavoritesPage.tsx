import FavoriteIcon from '@mui/icons-material/Favorite'
import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import { Alert, Avatar, Box, IconButton, Pagination, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { EmptyState } from '../../components/layout/EmptyState'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { listFavorites, toggleFavorite } from '../../services/favoriteService'
import { getApiErrorMessage } from '../../types/api'
import type { StorefrontEvent } from '../../types/storefront'
import { resolveEventCoverImageUrl } from '../../utils/eventCover'
import { formatDateBR } from '../../utils/format'
import { PortalShell } from './PortalShell'

const PER_PAGE = 15

function LoadingSkeleton() {
  return (
    <Stack spacing={1.25}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={76} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
      ))}
    </Stack>
  )
}

/**
 * Item favoritado — `EventResource` (catálogo/favoritos) não expõe nenhuma
 * referência à loja (nem `tenant_name` nem slug), então não há como montar
 * aqui um link de volta pra loja de origem sem inventar dado que a API não
 * devolve. Sinalizado no relatório final da tarefa; se o backend um dia
 * expuser isso, trocar este card por um `RouterLink` pra `/loja/{slug}/eventos/{event.slug}`.
 */
function FavoriteCard({ event, onUnfavorite }: { event: StorefrontEvent; onUnfavorite: (event: StorefrontEvent) => void }) {
  return (
    <Paper
      elevation={0}
      sx={{
        p: 1.5,
        ...ELEVATED_SURFACE_SX,
        display: 'flex',
        alignItems: 'center',
        gap: 1.5,
      }}
    >
      <Avatar
        src={resolveEventCoverImageUrl(event.cover_image_url)}
        variant="rounded"
        sx={{ width: 56, height: 56, flexShrink: 0, ...SOFT_PANEL_SX }}
      >
        <EventOutlinedIcon sx={{ color: 'var(--pt-muted)' }} />
      </Avatar>

      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14.5, fontWeight: 700, wordBreak: 'break-word' }}>{event.name}</Typography>
        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mt: 0.25 }}>{formatDateBR(event.starts_at)}</Typography>
      </Box>

      <IconButton
        onClick={() => onUnfavorite(event)}
        aria-label={`Remover ${event.name} dos favoritos`}
        size="small"
        sx={{ flexShrink: 0, minWidth: 44, minHeight: 44 }}
      >
        <FavoriteIcon fontSize="small" sx={{ color: 'var(--pt-primary)' }} />
      </IconButton>
    </Paper>
  )
}

function EmptyFavorites() {
  return (
    <Paper elevation={0} sx={{ p: 4, ...ELEVATED_SURFACE_SX }}>
      <EmptyState
        icon={<FavoriteBorderOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
        title="Nenhum evento favoritado ainda"
        description="Toque no coração de um evento no catálogo de uma loja para favoritá-lo e acompanhar por aqui."
      />
    </Paper>
  )
}

export function PortalFavoritesPage() {
  const [events, setEvents] = useState<StorefrontEvent[] | null>(null)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setIsLoading(true)
    setErrorMessage(null)
    listFavorites(PER_PAGE, page)
      .then((result) => {
        if (cancelled) return
        setEvents(result.items)
        setLastPage(result.pagination.last_page)
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar seus favoritos agora. Tente novamente.'))
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [page])

  function handleUnfavorite(event: StorefrontEvent) {
    setEvents((current) => (current ? current.filter((item) => item.uuid !== event.uuid) : current))
    toggleFavorite(event.uuid).catch((error: unknown) => {
      // Reverte a remoção otimista se a chamada falhar de verdade.
      setEvents((current) => (current ? [event, ...current] : current))
      setErrorMessage(getApiErrorMessage(error, 'Não foi possível remover este favorito agora.'))
    })
  }

  return (
    <PortalShell title="Favoritos" subtitle="Eventos que você favoritou nas lojas que visitou.">
      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && events && events.length === 0 && <EmptyFavorites />}

      {!isLoading && !errorMessage && events && events.length > 0 && (
        <Stack spacing={1.25}>
          {events.map((event) => (
            <FavoriteCard key={event.uuid} event={event} onUnfavorite={handleUnfavorite} />
          ))}

          {lastPage > 1 && (
            <Stack sx={{ mt: 1, alignItems: 'center' }}>
              <Pagination page={page} count={lastPage} onChange={(_, value) => setPage(value)} color="primary" shape="rounded" size="small" />
            </Stack>
          )}
        </Stack>
      )}
    </PortalShell>
  )
}
