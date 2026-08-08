import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import InsightsOutlinedIcon from '@mui/icons-material/InsightsOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import LocalActivityOutlinedIcon from '@mui/icons-material/LocalActivityOutlined'
import ManageAccountsOutlinedIcon from '@mui/icons-material/ManageAccountsOutlined'
import MenuIcon from '@mui/icons-material/Menu'
import GroupOutlinedIcon from '@mui/icons-material/GroupOutlined'
import PersonAddAltOutlinedIcon from '@mui/icons-material/PersonAddAltOutlined'
import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import CardGiftcardOutlinedIcon from '@mui/icons-material/CardGiftcardOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined'
import SettingsOutlinedIcon from '@mui/icons-material/SettingsOutlined'
import SupportAgentOutlinedIcon from '@mui/icons-material/SupportAgentOutlined'
import type { SvgIconComponent } from '@mui/icons-material'
import {
  AppBar,
  Box,
  Collapse,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Tooltip,
} from '@mui/material'
import { useMemo, useState } from 'react'
import { Link as RouterLink, Outlet, useLocation } from 'react-router-dom'
import { ACCESS } from '../access/requirements'
import { ReleaseNotesMenu } from '../components/ReleaseNotesMenu'
import { UserMenu } from '../components/UserMenu'
import { InstallPrompt } from '../components/pwa/InstallPrompt'
import { CommandPalette, type CommandPaletteItem } from '../components/search/CommandPalette'
import { TenantSelectModal } from '../components/tenant/TenantSelectModal'
import { Logo } from '../components/ui/Logo'
import { useAccessControl } from '../hooks/useAccessControl'
import { useAuth } from '../hooks/useAuth'
import { UI_RADIUS, UI_SIZE } from '../styles/layoutStandards'
import { isOwnerRole } from '../utils/tenantRole'
import type { PermissionRequirement } from '../types/access'

const DRAWER_WIDTH = 296

interface NavChild {
  to: string
  label: string
  exact?: boolean
  access?: PermissionRequirement
  ownerOnly?: boolean
  hideForOwner?: boolean
}

/**
 * Item flat (link direto) ou grupo expansível (`children`) — mesmo padrão
 * de navegação do sistema legado (`Sidebar.js`: "Eventos"/"Relatórios" como
 * dropdown agrupando sub-telas relacionadas). Novos módulos que ainda não
 * existem no `web/` (Operação, Relatórios...) entram como grupo
 * aqui quando forem implementados, não como item flat.
 */
type NavItem =
  | {
      kind: 'link'
      to: string
      label: string
      icon: SvgIconComponent
      exact?: boolean
      access?: PermissionRequirement
      ownerOnly?: boolean
      hideForOwner?: boolean
      /** Dono da empresa enxerga o item mesmo sem a permissão de `access` atribuída via grupo (vínculo de dono não passa por permissão de grupo). */
      ownerBypassesAccess?: boolean
    }
  | { kind: 'group'; label: string; icon: SvgIconComponent; children: NavChild[] }

