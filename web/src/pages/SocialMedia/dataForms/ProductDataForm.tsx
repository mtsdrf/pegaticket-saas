import { Box, Checkbox, FormControlLabel, Stack, TextField } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { AsyncAutocomplete } from '../../../components/crud/AsyncAutocomplete'
import { useAuth } from '../../../hooks/useAuth'
import { useRemoteImageDataUrl } from '../../../hooks/useRemoteImageDataUrl'
import * as productService from '../../../services/productService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import type { Product } from '../../../types/product'
import type { StorySingleContent } from '../../../types/socialMedia'

interface ProductDataFormProps {
  /** Passar diretamente o setter de estado do pai (`setResolvedContent`, identidade estável do `useState`) — nunca uma arrow function inline, senão o efeito abaixo entra em loop de render. */
  onContentChange: (content: StorySingleContent | null) => void
}

function priceField(text: string): number | undefined {
  const n = Number(text)
  return text.trim() && Number.isFinite(n) ? n : undefined
}

/**
 * Formulário de dado do conteúdo "Produto" — busca no catálogo, pré-preenche
 * nome/preço/foto, tudo editável. Preço varejo vem pré-preenchido do
 * cadastro do produto (`product.price`, "a base"); oferta e atacado são
 * opcionais, ligados por checkbox, e não persistem em lugar nenhum — é
 * conteúdo ad-hoc só pro story, mesma filosofia 100% client-side do resto
 * desta feature (não é o mesmo mecanismo de `product_promotions`, que é
 * preço real de checkout da loja online).
 */
export function ProductDataForm({ onContentChange }: ProductDataFormProps) {
  const { activeTenantUuid } = useAuth()
  const [product, setProduct] = useState<Product | null>(null)
  const [title, setTitle] = useState('')
  const [retailPriceText, setRetailPriceText] = useState('')
  const [isOffer, setIsOffer] = useState(false)
  const [offerPriceText, setOfferPriceText] = useState('')
  const [isWholesale, setIsWholesale] = useState(false)
  const [wholesalePriceText, setWholesalePriceText] = useState('')
  const [wholesaleMinQtyText, setWholesaleMinQtyText] = useState('')

  const { dataUrl: imageDataUrl, isLoading: isImageLoading } = useRemoteImageDataUrl(product?.image_url)

  const fetchProductOptions = useCallback(
    async (query: string): Promise<Product[]> => {
      if (!activeTenantUuid) return []
      const result = await productService.listProducts({ name: query || undefined, per_page: 20, sort_by: 'name', sort_dir: 'asc' })
      return result.items
    },
    [activeTenantUuid],
  )

  function handleProductChange(next: Product | null) {
    setProduct(next)
    setTitle(next?.name ?? '')
    setRetailPriceText(next ? String(next.price) : '')
    setIsOffer(false)
    setOfferPriceText('')
    setIsWholesale(false)
    setWholesalePriceText('')
    setWholesaleMinQtyText('')
  }

  useEffect(() => {
    if (!title.trim()) {
      onContentChange(null)
      return
    }

    const retailPrice = priceField(retailPriceText)
    const offerPrice = isOffer ? priceField(offerPriceText) : undefined
    const wholesalePrice = isWholesale ? priceField(wholesalePriceText) : undefined
    const wholesaleMinQuantity = isWholesale ? priceField(wholesaleMinQtyText) : undefined

    onContentChange({
      kind: 'single',
      eyebrow: 'Produto em destaque',
      title: title.trim(),
      imageDataUrl,
      pricing:
        retailPrice !== undefined
          ? { regularPrice: retailPrice, offerPrice, wholesalePrice, wholesaleMinQuantity }
          : undefined,
    })
  }, [title, retailPriceText, isOffer, offerPriceText, isWholesale, wholesalePriceText, wholesaleMinQtyText, imageDataUrl, onContentChange])

  return (
    <Stack spacing={2}>
      <AsyncAutocomplete
        label="Produto"
        value={product}
        onChange={handleProductChange}
        fetchOptions={fetchProductOptions}
        getOptionLabel={(option) => option.name}
        getOptionKey={(option) => option.uuid}
        placeholder="Buscar produto pelo nome"
        helperText={isImageLoading ? 'Carregando foto do produto…' : undefined}
      />

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
        <TextField label="Nome exibido no story" value={title} onChange={(event) => setTitle(event.target.value)} required />
        <TextField
          label="Preço varejo"
          type="number"
          value={retailPriceText}
          onChange={(event) => setRetailPriceText(event.target.value)}
          helperText="Pré-preenchido com o preço cadastrado do produto — pode ajustar."
          slotProps={{
            htmlInput: { min: 0, step: '0.01' },
            input: { startAdornment: <span style={{ color: 'var(--mk-muted)' }}>R$</span> },
          }}
        />
      </Box>

      <Box>
        <FormControlLabel
          control={<Checkbox checked={isOffer} onChange={(event) => setIsOffer(event.target.checked)} />}
          label="Esta é uma oferta especial"
        />
        {isOffer && (
          <TextField
            label="Preço da oferta"
            type="number"
            value={offerPriceText}
            onChange={(event) => setOfferPriceText(event.target.value)}
            helperText="Aparece em destaque no story, com o preço varejo riscado acima."
            fullWidth
            sx={{ mt: 1 }}
            slotProps={{
              htmlInput: { min: 0, step: '0.01' },
              input: { startAdornment: <span style={{ color: 'var(--mk-muted)' }}>R$</span> },
            }}
          />
        )}
      </Box>

      <Box>
        <FormControlLabel
          control={<Checkbox checked={isWholesale} onChange={(event) => setIsWholesale(event.target.checked)} />}
          label="Incluir preço de atacado"
        />
        {isWholesale && (
          <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, mt: 1 }}>
            <TextField
              label="Preço atacado"
              type="number"
              value={wholesalePriceText}
              onChange={(event) => setWholesalePriceText(event.target.value)}
              slotProps={{
                htmlInput: { min: 0, step: '0.01' },
                input: { startAdornment: <span style={{ color: 'var(--mk-muted)' }}>R$</span> },
              }}
            />
            <TextField
              label="Quantidade mínima (opcional)"
              type="number"
              value={wholesaleMinQtyText}
              onChange={(event) => setWholesaleMinQtyText(event.target.value)}
              helperText='Ex.: 10 → exibe "a partir de 10 un"'
              slotProps={{ htmlInput: { min: 1, step: '1' } }}
            />
          </Box>
        )}
      </Box>
    </Stack>
  )
}
