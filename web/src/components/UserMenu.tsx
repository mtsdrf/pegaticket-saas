import LogoutIcon from '@mui/icons-material/Logout'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import { Box, Divider, IconButton, ListItemIcon, ListItemText, Menu, MenuItem, Skeleton, Stack, Tooltip, Typography } from '@mui/material'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useUserProfile } from '../hooks/useUserProfile'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../styles/surfaces'
import { TenantMenu } from './TenantMenu'
import { ThemeModeSwitch } from './ThemeModeSwitch'
import { UserAvatar } from './UserAvatar'

const MENU_WIDTH = 320

function MenuHeader() {
  const { profile, isLoading, error } = useUserProfile()

  if (isLoading && !profile) {
    return (
      <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', px: 2, py: 1.5 }}>
        <Skeleton variant="circular" width={44} height={44} />
        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Skeleton variant="text" width="70%" />
          <Skeleton variant="text" width="90%" />
        </Box>
      </Stack>
    )
  }

  if (error || !profile) {
    return (
      <Typography variant="body2" color="error" sx={{ px: 2, py: 1.5 }}>
        Não foi possível carregar seus dados.
      </Typography>
    )
  }

  return (
    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', px: 2, py: 1.5 }}>
      <UserAvatar name={profile.name} avatarUrl={profile.avatar_url} size={44} />
      <Box sx={{ minWidth: 0 }}>
        <Typography
          sx={{ fontWeight: 700, fontSize: 15, color: 'var(--mk-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
        >
          {profile.name}
        </Typography>
        <Typography
          variant="caption"
          sx={{ display: 'block', color: 'var(--mk-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
        >
          {profile.email}
        </Typography>
      </Box>
    </Stack>
  )
}

export function UserMenu() {
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const { logout } = useAuth()
  const { profile } = useUserProfile()
  const navigate = useNavigate()

  function closeMenu() {
    setAnchorEl(null)
  }

  return (
    <>
      <Tooltip title="Conta" arrow>
        <IconButton
          aria-label="Abrir menu da conta"
          onClick={(event) => setAnchorEl(event.currentTarget)}
          edge="end"
          sx={{
            width: 54,
            height: 54,
            p: 0.5,
            ...SOFT_PANEL_SX,
            borderRadius: '16px',
            borderColor: 'transparent',
            background: 'var(--mk-surface-raised-bg)',
          }}
        >
          <UserAvatar name={profile?.name ?? ''} avatarUrl={profile?.avatar_url} size={42} />
        </IconButton>
      </Tooltip>

      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={closeMenu}
        transformOrigin={{ horizontal: 'right', vertical: 'top' }}
        anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
        slotProps={{
          paper: {
            sx: {
              width: MENU_WIDTH,
              maxWidth: 'calc(100vw - 24px)',
              mt: 1,
              ...ELEVATED_SURFACE_SX,
              borderRadius: '20px',
                background: 'var(--mk-surface-raised-bg)',
            },
          },
        }}
      >
        <MenuHeader />

        <Divider />

        <Box sx={{ py: 1.5 }}>
          <TenantMenu variant="menu" onSelectComplete={closeMenu} />
        </Box>

        <Divider />

        <Box sx={{ px: 2, py: 1.5 }}>
          <ThemeModeSwitch />
        </Box>

        <Divider />

        <MenuItem
          onClick={() => {
            closeMenu()
            navigate('/minha-conta')
          }}
          sx={{ minHeight: 44 }}
        >
          <ListItemIcon>
            <PersonOutlineOutlinedIcon fontSize="small" />
          </ListItemIcon>
          <ListItemText>Meus dados</ListItemText>
        </MenuItem>

        <MenuItem
          onClick={() => {
            closeMenu()
            void logout()
          }}
          sx={{ minHeight: 44 }}
        >
          <ListItemIcon>
            <LogoutIcon fontSize="small" />
          </ListItemIcon>
          <ListItemText>Sair</ListItemText>
        </MenuItem>
      </Menu>
    </>
  )
}
