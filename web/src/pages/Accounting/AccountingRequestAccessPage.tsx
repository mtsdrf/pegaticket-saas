import { Alert, Box, Button, Paper, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { AccountingStatusBadge } from '../../components/accounting/AccountingStatusBadge'
import * as accountingService from '../../services/accountingService'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { formatCpfCnpj } from '../../utils/cpfCnpj'
import { formatDateBR } from '../../utils/format'

export function AccountingRequestAccessPage() {
  const [links, setLinks] = useState<AccountingOfficeTenantLink[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [cnpj, setCnpj] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const load = useCallback(async () => {
    setIsLoading(true)
    setLoadError(null)
    try {
      setLinks(await accountingService.listMyLinks())
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar suas solicitações agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await accountingService.requestAccess({ tenant_cnpj: cnpj })
      setSuccessMessage('Solicitação enviada. A empresa precisa aprovar antes de você ver os dados.')
      setCnpj('')
      await load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível enviar a solicitação. Verifique o CNPJ.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box>
      <Typography sx={{ fontSize: { xs: 19, sm: 21 }, fontWeight: 700, mb: 0.5 }}>Solicitar acesso</Typography>
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2.5 }}>
        Informe o CNPJ da empresa. Ela recebe a solicitação e decide quais dados liberar.
      </Typography>

      <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 2.5 }, mb: 3 }}>
        <Box component="form" onSubmit={handleSubmit} noValidate>
          <Stack spacing={2}>
            {formError && (
              <Alert severity="error" variant="outlined" role="alert">
                {formError}
              </Alert>
            )}
            {successMessage && (
              <Alert severity="success" variant="outlined">
                {successMessage}
              </Alert>
            )}

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ alignItems: { sm: 'flex-start' } }}>
              <TextField
                label="CNPJ da empresa"
                value={cnpj}
                onChange={(event) => setCnpj(formatCpfCnpj(event.target.value))}
                error={Boolean(fieldErrors.tenant_cnpj?.[0])}
                helperText={fieldErrors.tenant_cnpj?.[0]}
                fullWidth
                required
              />
              <Button type="submit" variant="contained" size="large" disabled={isSubmitting} sx={{ minHeight: UI_SIZE.controlLarge, whiteSpace: 'nowrap' }}>
                {isSubmitting ? 'Enviando…' : 'Solicitar'}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </Paper>

      <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 1.5 }}>Minhas solicitações</Typography>

      {isLoading && (
        <Stack spacing={1.5}>
          <Skeleton variant="rounded" height={64} />
          <Skeleton variant="rounded" height={64} />
        </Stack>
      )}

      {!isLoading && loadError && (
        <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>
          {loadError}
        </Alert>
      )}

      {!isLoading && !loadError && links.length === 0 && (
        <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
          Você ainda não solicitou acesso a nenhuma empresa.
        </Typography>
      )}

      {!isLoading && !loadError && links.length > 0 && (
        <Stack spacing={1.5}>
          {links.map((link) => (
            <Paper
              key={link.uuid}
              variant="outlined"
              sx={{ ...ELEVATED_SURFACE_SX, p: 2, display: 'flex', alignItems: 'center', gap: 1.5 }}
            >
              <Box sx={{ flex: 1, minWidth: 0 }}>
                <Typography sx={{ fontSize: 15, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {link.tenant?.name ?? 'Empresa'}
                </Typography>
                {link.tenant?.cnpj && (
                  <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>CNPJ {link.tenant.cnpj}</Typography>
                )}
                {link.requested_at && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                    Solicitado em {formatDateBR(link.requested_at)}
                  </Typography>
                )}
              </Box>
              <AccountingStatusBadge status={link.status} />
            </Paper>
          ))}
        </Stack>
      )}
    </Box>
  )
}
