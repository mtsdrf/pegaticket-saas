import EmailOutlinedIcon from '@mui/icons-material/EmailOutlined'
import PhoneOutlinedIcon from '@mui/icons-material/PhoneOutlined'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import StarIcon from '@mui/icons-material/Star'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import TrendingUpOutlinedIcon from '@mui/icons-material/TrendingUpOutlined'
import WhatsAppIcon from '@mui/icons-material/WhatsApp'
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  FormControl,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Skeleton,
  Snackbar,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import type { SelectChangeEvent } from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import { useEffect, useState } from 'react'
import { ProductOptionsConfiguratorDialog, type ProductOptionSelection } from '../../components/product/ProductOptionsConfiguratorDialog'
import { useNavigate, useParams } from 'react-router-dom'
import { CategoryChipBar } from '../../components/storefront/CategoryChipBar'
import { EmptyState } from '../../components/layout/EmptyState'
import { FloatingCheckoutBar, FLOATING_CHECKOUT_BAR_HEIGHT } from '../../components/storefront/FloatingCheckoutBar'
import { STOREFRONT_BOTTOM_NAV_HEIGHT } from '../../components/storefront/StorefrontBottomNav'
import { OtpLoginDialog } from '../../components/storefront/OtpLoginDialog'
import { ProductGridCard } from '../../components/storefront/ProductGridCard'
import { ProductListItem } from '../../components/storefront/ProductListItem'
import { Logo } from '../../components/ui/Logo'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { toggleFavorite } from '../../services/favoriteService'
import * as storefrontService from '../../services/storefrontService'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type {
  StorefrontCategory,
  StorefrontProduct,
  StorefrontSortOption,
  StorefrontTenant,
} from '../../types/storefront'
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
          color: isOpen ? 'var(--mk-success, #1b7a3d)' : 'var(--mk-warning, #a15c00)',
          bgcolor: isOpen ? 'color-mix(in srgb, #1b7a3d 14%, transparent)' : 'color-mix(in srgb, #a15c00 14%, transparent)',
        }}
      />
      {tenant.estimated_preparation_minutes !== null && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
          ~{tenant.estimated_preparation_minutes} min
        </Typography>
      )}
    </Stack>
  )
}

const PER_PAGE = 12
const BEST_SELLERS_LIMIT = 10

const SORT_OPTIONS: { value: StorefrontSortOption; label: string }[] = [
  { value: 'relevance', label: 'Relevância' },
  { value: 'price_asc', label: 'Menor preço' },
  { value: 'price_desc', label: 'Maior preço' },
  { value: 'best_selling', label: 'Mais vendidos' },
]

function CatalogSkeleton() {
  return (
    <Stack spacing={1.5}>
      {Array.from({ length: 6 }).map((_, index) => (
        <Skeleton key={index} variant="rounded" height={104} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
      ))}
    </Stack>
  )
}

function digitsOnly(value: string) {
  return value.replace(/\D+/g, '')
}

/**
 * Rail horizontal "Mais vendidos" (vitrine estilo iFood) — só aparece como
 * seção de destaque quando o cliente ainda não filtrou/ordenou nada (ver
 * condição de fetch no efeito abaixo). SEMPRE usa `ProductGridCard`
 * (vertical, foto centralizada) independente do `catalog_layout` escolhido
 * pela empresa para o catálogo principal — decisão de produto: destaque de
 * "mais vendidos" tem identidade visual própria, não herda o configurador.
 */
function BestSellersRail({
  products,
  onToggleFavorite,
  onConfigureOptions,
}: {
  products: StorefrontProduct[]
  onToggleFavorite: (product: StorefrontProduct) => void
  onConfigureOptions: (product: StorefrontProduct) => void
}) {
  if (products.length === 0) return null

  return (
    <Box sx={{ mb: 3 }}>
      <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mb: 1.25 }}>
        <TrendingUpOutlinedIcon sx={{ fontSize: 20, color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 16, fontWeight: 700 }}>Mais vendidos</Typography>
      </Stack>
      <Stack
        direction="row"
        spacing={1.5}
        sx={{
          overflowX: 'auto',
          pb: 0.5,
          alignItems: 'stretch',
          scrollbarWidth: 'none',
          '&::-webkit-scrollbar': { display: 'none' },
        }}
      >
        {products.map((product) => (
          <Box key={product.uuid} sx={{ width: 168, flexShrink: 0, display: 'flex' }}>
            <ProductGridCard
              product={product}
              onToggleFavorite={onToggleFavorite}
              onConfigureOptions={onConfigureOptions}
              excludeBadges={['best_selling']}
            />
          </Box>
        ))}
      </Stack>
    </Box>
  )
}

