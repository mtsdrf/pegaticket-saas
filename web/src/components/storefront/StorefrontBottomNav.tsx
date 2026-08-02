import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import LocalActivityOutlinedIcon from '@mui/icons-material/LocalActivityOutlined'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import ShoppingCartOutlinedIcon from '@mui/icons-material/ShoppingCartOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import {
  Badge,
  Box,
  ButtonBase,
  Divider,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Typography,
} from '@mui/material'
import { useState, type ReactNode } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { StorefrontTenant } from '../../types/storefront'
import { OtpLoginDialog } from './OtpLoginDialog'

/** Altura visual do nav (sem contar a safe-area) — as páginas usam este valor pra reservar `padding-bottom`. */
export const STOREFRONT_BOTTOM_NAV_HEIGHT = 64

interface StorefrontBottomNavProps {
  slug: string
  tenant: StorefrontTenant | null
}

function NavButton({
  icon,
  label,
  active,
  badge,
  onClick,
  ariaLabel,
}: {
  icon: ReactNode
  label: string
  active: boolean
  badge?: number
  onClick: (event: React.MouseEvent<HTMLElement>) => void
  ariaLabel?: string
}) {
  const color = active ? 'var(--pt-primary)' : 'var(--pt-muted)'

  return (
    <ButtonBase
      onClick={onClick}
      aria-label={ariaLabel ?? label}
      aria-current={active ? 'page' : undefined}
      sx={{
        flex: 1,
        minWidth: 0,
        minHeight: STOREFRONT_BOTTOM_NAV_HEIGHT,
        px: 0.5,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 0.4,
        color,
        ...SOFT_PANEL_SX,
        borderRadius: 'var(--pt-radius-md)',
        borderColor: 'transparent',
        background: 'transparent',
        transition: 'color 0.15s ease',
      }}
    >
      {badge && badge > 0 ? (
        <Badge badgeContent={badge} color="secondary" max={99}>
          {icon}
        </Badge>
      ) : (
        icon
      )}
      <Typography sx={{ fontSize: 11, fontWeight: active ? 700 : 600, lineHeight: 1, color }}>{label}</Typography>
    </ButtonBase>
  )
}

/**
 * Barra de navegação fixa da loja pública (substitui o antigo
 * `StorefrontHeader`) — Eventos / Perfil da loja / Carrinho / Conta. A lógica
 * de "Conta" é PORTADA do header: deslogado abre o `OtpLoginDialog` (mesma
 * identidade OTP do Portal); logado abre o mesmo menu com os mesmos itens,
 * agora ancorado no item do nav e abrindo pra cima.
 */