const NAV_ITEMS: NavItem[] = [
  // Sem `access`: a Visão geral é sempre acessível (é o destino de fallback de
  // qualquer permissão negada) — a própria página decide o que mostrar
  // conforme a permissão `dashboard:read` do usuário.
  { kind: 'link', to: '/', label: 'Visão geral', icon: DashboardOutlinedIcon, exact: true },
  {
    kind: 'group',
    label: 'Eventos',
    icon: Inventory2OutlinedIcon,
    children: [
      { to: '/eventos/categorias', label: 'Categorias de evento', access: ACCESS.eventCategoriesRead },
      { to: '/eventos', label: 'Eventos', exact: true, access: ACCESS.eventsRead },
      { to: '/tipos-de-ingresso', label: 'Tipos de ingresso', access: ACCESS.ticketTypesRead },
      { to: '/ingressos', label: 'Ingressos emitidos', access: ACCESS.ticketsRead },
      { to: '/adicionais', label: 'Adicionais', access: ACCESS.eventProductsRead },
      { to: '/locais', label: 'Locais e mapa', access: ACCESS.venuesRead },
    ],
  },
  { kind: 'link', to: '/portaria', label: 'Portaria', icon: QrCodeScannerOutlinedIcon, access: ACCESS.ticketsCheckin },
  { kind: 'link', to: '/vendas-manuais', label: 'Vendas manuais', icon: ReceiptLongOutlinedIcon, access: ACCESS.salesRead },
  { kind: 'link', to: '/caixa', label: 'Caixa', icon: PointOfSaleOutlinedIcon, access: ACCESS.cashSessionsRead },
  { kind: 'link', to: '/listas-de-convidados', label: 'Listas de convidados', icon: CardGiftcardOutlinedIcon, access: ACCESS.eventsRead },
  { kind: 'link', to: '/vendas-online', label: 'Vendas Online', icon: LocalActivityOutlinedIcon, access: ACCESS.storefrontSalesRead },
  { kind: 'link', to: '/afiliados', label: 'Afiliados', icon: PersonAddAltOutlinedIcon, access: ACCESS.affiliatesRead },
  { kind: 'link', to: '/clientes', label: 'Clientes', icon: GroupOutlinedIcon, access: ACCESS.customersRead },
  {
    kind: 'group',
    label: 'Relatórios',
    icon: InsightsOutlinedIcon,
    children: [
      { to: '/analises', label: 'Análises', access: ACCESS.reportsRead },
      { to: '/relatorios-personalizados', label: 'Relatórios personalizados', access: ACCESS.customReportsRead },
      { to: '/relatorios/canais', label: 'Resultado por canal', access: ACCESS.reportsRead },
      { to: '/relatorios/vendas', label: 'Relatório de vendas', access: ACCESS.reportsRead },
      { to: '/financeiro/operacao', label: 'Operação financeira', access: ACCESS.financeRead },
      { to: '/financeiro/conciliacao', label: 'Conciliação financeira', access: ACCESS.financeRead },
    ],
  },
  {
    kind: 'group',
    label: 'Administração',
    icon: ManageAccountsOutlinedIcon,
    children: [
      { to: '/admin/usuarios', label: 'Usuários', access: ACCESS.adminUsersRead },
      { to: '/admin/grupos', label: 'Grupos', access: ACCESS.adminGroupsRead },
      { to: '/admin/funcionalidades', label: 'Funcionalidades', access: ACCESS.adminFunctionalitiesRead },
      { to: '/admin/planos', label: 'Planos', access: ACCESS.adminPlansRead },
      { to: '/admin/tenants', label: 'Empresas', access: ACCESS.adminTenantsRead },
      { to: '/admin/tenant-roles', label: 'Perfis da empresa', access: ACCESS.tenantRolesRead },
      { to: '/admin/tenant-users', label: 'Usuários da empresa', access: ACCESS.tenantUsersRead },
      { to: '/admin/auditoria', label: 'Auditoria', access: ACCESS.adminAuditLogsRead },
      { to: '/admin/financeiro', label: 'Financeiro admin', access: ACCESS.adminFinanceRead },
      { to: '/admin/configuracoes-financeiras', label: 'Taxa de serviço', access: ACCESS.adminFinanceSettingsRead },
      { to: '/admin/pagamentos-pendencias', label: 'Pendências de pagamento', access: ACCESS.adminPaymentIssuesRead },
    ],
  },
  {
    // Único ponto de entrada de nível 1 pra tudo que é configuração —
    // Bilheteria online/Assinatura/Contadores/Integrações deixaram de ser itens
    // próprios de menu (2026-07-24, hub `/configuracoes`) e viraram entradas
    // do índice do hub (ver `pages/Settings/blocks/registry.tsx`). Sem
    // `access`: a própria página decide o que exibir por bloco/entrada —
    // mesmo espírito do item "Visão geral" (sempre alcançável, conteúdo
    // condicional).
    kind: 'link',
    to: '/configuracoes',
    label: 'Configurações',
    icon: SettingsOutlinedIcon,
    exact: false,
  },
  {
    kind: 'link',
    to: '/suporte',
    label: 'Central de chamados',
    icon: SupportAgentOutlinedIcon,
    access: ACCESS.helpRequestsRead,
  },
]

