import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import FavoriteIcon from '@mui/icons-material/Favorite'
import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import { Box, IconButton, Paper, Stack, Typography } from '@mui/material'
import { CLAMP_TEXT_2_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { StorefrontEvent } from '../../types/storefront'
import { formatDateBR } from '../../utils/format'

interface EventCardProps {
  event: StorefrontEvent
  onClick: (event: StorefrontEvent) => void
  onToggleFavorite: (event: StorefrontEvent) => void
}

/** Card de evento do catálogo público — substitui `ProductGridCard`/`ProductListItem` (migração PegaTicket, comércio → ingressos). */
export function EventCard({ event, onClick, onToggleFavorite }: EventCardProps) {
  return (
    <Paper
      elevation={0}
      onClick={() => onClick(event)}
      sx={{
        ...ELEVATED_SURFACE_SX,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        width: '100%',
        cursor: 'pointer',
        transition: 'box-shadow 0.15s ease',
        '&:hover': { boxShadow: 'var(--pt-shadow-md)' },
      }}
    >
      <Box
        sx={{
          position: 'relative',
          width: '100%',
          aspectRatio: '16 / 9',
          ...SOFT_PANEL_SX,
          borderRadius: 0,
          borderLeft: 'none',
          borderRight: 'none',
          borderTop: 'none',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          overflow: 'hidden',
          flexShrink: 0,
        }}
      >
        {event.cover_image_url ? (
          <Box
            component="img"
            src={event.cover_image_url}
            alt={event.name}
            sx={{ width: '100%', height: '100%', objectFit: 'cover' }}
          />
        ) : (
          <EventOutlinedIcon sx={{ fontSize: 44, color: 'var(--pt-muted)' }} />
        )}

        <IconButton
          onClick={(clickEvent) => {
            clickEvent.stopPropagation()
            onToggleFavorite(event)
          }}
          aria-label={event.is_favorited ? `Remover ${event.name} dos favoritos` : `Favoritar ${event.name}`}
          size="small"
          sx={{
            position: 'absolute',
            top: 6,
            right: 6,
            width: 40,
            height: 40,
            bgcolor: 'color-mix(in srgb, var(--pt-surface) 85%, transparent)',
            '&:hover': { background: 'var(--pt-surface)' },
          }}
        >
          {event.is_favorited ? (
            <FavoriteIcon sx={{ fontSize: 17, color: 'var(--pt-primary)' }} />
          ) : (
            <FavoriteBorderOutlinedIcon sx={{ fontSize: 17, color: 'var(--pt-muted)' }} />
          )}
        </IconButton>
      </Box>

      <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', gap: 0.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700, lineHeight: 1.3, wordBreak: 'break-word', ...CLAMP_TEXT_2_SX }}>
          {event.name}
        </Typography>

        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
          <EventOutlinedIcon sx={{ fontSize: 15, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{formatDateBR(event.starts_at)}</Typography>
        </Stack>

        {event.location_name && (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            <PlaceOutlinedIcon sx={{ fontSize: 15, color: 'var(--pt-muted)' }} />
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', ...CLAMP_TEXT_2_SX }}>{event.location_name}</Typography>
          </Stack>
        )}
      </Box>
    </Paper>
  )
}
