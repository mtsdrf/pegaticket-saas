import { Alert, Box, Button, InputAdornment, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import * as clientCategoryService from '../../services/clientCategoryService'
import * as productService from '../../services/productService'
import { getApiErrorMessage } from '../../types/api'
import type { ClientCategory } from '../../types/clientCategory'

interface ProductCategoryPricesSectionProps {
  productUuid: string
}

/**
 * Preço por categoria de cliente (roadmap 2.4 — atacado/varejo). Campo vazio
 * = sem override configurado (usa `product.price`). Salvamento é
 * independente do formulário principal do produto — o endpoint de sync é
 * full-replace (`POST .../category-prices/sync`), não faz parte do
 * `PUT /products/{uuid}` — por isso tem seu próprio botão/estado de
 * loading-erro-sucesso, em vez de acoplar ao submit do produto.
 */
export function ProductCategoryPricesSection({ productUuid }: ProductCategoryPricesSectionProps) {
  const [categories, setCategories] = useState<ClientCategory[]>([])
  const [prices, setPrices] = useState<Record<string, string>>({})
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saveSuccess, setSaveSuccess] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    setLoadError(null)

    Promise.all([
      clientCategoryService.listClientCategories({ per_page: 100, sort_by: 'name', sort_dir: 'asc' }),
      productService.listProductCategoryPrices(productUuid),
    ])
      .then(([categoriesResult, existingPrices]) => {
        setCategories(categoriesResult.items)
        const initial: Record<string, string> = {}
        for (const entry of existingPrices) {
          initial[entry.client_category_uuid] = String(entry.price)
        }
        setPrices(initial)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os preços por categoria agora.')))
      .finally(() => setIsLoading(false))
  }, [productUuid])

  function updatePrice(categoryUuid: string, value: string) {
    setPrices((current) => ({ ...current, [categoryUuid]: value }))
    setSaveSuccess(false)
  }

  async function handleSave() {
    setIsSaving(true)
    setSaveError(null)
    setSaveSuccess(false)

    // Campo vazio significa "sem override" — omitido do payload. É assim
    // que uma categoria com preço configurado anteriormente é removida
    // (o endpoint é full-replace, não incremental).
    const payload = Object.entries(prices)
      .filter(([, value]) => value.trim() !== '' && Number.isFinite(Number(value)))
      .map(([client_category_uuid, value]) => ({ client_category_uuid, price: Number(value) }))

    try {
      await productService.syncProductCategoryPrices(productUuid, payload)
      setSaveSuccess(true)
    } catch (error) {
      setSaveError(getApiErrorMessage(error, 'Não foi possível salvar os preços por categoria agora.'))
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return (
      <Stack spacing={1.5}>
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} variant="rounded" height={44} />
        ))}
      </Stack>
    )
  }

  if (loadError) {
    return <Alert severity="error">{loadError}</Alert>
  }

  if (categories.length === 0) {
    return (
      <Alert severity="info">
        Nenhuma categoria de cliente cadastrada ainda. Crie categorias em "Categorias de clientes" para configurar preços
        diferenciados por atacado/varejo.
      </Alert>
    )
  }

  return (
    <Stack spacing={2}>
      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
        Deixe o campo vazio para usar o preço de tabela. Se um cliente se enquadrar em mais de uma categoria com preço
        configurado, o menor preço é aplicado.
      </Typography>

      {saveError && <Alert severity="error">{saveError}</Alert>}
      {saveSuccess && <Alert severity="success">Preços por categoria atualizados.</Alert>}

      <Stack spacing={1.25}>
        {categories.map((category) => (
          <Box
            key={category.uuid}
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr)' },
              gap: 1.5,
              alignItems: 'center',
            }}
          >
            <Typography sx={{ fontSize: 14, color: 'var(--mk-text)' }}>{category.name}</Typography>
            <TextField
              label="Preço"
              type="number"
              size="small"
              placeholder="Preço de tabela"
              value={prices[category.uuid] ?? ''}
              onChange={(event) => updatePrice(category.uuid, event.target.value)}
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
            />
          </Box>
        ))}
      </Stack>

      <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
        <Button
          type="button"
          variant="outlined"
          disabled={isSaving}
          onClick={() => void handleSave()}
          sx={{ minWidth: 200 }}
        >
          {isSaving ? 'Salvando…' : 'Salvar preços por categoria'}
        </Button>
      </Box>
    </Stack>
  )
}
