import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import { Avatar, type SxProps, type Theme } from '@mui/material'
import { getInitials } from '../utils/initials'

interface UserAvatarProps {
  name: string
  avatarUrl?: string | null
  size?: number
  sx?: SxProps<Theme>
}

/** Foto do usuário quando existe, senão iniciais sobre `--pt-primary` — usado no header e em "Meus dados". */
export function UserAvatar({ name, avatarUrl, size = 40, sx }: UserAvatarProps) {
  const initials = getInitials(name)

  return (
    <Avatar
      src={avatarUrl ?? undefined}
      alt={name}
      sx={{
        width: size,
        height: size,
        fontSize: size * 0.4,
        fontWeight: 700,
        bgcolor: 'var(--pt-primary)',
        color: '#FFFFFF',
        ...sx,
      }}
    >
      {/* Nome ainda não carregado (ex.: perfil em loading) e sem foto — ícone genérico em vez de avatar vazio. */}
      {initials || <PersonOutlineOutlinedIcon sx={{ fontSize: size * 0.55 }} />}
    </Avatar>
  )
}
