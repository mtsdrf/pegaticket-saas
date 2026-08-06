import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Box, Button, Divider, Paper, Stack, Typography } from '@mui/material'
import { FormSection } from '../../components/form/FormSection'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatDateBR } from '../../utils/format'
import { PortalShell } from './PortalShell'

function LinkedStoresEmpty() {
  return (
    <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
      Nenhuma loja vinculada ainda. Abra o link de rastreio de uma compra para vincular a primeira.
    </Typography>
  )
}

export function PortalProfilePage() {
  const { customer, logout } = usePortalAuth()

  return (
    <PortalShell title="Perfil" subtitle="Seus dados e as lojas vinculadas à sua conta PegaTicket.">
      <Stack spacing={2.5}>
        <FormSection
          title="Dados da conta"
          description="Resumo dos dados principais vinculados ao seu acesso no portal do cliente."
        >
          <Box sx={{ width: '100%' }}>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nome</Typography>
            <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 1.25 }}>
              {customer?.name ?? 'Não informado'}
            </Typography>

            <Divider sx={{ mb: 1.25 }} />

            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>E-mail</Typography>
            <Typography sx={{ fontSize: 15, fontWeight: 600, wordBreak: 'break-word' }}>
              {customer?.email}
            </Typography>
          </Box>
        </FormSection>

        <FormSection
          title="Lojas vinculadas"
          description="Essas lojas ficaram associadas à sua conta a partir dos links de rastreio e compras confirmadas."
        >
          {(!customer || customer.linked_tenants.length === 0) && <LinkedStoresEmpty />}

          {customer && customer.linked_tenants.length > 0 && (
            <Stack spacing={1.25}>
              {customer.linked_tenants.map((store) => (
                <Paper
                  key={`${store.tenant_name}-${store.confirmed_at}`}
                  elevation={0}
                  sx={{
                    ...SOFT_PANEL_SX,
                    p: 1.5,
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: 1.25,
                  }}
                >
                  <StorefrontOutlinedIcon sx={{ color: 'var(--pt-muted)', mt: 0.25, flexShrink: 0 }} />
                  <Box sx={{ minWidth: 0 }}>
                    <Typography sx={{ fontSize: 14.5, fontWeight: 700, wordBreak: 'break-word' }}>
                      {store.tenant_name}
                    </Typography>
                    <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', mt: 0.25 }}>
                      Vinculado em {formatDateBR(store.confirmed_at)}
                    </Typography>
                  </Box>
                </Paper>
              ))}
            </Stack>
          )}
        </FormSection>

        <Button onClick={logout} variant="outlined" color="inherit" size="large">
          Sair da conta
        </Button>
      </Stack>
    </PortalShell>
  )
}
