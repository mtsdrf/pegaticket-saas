import { Alert, Box, Button, CircularProgress, Popover, Stack, Switch, Typography } from '@mui/material'
import { useState, type ChangeEvent } from 'react'
import * as productService from '../../services/productService'
import { getApiErrorMessage } from '../../types/api'
import type { Product } from '../../types/product'

interface ProductAvailabilityToggleProps {
  product: Product
  /** Chamado depois de um toggle bem-sucedido, com o produto já atualizado — quem usa decide como refletir na grid (ex.: `refreshInfiniteCache`). */
  onToggled: (updated: Product) => void
}

/**
 * Toggle rápido de disponibilidade (roadmap A4, item 16) — 1 clique + uma
 * confirmação leve via `Popover` (não um modal pesado) só ao BLOQUEAR o
 * produto (ação com efeito visível pro cliente: some da venda/loja). Ao
 * desbloquear, aplica direto sem confirmação — reverter um bloqueio é
 * inofensivo, confirmar dos dois lados só adicionaria fricção.
 */
export function ProductAvailabilityToggle({ product, onToggled }: ProductAvailabilityToggleProps) {
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const [isSaving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function apply(nextAvailable: boolean) {
    setSaving(true)
    setError(null)
    try {
      const updated = await productService.toggleProductAvailability(product.uuid, nextAvailable)
      onToggled(updated)
      setAnchorEl(null)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível atualizar a disponibilidade agora.'))
    } finally {
      setSaving(false)
    }
  }

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const nextAvailable = event.target.checked
    if (nextAvailable) {
      void apply(true)
      return
    }
    setError(null)
    setAnchorEl(event.currentTarget)
  }

  return (
    <>
      <Switch
        checked={product.is_available}
        onChange={handleChange}
        disabled={isSaving}
        size="small"
        slotProps={{ input: { 'aria-label': `Disponibilidade de ${product.name}` } }}
      />

      <Popover
        open={Boolean(anchorEl)}
        anchorEl={anchorEl}
        onClose={() => (isSaving ? undefined : setAnchorEl(null))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
        transformOrigin={{ vertical: 'top', horizontal: 'center' }}
      >
        <Box sx={{ p: 2, maxWidth: 260 }}>
          <Typography sx={{ fontWeight: 600, fontSize: 14, mb: 0.5 }}>Bloquear produto?</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 1.5 }}>
            "{product.name}" deixa de aparecer na venda e na loja online até ser desbloqueado.
          </Typography>
          {error ? (
            <Alert severity="error" sx={{ mb: 1.5 }}>
              {error}
            </Alert>
          ) : null}
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Button size="small" onClick={() => setAnchorEl(null)} disabled={isSaving}>
              Cancelar
            </Button>
            <Button
              size="small"
              variant="contained"
              color="error"
              onClick={() => void apply(false)}
              disabled={isSaving}
              startIcon={isSaving ? <CircularProgress size={14} color="inherit" /> : undefined}
            >
              Bloquear
            </Button>
          </Stack>
        </Box>
      </Popover>
    </>
  )
}
