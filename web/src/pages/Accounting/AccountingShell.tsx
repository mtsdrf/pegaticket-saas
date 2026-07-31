import AddBusinessOutlinedIcon from '@mui/icons-material/AddBusinessOutlined'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import { Box, IconButton, Stack, Tab, Tabs, Typography } from '@mui/material'
import { Link as RouterLink, Outlet, useLocation } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { useAccountingAuth } from '../../hooks/useAccountingAuth'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

/**
 * Layout das telas autenticadas do módulo do contador (`/contador/*`). Mesmo
 * espírito de `PortalShell` (mobile-first, abas em vez de sidebar, tokens
 * `--mk-*`), mas identidade `AccountingOffice`.
 *
 * As pendências (central de mensagens) são POR EMPRESA — só existem dentro do
 * contexto de um tenant com vínculo aprovado (`/contador/empresas/:uuid/pendencias`),
 * por isso a navegação de topo tem só "Empresas" e "Solicitar acesso"; as
 * pendências aparecem como aba dentro da empresa selecionada.
 */
function activeTab(pathname: string): string {
  if (pathname.startsWith('/contador/solicitar-acesso')) return '/contador/solicitar-acesso'
  return '/contador/empresas'
}

export function AccountingShell() {
  const { office, logout } = useAccountingAuth()
  const location = useLocation()

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        background:
          'var(--mk-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 720 }}>
        <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 2.5 }}>
          <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', minWidth: 0 }}>
            <Logo variant="mark" size={34} />
            <Box sx={{ minWidth: 0 }}>
              <Typography sx={{ fontSize: 15, fontWeight: 700, lineHeight: 1.2 }}>Maskats Contador</Typography>
              {office && (
                <Typography
                  sx={{ fontSize: 12.5, color: 'var(--mk-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                >
                  {office.company_name}
                </Typography>
              )}
            </Box>
          </Stack>
          <IconButton onClick={logout} aria-label="Sair da conta do contador" size="small">
            <LogoutOutlinedIcon fontSize="small" />
          </IconButton>
        </Stack>

        <Tabs
          value={activeTab(location.pathname)}
          variant="scrollable"
          scrollButtons="auto"
          allowScrollButtonsMobile
          sx={{
            mb: 2.5,
            minHeight: UI_SIZE.navItem,
            ...SOFT_PANEL_SX,
            p: 0.5,
            '& .MuiTabs-indicator': { display: 'none' },
            '& .MuiTab-root': {
              minHeight: UI_SIZE.compactControl,
              borderRadius: UI_RADIUS.sm,
              textTransform: 'none',
              fontWeight: 600,
              fontSize: 13.5,
              minWidth: 0,
            },
            '& .Mui-selected': {
              bgcolor: 'var(--mk-primary)',
              color: '#FFFFFF !important',
            },
          }}
        >
          <Tab
            component={RouterLink}
            to="/contador/empresas"
            value="/contador/empresas"
            label="Empresas"
            icon={<ApartmentOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
          <Tab
            component={RouterLink}
            to="/contador/solicitar-acesso"
            value="/contador/solicitar-acesso"
            label="Solicitar acesso"
            icon={<AddBusinessOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
        </Tabs>

        <Outlet />
      </Box>
    </Box>
  )
}