export function StorefrontBottomNav({ slug, tenant }: StorefrontBottomNavProps) {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { totalQuantity } = useStorefrontCart()
  const { isAuthenticated, customer, logout } = usePortalAuth()

  const [menuAnchor, setMenuAnchor] = useState<null | HTMLElement>(null)
  const [otpOpen, setOtpOpen] = useState(false)
  const base = `/eventos/${slug}`

  function handleAccountClick(event: React.MouseEvent<HTMLElement>) {
    if (!isAuthenticated) {
      setOtpOpen(true)
      return
    }
    setMenuAnchor(event.currentTarget)
  }

  function goTo(path: string) {
    setMenuAnchor(null)
    navigate(path)
  }

  const storefrontEnabled = tenant?.storefront_enabled !== false
  const menuItems = [
    { key: 'perfil', label: 'Meus dados', icon: <PersonOutlineOutlinedIcon fontSize="small" />, path: '/portal/perfil' },
    { key: 'compras', label: 'Minhas compras', icon: <ReceiptLongOutlinedIcon fontSize="small" />, path: '/portal/compras' },
    { key: 'vouchers', label: 'Meus vouchers', icon: <ConfirmationNumberOutlinedIcon fontSize="small" />, path: '/portal/vouchers' },
    { key: 'favoritos', label: 'Favoritos', icon: <FavoriteBorderOutlinedIcon fontSize="small" />, path: '/portal/favoritos' },
  ]

  const isEventos = pathname === base || pathname === `${base}/`
  const isPerfil = pathname === `${base}/perfil`
  const isCarrinho = pathname === `${base}/carrinho` || pathname === `${base}/checkout`
  return (
    <>
      <Box
        component="nav"
        aria-label="Navegação da loja"
        sx={{
          position: 'fixed',
          left: 0,
          right: 0,
          bottom: 0,
          zIndex: (theme) => theme.zIndex.appBar,
          bgcolor: 'color-mix(in srgb, var(--pt-surface) 94%, transparent)',
          backdropFilter: 'blur(8px)',
          borderTop: '1px solid var(--pt-border)',
          pb: 'env(safe-area-inset-bottom, 0px)',
        }}
      >
        <Box
          sx={{
            maxWidth: 960,
            mx: 'auto',
            px: { xs: 0.5, sm: 1 },
            display: 'flex',
            alignItems: 'stretch',
          }}
        >
          <NavButton
            icon={<LocalActivityOutlinedIcon />}
            label={storefrontEnabled ? 'Eventos' : 'Empresa'}
            active={isEventos}
            onClick={() => navigate(base)}
          />
          <NavButton
            icon={<StorefrontOutlinedIcon />}
            label="Perfil da loja"
            active={isPerfil}
            onClick={() => navigate(`${base}/perfil`)}
          />
          {storefrontEnabled ? (
            <NavButton
              icon={<ShoppingCartOutlinedIcon />}
              label="Carrinho"
              active={isCarrinho}
              badge={totalQuantity}
              ariaLabel={totalQuantity > 0 ? `Carrinho, ${totalQuantity} itens` : 'Carrinho'}
              onClick={() => navigate(`${base}/carrinho`)}
            />
          ) : null}
          <NavButton
            icon={<PersonOutlineOutlinedIcon />}
            label="Conta"
            active={Boolean(menuAnchor)}
            ariaLabel={isAuthenticated ? 'Abrir menu da conta' : 'Entrar na sua conta'}
            onClick={handleAccountClick}
          />
        </Box>
      </Box>

      <Menu
        anchorEl={menuAnchor}
        open={Boolean(menuAnchor)}
        onClose={() => setMenuAnchor(null)}
        anchorOrigin={{ vertical: 'top', horizontal: 'right' }}
        transformOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        slotProps={{ paper: { sx: { minWidth: 230, ...SOFT_PANEL_SX, mb: 1 } } }}
      >
        {customer?.email && (
          <Box sx={{ px: 2, py: 1 }}>
            {customer.name && <Typography sx={{ fontSize: 14, fontWeight: 700 }}>{customer.name}</Typography>}
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', wordBreak: 'break-word' }}>
              {customer.email}
            </Typography>
          </Box>
        )}
        {customer?.email && <Divider />}
        {menuItems.map((item) => (
          <MenuItem key={item.key} onClick={() => goTo(item.path)} sx={{ minHeight: 44 }}>
            <ListItemIcon>{item.icon}</ListItemIcon>
            <ListItemText slotProps={{ primary: { sx: { fontSize: 14 } } }}>{item.label}</ListItemText>
          </MenuItem>
        ))}
        <Divider />
        <MenuItem
          onClick={() => {
            setMenuAnchor(null)
            logout()
          }}
          sx={{ minHeight: 44, color: 'var(--pt-danger)' }}
        >
          <ListItemIcon>
            <LogoutOutlinedIcon fontSize="small" sx={{ color: 'var(--pt-danger)' }} />
          </ListItemIcon>
          <ListItemText slotProps={{ primary: { sx: { fontSize: 14 } } }}>Sair</ListItemText>
        </MenuItem>
      </Menu>

      <OtpLoginDialog open={otpOpen} onClose={() => setOtpOpen(false)} onAuthenticated={() => setOtpOpen(false)} />
    </>
  )
}
