import { Box, Stack, Typography } from '@mui/material'
import { RevealSection } from '../components/RevealSection'
import { MODULES } from '../data/modules'

export function Modules() {
  return (
    <Box component="section" id="modulos" sx={{ py: { xs: 6, md: 10 } }}>
      <Box sx={{ maxWidth: 1200, mx: 'auto', px: { xs: 2.5, sm: 4 } }}>
        <RevealSection>
          <Typography component="h2" sx={{ fontSize: { xs: 26, md: 32 }, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
            Tudo que a operação precisa, no mesmo lugar
          </Typography>
          <Typography sx={{ fontSize: 16, color: 'var(--pt-muted)', maxWidth: 620, mb: 4 }}>
            Cada módulo abaixo já existe e funciona de forma integrada — sem planilha paralela, sem sistema separado
            para cada etapa do negócio.
          </Typography>
        </RevealSection>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' },
            gap: 2.5,
          }}
        >
          {MODULES.map((module, index) => (
            <RevealSection key={module.key} className="pt-module-card-wrap">
              <Box
                sx={{
                  height: '100%',
                  borderRadius: 'var(--pt-radius-lg)',
                  border: '1px solid var(--pt-border)',
                  backgroundColor: 'var(--pt-surface)',
                  boxShadow: 'var(--pt-shadow-sm)',
                  p: 2.5,
                  transition: 'transform 0.2s ease, box-shadow 0.2s ease',
                  '@media (prefers-reduced-motion: reduce)': { transition: 'none' },
                  '&:hover': { transform: 'translateY(-3px)', boxShadow: 'var(--pt-shadow-md)' },
                }}
                style={{ transitionDelay: `${Math.min(index, 6) * 40}ms` }}
              >
                <Stack
                  direction="row"
                  sx={{
                    width: 44,
                    height: 44,
                    alignItems: 'center',
                    justifyContent: 'center',
                    borderRadius: 'var(--pt-radius-md)',
                    backgroundColor: 'var(--pt-surface-soft)',
                    color: 'var(--pt-primary)',
                    mb: 1.75,
                  }}
                >
                  <module.icon />
                </Stack>
                <Typography sx={{ fontSize: 16.5, fontWeight: 700, color: 'var(--pt-text)', mb: 0.75 }}>
                  {module.title}
                </Typography>
                <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', lineHeight: 1.5 }}>
                  {module.description}
                </Typography>
              </Box>
            </RevealSection>
          ))}
        </Box>
      </Box>
    </Box>
  )
}
