import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Box, Button, Divider, Paper, Stack, Typography } from '@mui/material'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatDateBR } from '../../utils/format'
import { PortalShell } from './PortalShell'

function LinkedStoresEmpty() {
  return (
    <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
      Nenhuma loja vinculada ainda. Abra o link de rastreio de um pedido para vincular a primeira.
    </Typography>
  )
}

export function PortalProfilePage() {
  const { customer, logout } = usePortalAuth()

  return (
    <PortalShell title="Perfil" subtitle="Seus dados e as lojas vinculadas à sua conta Maskats.">
      <Stack spacing={2.5}>
        <Paper
          elevation={0}
          sx={{
            ...ELEVATED_SURFACE_SX,
            p: 2,
          }}
        >
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>Nome</Typography>
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 1.25 }}>
            {customer?.name ?? 'Não informado'}
          </Typography>

          <Divider sx={{ mb: 1.25 }} />

          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>E-mail</Typography>
          <Typography sx={{ fontSize: 15, fontWeight: 600, wordBreak: 'break-word' }}>{customer?.email}</Typography>
        </Paper>

        <Box>
          <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 1 }}>Lojas vinculadas</Typography>

          {(!customer || customer.linked_stores.length === 0) && <LinkedStoresEmpty />}

          {customer && customer.linked_stores.length > 0 && (
            <Stack spacing={1.25}>
              {customer.linked_stores.map((store) => (
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
                  <StorefrontOutlinedIcon sx={{ color: 'var(--mk-muted)', mt: 0.25, flexShrink: 0 }} />
                  <Box sx={{ minWidth: 0 }}>
                    <Typography sx={{ fontSize: 14.5, fontWeight: 700, wordBreak: 'break-word' }}>
                      {store.tenant_name}
                    </Typography>
                    <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', wordBreak: 'break-word' }}>
                      Cadastrado como {store.client_name}
                    </Typography>
                    <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)', mt: 0.25 }}>
                      Vinculado em {formatDateBR(store.confirmed_at)}
                    </Typography>
                  </Box>
                </Paper>
              ))}
            </Stack>
          )}
        </Box>

        <Button onClick={logout} variant="outlined" color="inherit" size="large">
          Sair da conta
        </Button>
      </Stack>
    </PortalShell>
  )
}
