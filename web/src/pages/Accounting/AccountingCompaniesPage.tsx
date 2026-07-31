import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import { Alert, Box, Button, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import * as accountingService from '../../services/accountingService'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { getApiErrorMessage } from '../../types/api'

export function AccountingCompaniesPage() {
  const navigate = useNavigate()
  const [links, setLinks] = useState<AccountingOfficeTenantLink[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setIsLoading(true)
    setError(null)
    try {
      setLinks(await accountingService.listMyLinks())
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar suas empresas agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const approved = links.filter((link) => link.status === 'approved')

  return (
    <Box>
      <Typography sx={{ fontSize: { xs: 19, sm: 21 }, fontWeight: 700, mb: 0.5 }}>Empresas</Typography>
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2.5 }}>
        Empresas que autorizaram o acesso do seu escritório. Toque em uma para ver os relatórios.
      </Typography>

      {isLoading && (
        <Stack spacing={1.5}>
          <Skeleton variant="rounded" height={72} />
          <Skeleton variant="rounded" height={72} />
        </Stack>
      )}

      {!isLoading && error && (
        <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>
          {error}
        </Alert>
      )}

      {!isLoading && !error && approved.length === 0 && (
        <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 0.5 }}>Nenhuma empresa liberada ainda</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
            Solicite acesso pelo CNPJ da empresa. Ela precisa aprovar antes de você ver os dados.
          </Typography>
          <Button component={RouterLink} to="/contador/solicitar-acesso" variant="contained">
            Solicitar acesso
          </Button>
        </Paper>
      )}

      {!isLoading && !error && approved.length > 0 && (
        <Stack spacing={1.5}>
          {approved.map((link) => (
            <Paper
              key={link.uuid}
              variant="outlined"
              onClick={() => link.tenant && navigate(`/contador/empresas/${link.tenant.uuid}`)}
              sx={{
                ...ELEVATED_SURFACE_SX,
                p: 2,
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: 1.5,
                transition: 'border-color 0.15s',
                '&:hover': { borderColor: 'var(--mk-primary)' },
              }}
            >
              <Box sx={{ flex: 1, minWidth: 0 }}>
                <Typography sx={{ fontSize: 15, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {link.tenant?.name ?? 'Empresa'}
                </Typography>
                {link.tenant?.cnpj && (
                  <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>CNPJ {link.tenant.cnpj}</Typography>
                )}
                <Stack direction="row" spacing={0.75} sx={{ mt: 0.75, flexWrap: 'wrap', gap: 0.5 }}>
                  {link.scopes.map((scope) => (
                    <Typography
                      key={scope}
                      sx={{ ...SOFT_PANEL_SX, fontSize: 11, color: 'var(--mk-muted)', px: 0.75, py: 0.25, borderRadius: 1 }}
                    >
                      {scope}
                    </Typography>
                  ))}
                </Stack>
              </Box>
              <ChevronRightIcon sx={{ color: 'var(--mk-muted)' }} />
            </Paper>
          ))}
        </Stack>
      )}
    </Box>
  )
}
