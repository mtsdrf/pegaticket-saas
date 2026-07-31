import { Box, List, ListItemButton, ListItemIcon, ListItemText, Paper } from '@mui/material'
import { Link as RouterLink, Outlet, useLocation } from 'react-router-dom'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { useSettingsDesktopLayout } from './blocks/useSettingsDesktopLayout'
import { useVisibleSettingsEntries } from './blocks/useVisibleSettingsEntries'

function isRailItemActive(to: string, pathname: string): boolean {
  return pathname === to || pathname.startsWith(`${to}/`)
}

/**
 * Layout do hub de Configurações (`/configuracoes/*`) — índice com
 * drill-down (decisão aprovada, 2026-07-24): em mobile só o `Outlet`
 * (índice ou bloco em página cheia, cada rota filha decide); em desktop
 * (≥1024px) rail fixo à esquerda com os mesmos blocos/entradas + o
 * conteúdo da rota filha à direita, trocando de painel via router sem
 * perder o rail.
 */
export function SettingsHubLayout() {
  const isDesktop = useSettingsDesktopLayout()
  const { blocks, links } = useVisibleSettingsEntries()
  const location = useLocation()

  if (!isDesktop) {
    return <Outlet />
  }

  return (
    <Box
      sx={{
        display: 'flex',
        gap: 3,
        alignItems: 'stretch',
        maxWidth: 1600,
        mx: 'auto',
        // O formulário do bloco selecionado deve ocupar todo o espaço até o
        // fim da tela (não só a altura do próprio conteúdo) — mesma altura
        // mínima que o rail (sticky) já ocupa visualmente. 200px cobre a
        // Toolbar espaçadora + padding vertical de `main` (AppLayout).
        minHeight: 'calc(100dvh - 200px)',
      }}
    >
      <Paper
        variant="outlined"
        sx={{
          width: 280,
          flexShrink: 0,
          ...ELEVATED_SURFACE_SX,
            background: 'var(--pt-surface-raised-bg)',
          overflow: 'hidden',
          position: 'sticky',
          top: 88,
          alignSelf: 'flex-start',
        }}
      >
        <List sx={{ py: 1 }}>
          {blocks.map((block) => {
            const to = `/configuracoes/${block.path}`
            const active = isRailItemActive(to, location.pathname)
            return (
              <ListItemButton key={block.key} component={RouterLink} to={to} selected={active} sx={{ minHeight: UI_SIZE.navItem, borderRadius: UI_RADIUS.md, mx: 0.75, mb: 0.5 }}>
                <ListItemIcon sx={{ color: active ? undefined : 'var(--pt-muted)', minWidth: 40 }}>
                  <block.icon fontSize="small" />
                </ListItemIcon>
                <ListItemText
                  primary={block.label}
                  slotProps={{ primary: { sx: { fontSize: 14, fontWeight: active ? 600 : 500 } } }}
                />
              </ListItemButton>
            )
          })}
          {links.map((link) => {
            const active = isRailItemActive(link.to, location.pathname)
            return (
              <ListItemButton key={link.key} component={RouterLink} to={link.to} selected={active} sx={{ minHeight: UI_SIZE.navItem, borderRadius: UI_RADIUS.md, mx: 0.75, mb: 0.5 }}>
                <ListItemIcon sx={{ color: active ? undefined : 'var(--pt-muted)', minWidth: 40 }}>
                  <link.icon fontSize="small" />
                </ListItemIcon>
                <ListItemText
                  primary={link.label}
                  slotProps={{ primary: { sx: { fontSize: 14, fontWeight: active ? 600 : 500 } } }}
                />
              </ListItemButton>
            )
          })}
        </List>
      </Paper>

      <Box sx={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
        <Outlet />
      </Box>
    </Box>
  )
}