/**
 * `startsWith` puro marcava "/vendas" como ativo em "/vendas-online" e no
 * alias legado "/vendas-loja" (prefixo
 * de string, não de rota) — exige fronteira de segmento (`/` depois do `to`)
 * pra continuar cobrindo sub-rotas reais como "/vendas/nova".
 */
function isPathActive(to: string, pathname: string, exact?: boolean): boolean {
  return exact ? pathname === to : pathname === to || pathname.startsWith(`${to}/`)
}

function isChildActive(child: NavChild, pathname: string): boolean {
  return isPathActive(child.to, pathname, child.exact)
}

function NavGroupItem({
  item,
  onNavigate,
}: {
  item: NavItem & { kind: 'group' }
  onNavigate?: () => void
}) {
  const location = useLocation()
  const { icon: Icon, label, children } = item
  const hasActiveChild = children.some((child) => isChildActive(child, location.pathname))
  const [manuallyOpen, setManuallyOpen] = useState(false)
  // Grupo abre sozinho quando a rota atual é de um dos filhos (igual ao
  // legado: `this.state.subMenuProdutos || pathname === ...`) — nesse caso
  // não é possível fechar clicando, só navegando pra fora do grupo.
  const isOpen = manuallyOpen || hasActiveChild

  return (
    <>
      <ListItemButton
        onClick={() => setManuallyOpen((open) => !open)}
        selected={hasActiveChild}
        sx={{
          minHeight: UI_SIZE.navGroup,
          mx: 1,
          mb: 0.5,
          pl: 2.125,
          borderRadius: UI_RADIUS.lg,
        }}
        aria-expanded={isOpen}
      >
        <ListItemIcon sx={{ color: hasActiveChild ? undefined : 'var(--pt-muted)', minWidth: 40 }}>
          <Icon fontSize="small" />
        </ListItemIcon>
        <ListItemText
          primary={label}
          slotProps={{ primary: { sx: { fontSize: 14.5, fontWeight: hasActiveChild ? 600 : 500 } } }}
        />
        <ExpandMoreIcon
          fontSize="small"
          sx={{
            color: 'var(--pt-muted)',
            transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
            transition: 'transform 0.2s',
          }}
        />
      </ListItemButton>

      <Collapse in={isOpen} timeout="auto" unmountOnExit>
        <List component="div" disablePadding>
          {children.map((child) => {
            const active = isChildActive(child, location.pathname)
            return (
              <ListItemButton
                key={child.to}
                component={RouterLink}
                to={child.to}
                selected={active}
                onClick={onNavigate}
                sx={{ minHeight: UI_SIZE.navItem, mx: 1, mb: 0.5, pl: 6.5, borderRadius: UI_RADIUS.md }}
              >
                <ListItemText
                  primary={child.label}
                  slotProps={{ primary: { sx: { fontSize: 14, fontWeight: active ? 600 : 500 } } }}
                />
              </ListItemButton>
            )
          })}
        </List>
      </Collapse>
    </>
  )
}

