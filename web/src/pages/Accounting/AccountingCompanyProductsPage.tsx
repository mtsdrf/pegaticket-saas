import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  InputAdornment,
  MenuItem,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import * as accountingProductService from '../../services/accountingProductService'
import * as accountingService from '../../services/accountingService'
import { FORM_GRID_2_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Product } from '../../types/product'

interface ProductFiscalFormState {
  ncm: string
  cest: string
  origin: string
  default_cfop: string
  csosn_cst: string
}

function ProductFiscalDialog({
  tenantUuid,
  product,
  onClose,
  onSaved,
}: {
  tenantUuid: string
  product: Product
  onClose: () => void
  onSaved: (product: Product) => void
}) {
  const [form, setForm] = useState<ProductFiscalFormState>({
    ncm: product.ncm ?? '',
    cest: product.cest ?? '',
    origin: product.origin ?? '',
    default_cfop: product.default_cfop ?? '',
    csosn_cst: product.csosn_cst ?? '',
  })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSave() {
    setFieldErrors({})
    setFormError(null)
    setIsSubmitting(true)

    try {
      const updated = await accountingProductService.updateAccountingProductFiscal(tenantUuid, product.uuid, {
        ncm: form.ncm.trim() || undefined,
        cest: form.cest.trim() || undefined,
        origin: form.origin || undefined,
        default_cfop: form.default_cfop.trim() || undefined,
        csosn_cst: form.csosn_cst.trim() || undefined,
      })
      onSaved(updated)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar os dados fiscais do produto agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Dados fiscais do produto</DialogTitle>
      <DialogContent>
        <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
          Atualize somente os campos fiscais de <strong>{product.name}</strong>.
        </Typography>

        {formError && (
          <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>
            {formError}
          </Alert>
        )}

        <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
          <TextField
            label="NCM"
            value={form.ncm}
            onChange={(event) => setForm((current) => ({ ...current, ncm: event.target.value.replace(/\D/g, '').slice(0, 8) }))}
            error={Boolean(fieldErrors.ncm)}
            helperText={fieldErrors.ncm?.[0] ?? '8 dígitos.'}
            inputMode="numeric"
            slotProps={{ htmlInput: { maxLength: 8 } }}
          />
          <TextField
            label="CEST"
            value={form.cest}
            onChange={(event) => setForm((current) => ({ ...current, cest: event.target.value.slice(0, 10) }))}
            error={Boolean(fieldErrors.cest)}
            helperText={fieldErrors.cest?.[0] ?? 'Opcional.'}
            slotProps={{ htmlInput: { maxLength: 10 } }}
          />
          <TextField
            select
            label="Origem"
            value={form.origin}
            onChange={(event) => setForm((current) => ({ ...current, origin: event.target.value }))}
            error={Boolean(fieldErrors.origin)}
            helperText={fieldErrors.origin?.[0] ?? 'Código fiscal da origem do item.'}
          >
            <MenuItem value="">Não definido</MenuItem>
            <MenuItem value="0">0 - Nacional</MenuItem>
            <MenuItem value="1">1 - Estrangeira importação direta</MenuItem>
            <MenuItem value="2">2 - Estrangeira adquirida no mercado interno</MenuItem>
            <MenuItem value="3">3 - Nacional com conteúdo de importação acima de 40%</MenuItem>
            <MenuItem value="4">4 - Nacional produzida conforme PPB</MenuItem>
            <MenuItem value="5">5 - Nacional com conteúdo de importação até 40%</MenuItem>
            <MenuItem value="6">6 - Estrangeira importação direta sem similar nacional</MenuItem>
            <MenuItem value="7">7 - Estrangeira mercado interno sem similar nacional</MenuItem>
            <MenuItem value="8">8 - Nacional com conteúdo de importação acima de 70%</MenuItem>
          </TextField>
          <TextField
            label="CFOP padrão"
            value={form.default_cfop}
            onChange={(event) => setForm((current) => ({ ...current, default_cfop: event.target.value.slice(0, 10) }))}
            error={Boolean(fieldErrors.default_cfop)}
            helperText={fieldErrors.default_cfop?.[0] ?? 'Ex.: 5102'}
            slotProps={{ htmlInput: { maxLength: 10 } }}
          />
          <TextField
            label="CSOSN / CST"
            value={form.csosn_cst}
            onChange={(event) => setForm((current) => ({ ...current, csosn_cst: event.target.value.slice(0, 10) }))}
            error={Boolean(fieldErrors.csosn_cst)}
            helperText={fieldErrors.csosn_cst?.[0] ?? 'Conforme regime tributário da empresa.'}
            slotProps={{ htmlInput: { maxLength: 10 } }}
          />
        </Box>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={() => void handleSave()} disabled={isSubmitting}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export function AccountingCompanyProductsPage() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const [link, setLink] = useState<AccountingOfficeTenantLink | null>(null)
  const [items, setItems] = useState<Product[]>([])
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [searchDraft, setSearchDraft] = useState('')
  const [pagination, setPagination] = useState({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)

  const canWrite = Boolean(link?.scopes.includes('fiscal.write'))

  const loadLink = useCallback(async () => {
    const links = await accountingService.listMyLinks()
    return links.find((item) => item.tenant?.uuid === tenantUuid && item.status === 'approved') ?? null
  }, [tenantUuid])

  const loadProducts = useCallback(async () => {
    if (!tenantUuid) return
    setIsLoading(true)
    setError(null)
    try {
      const [resolvedLink, result] = await Promise.all([
        loadLink(),
        accountingProductService.listAccountingProducts(tenantUuid, { q: search || undefined, page, per_page: 20 }),
      ])
      setLink(resolvedLink)
      setItems(result.items)
      setPagination(result.pagination)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar os produtos fiscais agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [loadLink, page, search, tenantUuid])

  useEffect(() => {
    void loadProducts()
  }, [loadProducts])

  const emptyStateMessage = useMemo(() => {
    if (search) {
      return 'Nenhum produto encontrado para o filtro informado.'
    }
    return 'Nenhum produto cadastrado nesta empresa ainda.'
  }, [search])

  return (
    <Box>
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
        O contador pode consultar e, quando liberado, preencher apenas os dados fiscais dos produtos da empresa.
      </Typography>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 360px) auto' }, gap: 1.5, mb: 2 }}>
        <TextField
          label="Buscar produto"
          value={searchDraft}
          onChange={(event) => setSearchDraft(event.target.value)}
          placeholder="Nome, tipo ou categoria"
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <SearchOutlinedIcon fontSize="small" />
                </InputAdornment>
              ),
            },
          }}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              setPage(1)
              setSearch(searchDraft.trim())
            }
          }}
        />
        <Box sx={{ display: 'flex', gap: 1, justifyContent: { xs: 'stretch', sm: 'flex-start' } }}>
          <Button
            variant="contained"
            onClick={() => {
              setPage(1)
              setSearch(searchDraft.trim())
            }}
          >
            Buscar
          </Button>
          <Button
            variant="outlined"
            onClick={() => {
              setSearchDraft('')
              setSearch('')
              setPage(1)
            }}
          >
            Limpar
          </Button>
        </Box>
      </Box>

      {isLoading && (
        <Stack spacing={1.5}>
          <Skeleton variant="rounded" height={92} />
          <Skeleton variant="rounded" height={92} />
        </Stack>
      )}

      {!isLoading && error && (
        <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void loadProducts()}>Tentar de novo</Button>}>
          {error}
        </Alert>
      )}

      {!isLoading && !error && items.length === 0 && (
        <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 0.5 }}>{emptyStateMessage}</Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
            Cadastros fiscais como NCM, CEST, origem e CFOP podem ser preenchidos por aqui.
          </Typography>
        </Paper>
      )}

      {!isLoading && !error && items.length > 0 && (
        <>
          <Stack spacing={1.5}>
            {items.map((product) => (
              <Paper
                key={product.uuid}
                variant="outlined"
                sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}
              >
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                  <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>{product.name}</Typography>
                    <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                      {product.product_type?.product_category?.name ? `${product.product_type.product_category.name} · ` : ''}
                      {product.product_type?.name ?? 'Sem tipo'}
                    </Typography>
                    <Stack spacing={0.35} sx={{ mt: 1 }}>
                      <Typography sx={{ fontSize: 13 }}>
                        NCM: {product.ncm || 'Não informado'} · CEST: {product.cest || 'Não informado'}
                      </Typography>
                      <Typography sx={{ fontSize: 13 }}>
                        Origem: {product.origin || 'Não informada'} · CFOP: {product.default_cfop || 'Não informado'}
                      </Typography>
                      <Typography sx={{ fontSize: 13 }}>
                        CSOSN/CST: {product.csosn_cst || 'Não informado'}
                      </Typography>
                    </Stack>
                  </Box>

                  {canWrite && (
                    <Tooltip title="Editar dados fiscais" arrow>
                      <IconButton
                        onClick={() => setEditingProduct(product)}
                        aria-label={`Editar dados fiscais de ${product.name}`}
                        sx={{ alignSelf: { xs: 'flex-end', md: 'flex-start' }, minWidth: UI_SIZE.iconButton, minHeight: UI_SIZE.iconButton }}
                      >
                        <EditOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                </Stack>
              </Paper>
            ))}
          </Stack>

          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end', alignItems: 'center', mt: 2 }}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
              Página {pagination.current_page} de {pagination.last_page}
            </Typography>
            <Button variant="outlined" size="small" disabled={pagination.current_page <= 1} onClick={() => setPage((current) => current - 1)}>
              Anterior
            </Button>
            <Button
              variant="outlined"
              size="small"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Próxima
            </Button>
          </Stack>
        </>
      )}

      {tenantUuid && editingProduct && (
        <ProductFiscalDialog
          tenantUuid={tenantUuid}
          product={editingProduct}
          onClose={() => setEditingProduct(null)}
          onSaved={(updatedProduct) => {
            setItems((current) => current.map((item) => (item.uuid === updatedProduct.uuid ? updatedProduct : item)))
            setEditingProduct(null)
          }}
        />
      )}
    </Box>
  )
}