export function StorefrontCatalogPage() {
  const navigate = useNavigate()
  const { slug } = useParams<{ slug: string }>()
  const { isAuthenticated } = usePortalAuth()
  const { addConfiguredItem, totalQuantity } = useStorefrontCart()

  const [tenant, setTenant] = useState<StorefrontTenant | null>(null)
  const [tenantError, setTenantError] = useState<string | null>(null)
  const [isLoadingTenant, setIsLoadingTenant] = useState(true)

  const [products, setProducts] = useState<StorefrontProduct[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [isLoadingProducts, setIsLoadingProducts] = useState(true)
  const [productsError, setProductsError] = useState<string | null>(null)

  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebouncedValue(searchTerm, 400)
  const [onlyPromotion, setOnlyPromotion] = useState(false)
  const [sort, setSort] = useState<StorefrontSortOption>('relevance')

  // Categorias (vitrine estilo iFood) — carregadas 1x quando o tenant
  // resolve, independente de busca/ordenação.
  const [categories, setCategories] = useState<StorefrontCategory[]>([])
  const [selectedCategoryUuid, setSelectedCategoryUuid] = useState<string | null>(null)

  // "Mais vendidos" — seção de destaque, só busca quando não há
  // busca/categoria/ordenação/promoção ativa (é a home da loja, não um
  // resultado filtrado); falha silenciosa, some se vier vazio.
  const [bestSellers, setBestSellers] = useState<StorefrontProduct[]>([])
  const [optionsDialogProduct, setOptionsDialogProduct] = useState<StorefrontProduct | null>(null)
  const showBestSellersRail =
    !debouncedSearch && !onlyPromotion && !selectedCategoryUuid && sort === 'relevance' && page === 1

  // Favoritos (Delivery Fase 4) — exige identidade do FinalCustomer; se
  // ainda não autenticado, abre o mesmo diálogo de OTP do checkout antes de
  // favoritar (nunca duplica o fluxo). Otimista: alterna o ícone na hora,
  // reverte se a chamada falhar.
  const [otpDialogOpen, setOtpDialogOpen] = useState(false)
  const [pendingFavoriteProduct, setPendingFavoriteProduct] = useState<StorefrontProduct | null>(null)
  const [favoriteErrorMessage, setFavoriteErrorMessage] = useState<string | null>(null)

  function performFavoriteToggle(product: StorefrontProduct) {
    const applyToggle = (list: StorefrontProduct[]) =>
      list.map((item) => (item.uuid === product.uuid ? { ...item, is_favorited: !item.is_favorited } : item))

    setProducts(applyToggle)
    setBestSellers(applyToggle)

    toggleFavorite(product.uuid).catch((error: unknown) => {
      const revertToggle = (list: StorefrontProduct[]) =>
        list.map((item) => (item.uuid === product.uuid ? { ...item, is_favorited: product.is_favorited } : item))
      setProducts(revertToggle)
      setBestSellers(revertToggle)
      setFavoriteErrorMessage(getApiErrorMessage(error, 'Não foi possível atualizar seus favoritos agora.'))
    })
  }

  function handleToggleFavorite(product: StorefrontProduct) {
    if (!isAuthenticated) {
      setPendingFavoriteProduct(product)
      setOtpDialogOpen(true)
      return
    }
    performFavoriteToggle(product)
  }

  function handleConfigureOptions(product: StorefrontProduct) {
    setOptionsDialogProduct(product)
  }

  function handleConfirmOptions(selections: ProductOptionSelection[]) {
    if (!optionsDialogProduct) return
    addConfiguredItem(optionsDialogProduct, 1, selections)
    setOptionsDialogProduct(null)
  }

  function handleFavoriteOtpAuthenticated() {
    setOtpDialogOpen(false)
    if (pendingFavoriteProduct) {
      performFavoriteToggle(pendingFavoriteProduct)
      setPendingFavoriteProduct(null)
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
  }, [debouncedSearch, onlyPromotion, selectedCategoryUuid, sort])

  useEffect(() => {
    if (isLoadingTenant || !tenant) {
      return
    }

    if (tenant?.storefront_enabled === false) {
      setProducts([])
      setLastPage(1)
      setProductsError(null)
      setIsLoadingProducts(false)
      return
    }

    if (!slug) return
    let cancelled = false
    setIsLoadingProducts(true)
    setProductsError(null)
    storefrontService
      .listStorefrontProducts(slug, {
        name: debouncedSearch || undefined,
        on_promotion: onlyPromotion || undefined,
        product_category_uuid: selectedCategoryUuid ?? undefined,
        sort,
        per_page: PER_PAGE,
        page,
      })
      .then((result) => {
        if (cancelled) return
        setProducts(result.items)
        setLastPage(result.pagination.last_page)
      })
      .catch((error: unknown) => {
        if (!cancelled) setProductsError(getApiErrorMessage(error, 'Não foi possível carregar os produtos agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoadingProducts(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug, debouncedSearch, onlyPromotion, selectedCategoryUuid, sort, page, isLoadingTenant, tenant])

  useEffect(() => {
    if (!slug || isLoadingTenant || !tenant || !showBestSellersRail || tenant.storefront_enabled === false) {
      setBestSellers([])
      return
    }
    let cancelled = false
    storefrontService
      .listStorefrontProducts(slug, { sort: 'best_selling', per_page: BEST_SELLERS_LIMIT, page: 1 })
      .then((result) => {
        if (!cancelled) setBestSellers(result.items)
      })
      .catch(() => {
        // Seção de destaque opcional — falha silenciosa, não afeta o grid principal.
      })
    return () => {
      cancelled = true
    }
  }, [slug, showBestSellersRail, isLoadingTenant, tenant])

  if (tenantError) {
    return (
      <Box sx={{ minHeight: '100dvh', display: 'flex', alignItems: 'center', justifyContent: 'center', px: 2 }}>
        <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center', maxWidth: 420 }}>
          <SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)', mb: 1.5 }} />
          <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 0.75 }}>Loja não encontrada</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>{tenantError}</Typography>
        </Paper>
      </Box>
    )
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--mk-page-background)',
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
              <StorefrontOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
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
                {!tenant.storefront_enabled && <Chip size="small" label="Loja online desativada" variant="outlined" />}
                {tenant.ratings_count > 0 && tenant.average_rating !== null && (
                  <Stack direction="row" spacing={0.25} sx={{ alignItems: 'center' }}>
                    <StarIcon sx={{ fontSize: 15, color: 'var(--mk-warning, #a15c00)' }} />
                    <Typography sx={{ fontSize: 12.5, fontWeight: 700 }}>
                      {tenant.average_rating.toFixed(1).replace('.', ',')}
                    </Typography>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>({tenant.ratings_count})</Typography>
                  </Stack>
                )}
              </Stack>
            ) : (
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>Cardápio digital</Typography>
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
                <Typography sx={{ color: 'var(--mk-muted)' }}>
                  O catálogo de produtos e o checkout estão desativados no momento. Aqui você encontra os dados de contato e localização da empresa.
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
                <Typography sx={{ color: 'var(--mk-text)' }}>{tenant.address ?? 'Endereço ainda não informado.'}</Typography>
                <Stack spacing={0.5}>
                  {tenant.phone ? <Typography sx={{ color: 'var(--mk-muted)' }}>Telefone: {tenant.phone}</Typography> : null}
                  {tenant.mobile_phone ? <Typography sx={{ color: 'var(--mk-muted)' }}>Celular: {tenant.mobile_phone}</Typography> : null}
                  {tenant.whatsapp ? <Typography sx={{ color: 'var(--mk-muted)' }}>WhatsApp: {tenant.whatsapp}</Typography> : null}
                  {tenant.email ? <Typography sx={{ color: 'var(--mk-muted)' }}>E-mail: {tenant.email}</Typography> : null}
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
              placeholder="Buscar produto pelo nome"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              fullWidth
              size="medium"
              sx={{ ...SOFT_PANEL_SX, mb: 2.5 }}
              slotProps={{
                input: {
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon fontSize="small" sx={{ color: 'var(--mk-muted)' }} />
                    </InputAdornment>
                  ),
                },
              }}
            />

            {categories.length > 1 && (
              <CategoryChipBar categories={categories} selectedUuid={selectedCategoryUuid} onSelect={setSelectedCategoryUuid} />
            )}

            {showBestSellersRail && (
              <BestSellersRail
                products={bestSellers}
                onToggleFavorite={handleToggleFavorite}
                onConfigureOptions={handleConfigureOptions}
              />
            )}

            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 1, mb: 2.5 }}>
              <Chip
                label="Em promoção"
                clickable
                onClick={() => setOnlyPromotion((current) => !current)}
                color={onlyPromotion ? 'primary' : 'default'}
                variant={onlyPromotion ? 'filled' : 'outlined'}
                sx={{ fontWeight: 600 }}
              />

              <FormControl size="small" sx={{ ml: 'auto', minWidth: 168 }}>
                <Select
                  value={sort}
                  onChange={(event: SelectChangeEvent) => setSort(event.target.value as StorefrontSortOption)}
                  displayEmpty
                  sx={{ ...SOFT_PANEL_SX, fontSize: 14, fontWeight: 600 }}
                >
                  {SORT_OPTIONS.map((option) => (
                    <MenuItem key={option.value} value={option.value} sx={{ fontSize: 14 }}>
                      Ordenar: {option.label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Stack>

            {productsError && (
              <Alert severity="error" variant="outlined" sx={{ mb: 2.5 }}>
                {productsError}
              </Alert>
            )}

            {isLoadingProducts && <CatalogSkeleton />}
          </>
        )}

        {tenant?.storefront_enabled !== false && !isLoadingProducts && !productsError && products.length === 0 && (
          <EmptyState
            icon={<SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />}
            title="Nenhum produto encontrado"
            description={
              debouncedSearch
                ? 'Tente buscar por outro termo.'
                : 'Esta loja ainda não cadastrou produtos disponíveis.'
            }
          />
        )}

        {tenant?.storefront_enabled !== false && !isLoadingProducts && products.length > 0 && (
          <>
            {tenant?.catalog_layout === 'grid' ? (
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fill, minmax(min(45%, 190px), 1fr))',
                  gap: 1.5,
                }}
              >
                {products.map((product) => (
                  <ProductGridCard
                    key={product.uuid}
                    product={product}
                    onToggleFavorite={handleToggleFavorite}
                    onConfigureOptions={handleConfigureOptions}
                  />
                ))}
              </Box>
            ) : (
              <Stack spacing={1.5}>
                {products.map((product) => (
                  <ProductListItem
                    key={product.uuid}
                    product={product}
                    onToggleFavorite={handleToggleFavorite}
                    onConfigureOptions={handleConfigureOptions}
                  />
                ))}
              </Stack>
            )}

            {lastPage > 1 && (
              <Stack sx={{ mt: 3, alignItems: 'center' }}>
                <Pagination page={page} count={lastPage} onChange={(_, value) => setPage(value)} color="primary" shape="rounded" />
              </Stack>
            )}
          </>
        )}

        <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 5 }}>
          <Logo variant="mark" size={20} />
          <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)' }}>Loja via Maskats</Typography>
        </Stack>
      </Box>

      <OtpLoginDialog
        open={otpDialogOpen}
        onClose={() => {
          setOtpDialogOpen(false)
          setPendingFavoriteProduct(null)
        }}
        onAuthenticated={handleFavoriteOtpAuthenticated}
      />

      <ProductOptionsConfiguratorDialog
        open={Boolean(optionsDialogProduct)}
        title={optionsDialogProduct ? `Personalizar ${optionsDialogProduct.name}` : 'Personalizar produto'}
        groups={(optionsDialogProduct?.option_groups ?? []).map((group) => ({
          uuid: group.uuid,
          name: group.name,
          description: group.description,
          kind: group.kind,
          min_select: group.min_select,
          max_select: group.max_select,
          options: group.options.map((option) => ({
            uuid: option.uuid,
            name: option.name,
            description: option.description,
            price: option.price,
          })),
        }))}
        onClose={() => setOptionsDialogProduct(null)}
        onConfirm={handleConfirmOptions}
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
