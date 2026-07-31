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
import * as accountingClientService from '../../services/accountingClientService'
import * as accountingService from '../../services/accountingService'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Client } from '../../types/client'
import { formatCpfCnpj, normalizeCpfCnpj } from '../../utils/cpfCnpj'

function ClientFiscalDialog({
  tenantUuid,
  client,
  onClose,
  onSaved,
}: {
  tenantUuid: string
  client: Client
  onClose: () => void
  onSaved: (client: Client) => void
}) {
  const [cpfCnpj, setCpfCnpj] = useState(client.cpf_cnpj ? formatCpfCnpj(client.cpf_cnpj) : '')
  const [ie, setIe] = useState(client.ie ?? '')
  const [ieIndicator, setIeIndicator] = useState(client.ie_indicator ?? 'nao_contribuinte')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSave() {
    setFieldErrors({})
    setFormError(null)
    setIsSubmitting(true)

    try {
      const updated = await accountingClientService.updateAccountingClientFiscal(tenantUuid, client.uuid, {
        cpf_cnpj: normalizeCpfCnpj(cpfCnpj) || undefined,
        ie: ie.trim() || undefined,
        ie_indicator: ieIndicator,
      })
      onSaved(updated)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar os dados fiscais do cliente agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Dados fiscais do cliente</DialogTitle>
      <DialogContent>
        <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
          Atualize somente os campos fiscais de <strong>{client.name}</strong>.
        </Typography>

        {formError && <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>{formError}</Alert>}

        <Stack spacing={2}>
          <TextField
            label="CPF/CNPJ"
            value={cpfCnpj}
            onChange={(event) => setCpfCnpj(formatCpfCnpj(event.target.value))}
            error={Boolean(fieldErrors.cpf_cnpj)}
            helperText={fieldErrors.cpf_cnpj?.[0]}
            slotProps={{ htmlInput: { maxLength: 18 } }}
          />
          <TextField
            label="Inscrição estadual"
            value={ie}
            onChange={(event) => setIe(event.target.value)}
            error={Boolean(fieldErrors.ie)}
            helperText={fieldErrors.ie?.[0]}
            slotProps={{ htmlInput: { maxLength: 30 } }}
          />
          <TextField
            select
            label="Indicador da IE"
            value={ieIndicator}
            onChange={(event) => setIeIndicator(event.target.value as 'contribuinte' | 'isento' | 'nao_contribuinte')}
            error={Boolean(fieldErrors.ie_indicator)}
            helperText={fieldErrors.ie_indicator?.[0]}
          >
            <MenuItem value="contribuinte">Contribuinte</MenuItem>
            <MenuItem value="isento">Isento</MenuItem>
            <MenuItem value="nao_contribuinte">Não contribuinte</MenuItem>
          </TextField>
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>Cancelar</Button>
        <Button variant="contained" onClick={() => void handleSave()} disabled={isSubmitting}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export function AccountingCompanyClientsPage() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const [link, setLink] = useState<AccountingOfficeTenantLink | null>(null)
  const [items, setItems] = useState<Client[]>([])
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [searchDraft, setSearchDraft] = useState('')
  const [pagination, setPagination] = useState({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editingClient, setEditingClient] = useState<Client | null>(null)

  const canWrite = Boolean(link?.scopes.includes('fiscal.write'))

  const load = useCallback(async () => {
    if (!tenantUuid) return
    setIsLoading(true)
    setError(null)
    try {
      const [links, result] = await Promise.all([
        accountingService.listMyLinks(),
        accountingClientService.listAccountingClients(tenantUuid, { q: search || undefined, page, per_page: 20 }),
      ])
      setLink(links.find((item) => item.tenant?.uuid === tenantUuid && item.status === 'approved') ?? null)
      setItems(result.items)
      setPagination(result.pagination)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar os clientes fiscais agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [page, search, tenantUuid])

  useEffect(() => {
    void load()
  }, [load])

  const emptyMessage = useMemo(() => (search ? 'Nenhum cliente encontrado para o filtro informado.' : 'Nenhum cliente cadastrado nesta empresa ainda.'), [search])

  return (
    <Box>
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
        O contador pode consultar e, quando liberado, manter os dados fiscais dos clientes da empresa.
      </Typography>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 360px) auto' }, gap: 1.5, mb: 2 }}>
        <TextField
          label="Buscar cliente"
          value={searchDraft}
          onChange={(event) => setSearchDraft(event.target.value)}
          placeholder="Nome, telefone ou cidade"
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchOutlinedIcon fontSize="small" /></InputAdornment> } }}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              setPage(1)
              setSearch(searchDraft.trim())
            }
          }}
        />
        <Box sx={{ display: 'flex', gap: 1, justifyContent: { xs: 'stretch', sm: 'flex-start' } }}>
          <Button variant="contained" onClick={() => { setPage(1); setSearch(searchDraft.trim()) }}>Buscar</Button>
          <Button variant="outlined" onClick={() => { setSearchDraft(''); setSearch(''); setPage(1) }}>Limpar</Button>
        </Box>
      </Box>

      {isLoading && <Stack spacing={1.5}><Skeleton variant="rounded" height={92} /><Skeleton variant="rounded" height={92} /></Stack>}
      {!isLoading && error && <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>{error}</Alert>}
      {!isLoading && !error && items.length === 0 && (
        <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 0.5 }}>{emptyMessage}</Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
            CPF/CNPJ, inscrição estadual e indicador da IE podem ser mantidos por aqui.
          </Typography>
        </Paper>
      )}

      {!isLoading && !error && items.length > 0 && (
        <>
          <Stack spacing={1.5}>
            {items.map((client) => (
              <Paper key={client.uuid} variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                  <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>{client.name}</Typography>
                    <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                      {client.endereco?.cidade_name ?? 'Cidade não informada'} · {client.phone_primary ?? 'Sem telefone'}
                    </Typography>
                    <Stack spacing={0.35} sx={{ mt: 1 }}>
                      <Typography sx={{ fontSize: 13 }}>CPF/CNPJ: {client.cpf_cnpj ? formatCpfCnpj(client.cpf_cnpj) : 'Não informado'}</Typography>
                      <Typography sx={{ fontSize: 13 }}>
                        IE: {client.ie || 'Não informada'} · Indicador: {client.ie_indicator ? client.ie_indicator.replaceAll('_', ' ') : 'Não informado'}
                      </Typography>
                    </Stack>
                  </Box>
                  {canWrite && (
                    <Tooltip title="Editar dados fiscais" arrow>
                      <IconButton
                        onClick={() => setEditingClient(client)}
                        aria-label={`Editar dados fiscais de ${client.name}`}
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
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>Página {pagination.current_page} de {pagination.last_page}</Typography>
            <Button variant="outlined" size="small" disabled={pagination.current_page <= 1} onClick={() => setPage((current) => current - 1)}>Anterior</Button>
            <Button variant="outlined" size="small" disabled={pagination.current_page >= pagination.last_page} onClick={() => setPage((current) => current + 1)}>Próxima</Button>
          </Stack>
        </>
      )}

      {tenantUuid && editingClient && (
        <ClientFiscalDialog
          tenantUuid={tenantUuid}
          client={editingClient}
          onClose={() => setEditingClient(null)}
          onSaved={(updatedClient) => {
            setItems((current) => current.map((item) => (item.uuid === updatedClient.uuid ? updatedClient : item)))
            setEditingClient(null)
          }}
        />
      )}
    </Box>
  )
}
