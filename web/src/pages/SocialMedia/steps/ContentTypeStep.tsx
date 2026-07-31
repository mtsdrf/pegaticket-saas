import { Box, Paper, Typography } from '@mui/material'
import { FORM_GRID_3_SX } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../../styles/surfaces'
import { CONTENT_TYPES } from '../contentTypes'
import type { SocialContentTypeKey } from '../../../types/socialMedia'

interface ContentTypeStepProps {
  onSelect: (key: SocialContentTypeKey) => void
}

/** Etapa 1 do wizard: escolha do tipo de conteúdo (5 cards, um por tipo do MVP). */
export function ContentTypeStep({ onSelect }: ContentTypeStepProps) {
  return (
    <Box
      sx={{
        ...FORM_GRID_3_SX,
        gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))', md: 'repeat(3, minmax(0, 1fr))' },
      }}
    >
      {CONTENT_TYPES.map(({ key, label, description, icon: Icon }) => (
        <Paper
          key={key}
          component="button"
          type="button"
          onClick={() => onSelect(key)}
          variant="outlined"
          sx={{
            p: 2.5,
            textAlign: 'left',
            cursor: 'pointer',
            ...ELEVATED_SURFACE_SX,
            display: 'flex',
            flexDirection: 'column',
            gap: 1.25,
            minHeight: 150,
            font: 'inherit',
            color: 'inherit',
            transition: 'border-color 0.15s, background-color 0.15s',
            '&:hover': { borderColor: 'var(--mk-primary)', background: 'var(--mk-surface-soft)' },
            '&:focus-visible': { outline: 'none', boxShadow: 'var(--mk-focus-ring)' },
          }}
        >
          <Box
            sx={{
              width: 44,
              height: 44,
              ...SOFT_PANEL_SX,
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: 'color-mix(in srgb, var(--mk-primary) 12%, transparent)',
              color: 'var(--mk-primary)',
            }}
          >
            <Icon />
          </Box>
          <Typography sx={{ fontWeight: 600, fontSize: 15.5, color: 'var(--mk-text)' }}>{label}</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{description}</Typography>
        </Paper>
      ))}
    </Box>
  )
}
