import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, Box, Button, Skeleton, Stack, Tab, Tabs, Typography } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { Link as RouterLink, Outlet, useLocation, useNavigate, useParams } from 'react-router-dom'
import * as accountingService from '../../services/accountingService'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { getApiErrorMessage } from '../../types/api'

/**
 * Contexto de UMA empresa (tenant) selecionada pelo contador. O `tenant_uuid`
 * ativo vem do path param (`/contador/empresas/:tenantUuid`) — não há estado
 * global de "empresa ativa": a URL É o estado, mais RESTful e compartilhável.
 * Valida localmente que o vínculo está aprovado antes de renderizar as abas
 * (o backend também valida via `ResolveAccountingTenant`, isto é só UX).
 */
export function AccountingCompanyLayout() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const [link, setLink] = useState<AccountingOfficeTenantLink | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setIsLoading(true)
    setError(null)
    try {
      const links = await accountingService.listMyLinks()
      const match = links.find((item) => item.tenant?.uuid === tenantUuid && item.status === 'approved')
      if (!match) {
        setError('Você não tem acesso aprovado a esta empresa. Ela pode ter revogado o acesso.')
        setLink(null)
      } else {
        setLink(match)
      }
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar os dados desta empresa agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [tenantUuid])

  useEffect(() => {
    void load()
  }, [load])

  const subTab = location.pathname.endsWith('/pendencias')
    ? `/contador/empresas/${tenantUuid}/pendencias`
    : location.pathname.endsWith('/produtos-fiscais')
      ? `/contador/empresas/${tenantUuid}/produtos-fiscais`
      : location.pathname.endsWith('/clientes-fiscais')
        ? `/contador/empresas/${tenantUuid}/clientes-fiscais`
        : location.pathname.endsWith('/regras-tributarias')
          ? `/contador/empresas/${tenantUuid}/regras-tributarias`
    : `/contador/empresas/${tenantUuid}`

  const canViewFiscal = Boolean(link?.scopes.includes('fiscal.read') || link?.scopes.includes('fiscal.write'))

  return (
    <Box>
      <Button
        component={RouterLink}
        to="/contador/empresas"
        startIcon={<ArrowBackIcon fontSize="small" />}
        size="small"
        sx={{ mb: 1.5, color: 'var(--mk-muted)' }}
      >
        Empresas
      </Button>

      {isLoading && <Skeleton variant="rounded" height={120} />}

      {!isLoading && error && (
        <Alert
          severity="warning"
          variant="outlined"
          action={
            <Button size="small" onClick={() => navigate('/contador/empresas')}>
              Voltar
            </Button>
          }
        >
          {error}
        </Alert>
      )}

      {!isLoading && !error && link && (
        <>
          <Typography sx={{ fontSize: { xs: 20, sm: 22 }, fontWeight: 700 }}>
            {link.tenant?.name ?? 'Empresa'}
          </Typography>
          {link.tenant?.cnpj && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 1.5 }}>CNPJ {link.tenant.cnpj}</Typography>
          )}

          <Tabs
            value={subTab}
            sx={{
              mb: 2.5,
              minHeight: 40,
              '& .MuiTab-root': { textTransform: 'none', fontWeight: 600, fontSize: 14, minHeight: 40 },
            }}
          >
            <Tab component={RouterLink} to={`/contador/empresas/${tenantUuid}`} value={`/contador/empresas/${tenantUuid}`} label="Relatórios" />
            {canViewFiscal && (
              <Tab component={RouterLink} to={`/contador/empresas/${tenantUuid}/produtos-fiscais`} value={`/contador/empresas/${tenantUuid}/produtos-fiscais`} label="Produtos fiscais" />
            )}
            {canViewFiscal && (
              <Tab component={RouterLink} to={`/contador/empresas/${tenantUuid}/clientes-fiscais`} value={`/contador/empresas/${tenantUuid}/clientes-fiscais`} label="Clientes fiscais" />
            )}
            {canViewFiscal && (
              <Tab component={RouterLink} to={`/contador/empresas/${tenantUuid}/regras-tributarias`} value={`/contador/empresas/${tenantUuid}/regras-tributarias`} label="Regras tributárias" />
            )}
            <Tab
              component={RouterLink}
              to={`/contador/empresas/${tenantUuid}/pendencias`}
              value={`/contador/empresas/${tenantUuid}/pendencias`}
              label="Pendências"
            />
          </Tabs>

          <Stack spacing={2.5}>
            <Outlet />
          </Stack>
        </>
      )}
    </Box>
  )
}
