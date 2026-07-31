import { Box, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Logo } from '../ui/Logo'

const DEFAULT_HIGHLIGHTS = [
  'Pedidos, clientes e produtos em um só lugar',
  'Indicadores atualizados em tempo real',
  'Acesso por empresa com permissões por perfil',
]

interface AuthBrandPanelProps {
  headline: string
  subheadline: string
  highlights?: string[]
}

/**
 * Painel de marca (gradiente + blobs + destaques) compartilhado pelas telas
 * públicas de autenticação — extraído para evitar reduplicar o mesmo bloco
 * visual a cada nova tela (`LoginPage`/`AcceptInvitePage` mantêm sua cópia
 * original por não fazer parte do escopo desta mudança).
 */
export function AuthBrandPanel({ headline, subheadline, highlights = DEFAULT_HIGHLIGHTS }: AuthBrandPanelProps): ReactNode {
  return (
    <Box
      sx={{
        display: { xs: 'none', md: 'flex' },
        position: 'relative',
        overflow: 'hidden',
        flexDirection: 'column',
        justifyContent: 'space-between',
        p: 6,
        color: '#FFFFFF',
        background:
          'linear-gradient(155deg, var(--pt-primary) 0%, color-mix(in srgb, var(--pt-primary) 65%, black) 55%, color-mix(in srgb, var(--pt-accent) 45%, black) 100%)',
      }}
    >
      <Box
        aria-hidden="true"
        sx={{
          position: 'absolute',
          width: 420,
          height: 420,
          borderRadius: '50%',
          top: -140,
          right: -120,
          filter: 'blur(90px)',
          background: 'color-mix(in srgb, var(--pt-accent) 55%, transparent)',
          '@keyframes pt-float-a': {
            '0%, 100%': { transform: 'translate(0, 0)' },
            '50%': { transform: 'translate(-24px, 28px)' },
          },
          animation: 'pt-float-a 14s ease-in-out infinite',
          '@media (prefers-reduced-motion: reduce)': { animation: 'none' },
        }}
      />
      <Box
        aria-hidden="true"
        sx={{
          position: 'absolute',
          width: 320,
          height: 320,
          borderRadius: '50%',
          bottom: -100,
          left: -80,
          filter: 'blur(80px)',
          background: 'color-mix(in srgb, #FFFFFF 20%, transparent)',
          '@keyframes pt-float-b': {
            '0%, 100%': { transform: 'translate(0, 0)' },
            '50%': { transform: 'translate(20px, -18px)' },
          },
          animation: 'pt-float-b 16s ease-in-out infinite',
          '@media (prefers-reduced-motion: reduce)': { animation: 'none' },
        }}
      />
      <Box
        aria-hidden="true"
        sx={{
          position: 'absolute',
          inset: 0,
          opacity: 0.5,
          backgroundImage:
            'radial-gradient(color-mix(in srgb, #FFFFFF 22%, transparent) 1px, transparent 1px)',
          backgroundSize: '22px 22px',
          maskImage: 'linear-gradient(180deg, transparent, black 30%, black 70%, transparent)',
        }}
      />

      <Box sx={{ position: 'relative' }}>
        <Logo variant="mark" size={50} tone="light" />
        <Typography
          sx={{
            mt: 0.75,
            fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
            fontWeight: 600,
            fontSize: 22,
            letterSpacing: '-0.01em',
          }}
        >
          PegaTicket
        </Typography>
      </Box>

      <Box sx={{ position: 'relative', maxWidth: 400 }}>
        <Typography sx={{ fontSize: 30, fontWeight: 600, lineHeight: 1.25, mb: 1.5 }}>{headline}</Typography>
        <Typography sx={{ fontSize: 15, color: 'color-mix(in srgb, #FFFFFF 78%, transparent)', mb: 3 }}>
          {subheadline}
        </Typography>

        <Stack spacing={1.25}>
          {highlights.map((item) => (
            <Box key={item} sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: 1.25 }}>
              <Box
                aria-hidden="true"
                sx={{
                  width: 6,
                  height: 6,
                  borderRadius: '50%',
                  bgcolor: 'var(--pt-accent)',
                  flexShrink: 0,
                }}
              />
              <Typography sx={{ fontSize: 14, color: 'color-mix(in srgb, #FFFFFF 85%, transparent)' }}>
                {item}
              </Typography>
            </Box>
          ))}
        </Stack>
      </Box>

      <Typography sx={{ position: 'relative', fontSize: 13, color: 'color-mix(in srgb, #FFFFFF 55%, transparent)' }}>
        © {new Date().getFullYear()} PegaTicket. Todos os direitos reservados.
      </Typography>
    </Box>
  )
}
