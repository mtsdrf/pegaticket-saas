import FavoriteIcon from '@mui/icons-material/Favorite'
import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Alert, Avatar, Box, IconButton, Pagination, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { EmptyState } from '../../components/layout/EmptyState'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { listFavorites, toggleFavorite } from '../../services/favoriteService'
import { getApiErrorMessage } from '../../types/api'
import type { StorefrontProduct } from '../../types/storefront'
import { formatCurrency } from '../../utils/format'
import { PortalShell } from './PortalShell'

const PER_PAGE = 15

function LoadingSkeleton() {
  return (
    <Stack spacing={1.25}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={76} sx={{ borderRadius: 'var(--mk-radius-xl)' }} />
      ))}
    </Stack>
  )
}

/**
 * Item favoritado — `StorefrontProductResource` (catálogo/favoritos) não
 * expõe nenhuma referência à loja (nem `tenant_name` nem slug), então não há
 * como montar aqui um link de volta pra loja de origem sem inventar dado que
 * a API não devolve. Sinalizado no relatório final da tarefa; se o backend
 * um dia expuser isso, trocar este card por um `RouterLink` pra `/loja/{slug}`.
 */
function FavoriteCard({ product, onUnfavorite }: { product: StorefrontProduct; onUnfavorite: (product: StorefrontProduct) => void }) {
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
        src={product.image_url ?? undefined}
        variant="rounded"
        sx={{ width: 56, height: 56, flexShrink: 0, ...SOFT_PANEL_SX }}
      >
        <StorefrontOutlinedIcon sx={{ color: 'var(--mk-muted)' }} />
      </Avatar>

      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14.5, fontWeight: 700, wordBreak: 'break-word' }}>{product.name}</Typography>
        <Typography sx={{ fontSize: 13.5, fontWeight: 700, color: 'var(--mk-primary)', mt: 0.25 }}>
          {formatCurrency(product.promo_price ?? product.price)}
        </Typography>
        {!product.is_available && (
          <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)', mt: 0.25 }}>Indisponível no momento</Typography>
        )}
      </Box>

      <IconButton
        onClick={() => onUnfavorite(product)}
        aria-label={`Remover ${product.name} dos favoritos`}
        size="small"
        sx={{ flexShrink: 0, minWidth: 44, minHeight: 44 }}
      >
        <FavoriteIcon fontSize="small" sx={{ color: 'var(--mk-primary)' }} />
      </IconButton>
    </Paper>
  )
}

function EmptyFavorites() {
  return (
    <Paper elevation={0} sx={{ p: 4, ...ELEVATED_SURFACE_SX }}>
      <EmptyState
        icon={<FavoriteBorderOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />}
        title="Nenhum produto favoritado ainda"
        description="Toque no coração de um produto no catálogo de uma loja para favoritá-lo e acompanhar por aqui."
      />
    </Paper>
  )
}

export function PortalFavoritesPage() {
  const [products, setProducts] = useState<StorefrontProduct[] | null>(null)
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
        setProducts(result.items)
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

  function handleUnfavorite(product: StorefrontProduct) {
    setProducts((current) => (current ? current.filter((item) => item.uuid !== product.uuid) : current))
    toggleFavorite(product.uuid).catch((error: unknown) => {
      // Reverte a remoção otimista se a chamada falhar de verdade.
      setProducts((current) => (current ? [product, ...current] : current))
      setErrorMessage(getApiErrorMessage(error, 'Não foi possível remover este favorito agora.'))
    })
  }

  return (
    <PortalShell title="Favoritos" subtitle="Produtos que você favoritou nas lojas que visitou.">
      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && products && products.length === 0 && <EmptyFavorites />}

      {!isLoading && !errorMessage && products && products.length > 0 && (
        <Stack spacing={1.25}>
          {products.map((product) => (
            <FavoriteCard key={product.uuid} product={product} onUnfavorite={handleUnfavorite} />
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