function NavList({ onNavigate }: { onNavigate?: () => void }) {
  const location = useLocation()
  const { accessProfile, can } = useAccessControl()
  const { activeTenant } = useAuth()
  const isTenantOwner = Boolean(accessProfile?.is_tenant_owner || isOwnerRole(activeTenant?.role, activeTenant?.role_slug))
  const visibleItems = NAV_ITEMS.reduce<NavItem[]>((acc, item) => {
    if (item.kind === 'link') {
      if (item.ownerOnly && !isTenantOwner) {
        return acc
      }

      if (item.hideForOwner && isTenantOwner) {
        return acc
      }

      if (!item.access || (item.ownerBypassesAccess && isTenantOwner) || can(item.access)) {
        acc.push(item)
      }

      return acc
    }

    const children = item.children.filter((child) => {
      if (child.ownerOnly && !isTenantOwner) return false
      if (child.hideForOwner && isTenantOwner) return false
      return !child.access || can(child.access)
    })
    if (children.length > 0) {
      acc.push({ ...item, children })
    }

    return acc
  }, [])

  return (
    <List sx={{ pt: 1.5, pb: 2.5, px: 0.75 }}>
      {visibleItems.map((item) => {
        if (item.kind === 'group') {
          return <NavGroupItem key={item.label} item={item} onNavigate={onNavigate} />
        }

        const { to, label, icon: Icon, exact } = item
        const isActive = isPathActive(to, location.pathname, exact)
        return (
          <ListItemButton
            key={to}
            component={RouterLink}
            to={to}
            selected={isActive}
            onClick={onNavigate}
            sx={{
              minHeight: UI_SIZE.navGroup,
              mx: 1,
              pl: 2.125,
              mb: 0.5,
              borderRadius: UI_RADIUS.lg,
            }}
          >
            <ListItemIcon sx={{ color: isActive ? undefined : 'var(--pt-muted)', minWidth: 40 }}>
              <Icon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary={label}
              slotProps={{ primary: { sx: { fontSize: 14.5, fontWeight: isActive ? 600 : 500 } } }}
            />
          </ListItemButton>
        )
      })}
    </List>
  )
}

/**
 * Achata `NAV_ITEMS` (só os itens navegáveis — grupo em si não tem rota)
 * respeitando a mesma permissão (`can`) usada pela sidebar, pra alimentar a
 * busca universal (`CommandPalette`) sem duplicar a lista de telas.
 */
function useSearchableNavItems(): CommandPaletteItem[] {
  const { accessProfile, can } = useAccessControl()
  const { activeTenant } = useAuth()

  return useMemo(() => {
    const isTenantOwner = Boolean(accessProfile?.is_tenant_owner || isOwnerRole(activeTenant?.role, activeTenant?.role_slug))
    const flat: CommandPaletteItem[] = []
    for (const item of NAV_ITEMS) {
      if (item.kind === 'link') {
        if (item.ownerOnly && !isTenantOwner) continue
        if (item.hideForOwner && isTenantOwner) continue
        if (!item.access || (item.ownerBypassesAccess && isTenantOwner) || can(item.access)) flat.push({ to: item.to, label: item.label })
        continue
      }
      for (const child of item.children) {
        if (child.ownerOnly && !isTenantOwner) continue
        if (child.hideForOwner && isTenantOwner) continue
        if (!child.access || can(child.access)) flat.push({ to: child.to, label: child.label, group: item.label })
      }
    }
    return flat
  }, [accessProfile?.is_tenant_owner, activeTenant?.role, activeTenant?.role_slug, can])
}

/** Só navegação — empresa ativa/plano e tema vivem no dropdown de `UserMenu` (ver design-system.md, 2026-07-14). */
function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <Box
      sx={{
        display: 'flex',
        minHeight: 0,
        flex: 1,
        flexDirection: 'column',
        background: 'var(--pt-sidebar-background)',
      }}
    >
      <Box sx={{ minHeight: 0, flex: 1, overflowY: 'auto' }}>
        <NavList onNavigate={onNavigate} />
      </Box>
    </Box>
  )
}

