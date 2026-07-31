import { Box, Paper, Typography } from '@mui/material'
import { StoryCanvas } from '../../../components/socialMedia/StoryCanvas'
import { StoryPreviewStage } from '../../../components/socialMedia/StoryPreviewStage'
import { StorySingleBody } from '../../../components/socialMedia/StorySingleBody'
import { FORM_GRID_3_SX } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import type { StorySingleContent, StoryTemplateVariant } from '../../../types/socialMedia'
import { STORY_TEMPLATES } from '../templates'

const SAMPLE_CONTENT: StorySingleContent = {
  kind: 'single',
  eyebrow: 'Produto em destaque',
  title: 'Queijo Meia Cura',
  value: 'R$ 38,00',
}

interface TemplateStepProps {
  onSelect: (variant: StoryTemplateVariant) => void
}

/**
 * Etapa 2 do wizard: escolha do template. A miniatura de cada card é o
 * `StoryCanvas` real (não uma ilustração aproximada) escalado via
 * `StoryPreviewStage` — garante que a prévia mostrada aqui é exatamente o
 * que o usuário vai ver na etapa de preview final.
 */
export function TemplateStep({ onSelect }: TemplateStepProps) {
  return (
    <Box sx={{ ...FORM_GRID_3_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(3, minmax(0, 1fr))' } }}>
      {STORY_TEMPLATES.map(({ key, label, description }) => (
        <Paper
          key={key}
          component="button"
          type="button"
          onClick={() => onSelect(key)}
          variant="outlined"
          sx={{
            p: 2,
            textAlign: 'left',
            cursor: 'pointer',
            ...ELEVATED_SURFACE_SX,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: 1.5,
            font: 'inherit',
            color: 'inherit',
            transition: 'border-color 0.15s, background-color 0.15s',
            '&:hover': { borderColor: 'var(--mk-primary)', background: 'var(--mk-surface-soft)' },
            '&:focus-visible': { outline: 'none', boxShadow: 'var(--mk-focus-ring)' },
          }}
        >
          <StoryPreviewStage maxWidth={116}>
            <StoryCanvas variant={key}>
              <StorySingleBody content={SAMPLE_CONTENT} />
            </StoryCanvas>
          </StoryPreviewStage>
          <Typography sx={{ fontWeight: 600, fontSize: 14.5, color: 'var(--mk-text)' }}>{label}</Typography>
          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', textAlign: 'center' }}>{description}</Typography>
        </Paper>
      ))}
    </Box>
  )
}
