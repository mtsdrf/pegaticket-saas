import { Box, Button, Stack, Typography } from '@mui/material'
import { APP_URL } from '../constants/app'
import { trackEvent } from '../utils/analytics'

export function FinalCta() {
  return (
    <Box
      component="section"
      sx={{
        py: { xs: 7, md: 10 },
        backgroundColor: 'var(--pt-primary)',
      }}
    >
      <Box sx={{ maxWidth: 720, mx: 'auto', px: { xs: 2.5, sm: 4 }, textAlign: 'center' }}>
        <Typography sx={{ fontSize: { xs: 26, md: 32 }, fontWeight: 700, color: '#FFFFFF', mb: 1.5 }}>
          Sua operação não precisa continuar desorganizada
        </Typography>
        <Typography sx={{ fontSize: 16, color: 'rgba(255,255,255,0.85)', mb: 3.5 }}>
          Centralize pedidos, estoque e clientes, e tenha mais clareza para fazer o negócio crescer.
        </Typography>
        <Button
          size="large"
          variant="contained"
          href={APP_URL}
          onClick={() => trackEvent({ name: 'hero_cta_click', target: 'primary' })}
          sx={{
            backgroundColor: '#FFFFFF',
            color: 'var(--pt-primary)',
            px: 4,
            '&:hover': { backgroundColor: 'rgba(255,255,255,0.9)' },
          }}
        >
          Começar meu teste gratuito
        </Button>
        <Stack direction="row" spacing={2.5} useFlexGap sx={{ mt: 2.5, justifyContent: 'center', flexWrap: 'wrap' }}>
          {['14 dias de teste', 'Sem multa no cancelamento', 'Suporte na implantação'].map((item) => (
            <Typography key={item} sx={{ fontSize: 13, color: 'rgba(255,255,255,0.75)' }}>
              {item}
            </Typography>
          ))}
        </Stack>
      </Box>
    </Box>
  )
}