export function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [searchOpen, setSearchOpen] = useState(false)
  const searchableNavItems = useSearchableNavItems()

  return (
    <Box sx={{ display: 'flex', minHeight: '100dvh' }}>
      <TenantSelectModal />
      <CommandPalette items={searchableNavItems} open={searchOpen} onOpenChange={setSearchOpen} />
      <AppBar
        position="fixed"
        color="transparent"
        elevation={0}
        sx={{
          zIndex: (theme) => theme.zIndex.drawer + 1,
          backdropFilter: 'blur(18px)',
          background: 'color-mix(in srgb, var(--pt-surface) 84%, transparent)',
          borderBottom: '1px solid color-mix(in srgb, var(--pt-border) 88%, white)',
          boxShadow: '0 10px 28px rgba(10, 33, 62, 0.08)',
        }}
      >
        <Toolbar sx={{ gap: 0.5, minHeight: { xs: 64, sm: 72 }, px: { xs: 1, sm: 2.25 } }}>
          <IconButton
            aria-label="Abrir menu"
            onClick={() => setMobileOpen(true)}
            sx={{
              display: { sm: 'none' },
              ml: { xs: 0 },
              width: UI_SIZE.iconButton,
              height: UI_SIZE.iconButton,
              borderRadius: UI_RADIUS.lg,
              backgroundColor: 'color-mix(in srgb, var(--pt-surface) 90%, white)',
            }}
          >
            <MenuIcon />
          </IconButton>

          <Box sx={{ display: { xs: 'none', sm: 'flex' }, alignItems: 'center', flexGrow: 1 }}>
            <Logo variant="full" size={50} textSize={25} />
          </Box>

          <Box
            sx={{
              position: 'absolute',
              left: '50%',
              transform: 'translateX(-50%)',
              display: { xs: 'inline-flex', sm: 'none' },
              alignItems: 'center',
              pointerEvents: 'none',
            }}
          >
            <Logo variant="mark" size={50} />
          </Box>

          <Box sx={{ ml: 'auto', mr: { xs: 1.25, sm: 1.5 }, display: 'flex', alignItems: 'center', gap: { xs: 0.5, sm: 0.9 } }}>
            <Tooltip title="Buscar (Ctrl+K)">
              <IconButton
                aria-label="Buscar telas e páginas"
                onClick={() => setSearchOpen(true)}
                sx={{
                  width: UI_SIZE.controlLarge,
                  height: UI_SIZE.controlLarge,
                  borderRadius: UI_RADIUS.lg,
                  backgroundColor: 'color-mix(in srgb, var(--pt-surface) 90%, white)',
                }}
              >
                <SearchOutlinedIcon />
              </IconButton>
            </Tooltip>
            <ReleaseNotesMenu />
            <UserMenu />
          </Box>
        </Toolbar>
      </AppBar>

      <Drawer
        variant="temporary"
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
        ModalProps={{ keepMounted: true }}
        sx={{
          display: { xs: 'block', sm: 'none' },
          '& .MuiDrawer-paper': {
            width: DRAWER_WIDTH,
            boxSizing: 'border-box',
            display: 'flex',
            borderRight: '1px solid color-mix(in srgb, var(--pt-border) 88%, white)',
            backgroundColor: 'var(--pt-surface)',
          },
        }}
      >
        <Toolbar sx={{ minHeight: 64 }} />
        <SidebarContent onNavigate={() => setMobileOpen(false)} />
      </Drawer>

      <Drawer
        variant="permanent"
        open
        sx={{
          display: { xs: 'none', sm: 'block' },
          width: DRAWER_WIDTH,
          flexShrink: 0,
          '& .MuiDrawer-paper': {
            width: DRAWER_WIDTH,
            boxSizing: 'border-box',
            display: 'flex',
            borderRight: '1px solid color-mix(in srgb, var(--pt-border) 88%, white)',
            backgroundColor: 'var(--pt-surface)',
          },
        }}
      >
        <Toolbar sx={{ minHeight: 72 }} />
        <SidebarContent />
      </Drawer>

      <Box
        component="main"
        sx={{
          flexGrow: 1,
          // `minWidth: 0` é obrigatório: como flex item, o default `min-width:
          // auto` faz o <main> herdar a largura mínima do conteúdo (ex.: os
          // wrappers `minWidth: NNN` dos grids), estourando a página inteira
          // com scroll horizontal em mobile — o `overflowX: 'auto'` do wrapper
          // imediato só contém o conteúdo se o <main> puder encolher.
          minWidth: 0,
          width: { sm: `calc(100% - ${DRAWER_WIDTH}px)` },
          p: { xs: 2, sm: 3.25 },
        }}
      >
        <Toolbar />
        <Outlet />
      </Box>

      <InstallPrompt />
    </Box>
  )
}
