import { Box, Typography } from '@mui/material'
import { RevealSection } from '../components/RevealSection'
import { SEGMENTS } from '../data/segments'

export function Segments() {
  return (
    <Box component="section" id="segmentos" sx={{ py: { xs: 6, md: 10 }, backgroundColor: 'var(--mk-surface-soft)' }}>
      <Box sx={{ maxWidth: 1200, mx: 'auto', px: { xs: 2.5, sm: 4 } }}>
        <RevealSection>
          <Typography component="h2" sx={{ fontSize: { xs: 26, md: 32 }, fontWeight: 700, color: 'var(--mk-text)', mb: 1 }}>
            Feito para o seu tipo de negócio
          </Typography>
          <Typography sx={{ fontSize: 16, color: 'var(--mk-muted)', maxWidth: 620, mb: 4 }}>
            O Maskats já é usado por operações de atacado, varejo, laticínios, distribuidoras de bebidas, bares e
            casas noturnas.
          </Typography>
        </RevealSection>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', md: 'repeat(3, 1fr)' },
            gap: 2,
          }}
        >
          {SEGMENTS.map((segment) => (
            <RevealSection key={segment.key}>
              <Box
                sx={{
                  height: '100%',
                  borderRadius: 'var(--mk-radius-lg)',
                  border: '1px solid var(--mk-border)',
                  backgroundColor: 'var(--mk-surface)',
                  p: 2.5,
                }}
              >
                <Typography sx={{ fontSize: 16.5, fontWeight: 700, color: 'var(--mk-text)', mb: 0.75 }}>
                  {segment.title}
                </Typography>
                <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', lineHeight: 1.5 }}>
                  {segment.description}
                </Typography>
              </Box>
            </RevealSection>
          ))}
        </Box>
      </Box>
    </Box>
  )
}
