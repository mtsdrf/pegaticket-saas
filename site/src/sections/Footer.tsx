import { Box, Stack, Typography } from '@mui/material'
import { Logo } from '../components/Logo'
import { APP_URL } from '../constants/app'

const YEAR = new Date().getFullYear()

export function Footer() {
  return (
    <Box component="footer" sx={{ py: 5, borderTop: '1px solid var(--pt-border)', backgroundColor: 'var(--pt-surface)' }}>
      <Box
        sx={{
          maxWidth: 1200,
          mx: 'auto',
          px: { xs: 2.5, sm: 4 },
          display: 'flex',
          flexDirection: { xs: 'column', sm: 'row' },
          justifyContent: 'space-between',
          alignItems: { xs: 'flex-start', sm: 'center' },
          gap: 2,
        }}
      >
        <Logo size={26} />

        <Stack direction="row" spacing={3} useFlexGap sx={{ flexWrap: 'wrap' }}>
          <Box component="a" href="/#modulos" sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textDecoration: 'none' }}>
            Módulos
          </Box>
          <Box component="a" href="/precos" sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textDecoration: 'none' }}>
            Planos
          </Box>
          <Box component="a" href="#faq" sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textDecoration: 'none' }}>
            Perguntas frequentes
          </Box>
          <Box
            component="a"
            href={`${APP_URL}/termos`}
            sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textDecoration: 'none' }}
          >
            Termos de uso
          </Box>
          <Box
            component="a"
            href={`${APP_URL}/privacidade`}
            sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textDecoration: 'none' }}
          >
            Privacidade
          </Box>
        </Stack>

        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
          © {YEAR} PegaTicket. Todos os direitos reservados.
        </Typography>
      </Box>
    </Box>
  )
}
