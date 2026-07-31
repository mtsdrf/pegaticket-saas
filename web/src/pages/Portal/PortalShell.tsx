import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import { Box, IconButton, Stack, Tab, Tabs, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Link as RouterLink, useLocation } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

interface PortalShellProps {
  title: string
  subtitle?: string
  children: ReactNode
}

/**
 * Layout compartilhado das telas autenticadas do portal (`/portal/pedidos`,
 * `/portal/favoritos`, `/portal/perfil`) — mesma linguagem visual do
 * rastreio público (`OrderTrackingPage`) e do login de staff, mas com
 * navegação simples entre as seções (mobile-first: abas em vez de sidebar).
 */
export function PortalShell({ title, subtitle, children }: PortalShellProps) {
  const { logout } = usePortalAuth()
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
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 480 }}>
        <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 2.5 }}>
          <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center' }}>
            <Logo variant="mark" size={34} />
            <Typography sx={{ fontSize: 15, fontWeight: 700 }}>Meu Maskats</Typography>
          </Stack>
          <IconButton onClick={logout} aria-label="Sair da conta" size="small">
            <LogoutOutlinedIcon fontSize="small" />
          </IconButton>
        </Stack>

        <Tabs
          value={location.pathname}
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
            to="/portal/pedidos"
            value="/portal/pedidos"
            label="Meus pedidos"
            icon={<ReceiptLongOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
          <Tab
            component={RouterLink}
            to="/portal/favoritos"
            value="/portal/favoritos"
            label="Favoritos"
            icon={<FavoriteBorderOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
          <Tab
            component={RouterLink}
            to="/portal/vouchers"
            value="/portal/vouchers"
            label="Vouchers"
            icon={<ConfirmationNumberOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
          <Tab
            component={RouterLink}
            to="/portal/perfil"
            value="/portal/perfil"
            label="Perfil"
            icon={<PersonOutlineOutlinedIcon fontSize="small" />}
            iconPosition="start"
          />
        </Tabs>

        <Typography sx={{ fontSize: { xs: 19, sm: 21 }, fontWeight: 700, mb: 0.5 }}>{title}</Typography>
        {subtitle && (
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2.5 }}>{subtitle}</Typography>
        )}

        <Box sx={{ mt: subtitle ? 0 : 2.5 }}>{children}</Box>
      </Box>
    </Box>
  )
}
