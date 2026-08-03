import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded'
import PlayCircleRoundedIcon from '@mui/icons-material/PlayCircleRounded'
import { Box, Button, Chip, Stack, Typography } from '@mui/material'
import { APP_URL } from '../constants/app'
import { trackEvent } from '../utils/analytics'

const RISK_REDUCERS = ['14 dias de teste', 'Cancele quando quiser', 'Suporte na implantação']

export function Hero() {
  return (
    <Box
      id="topo"
      component="section"
      sx={{
        pt: { xs: 6, md: 10 },
        pb: { xs: 6, md: 10 },
        background: 'linear-gradient(180deg, var(--pt-surface-soft) 0%, var(--pt-bg) 70%)',
      }}
    >
      <Box
        sx={{
          maxWidth: 1200,
          mx: 'auto',
          px: { xs: 2.5, sm: 4 },
          display: 'grid',
          gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: '1.05fr 0.95fr' },
          gap: { xs: 5, md: 6 },
          alignItems: 'center',
        }}
      >
        <Box>
          <Chip
            label="Eventos, ingressos, bilheteria online e check-in em um só sistema"
            sx={{
              mb: 2.5,
              fontWeight: 600,
              backgroundColor: 'var(--pt-surface)',
              border: '1px solid var(--pt-border)',
              color: 'var(--pt-primary)',
            }}
          />

          <Typography
            component="h1"
            className="pt-wordmark"
            sx={{ fontSize: { xs: 32, sm: 42, md: 48 }, lineHeight: 1.1, color: 'var(--pt-text)', mb: 2.5 }}
          >
            Gestão clara para empresas em movimento.
          </Typography>

          <Typography sx={{ fontSize: { xs: 16, md: 18 }, color: 'var(--pt-muted)', maxWidth: 560, mb: 4 }}>
            Eventos, ingressos, lotes, bilheteria online, pagamentos e relatórios em um único sistema, com operação
            pensada para organizadores, produtores e casas de evento.
          </Typography>

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 2.5 }}>
            <Button
              variant="contained"
              color="primary"
              size="large"
              href={APP_URL}
              onClick={() => trackEvent({ name: 'hero_cta_click', target: 'primary' })}
              sx={{ px: 3.5 }}
            >
              Começar agora
            </Button>
            <Button
              variant="outlined"
              size="large"
              href="#modulos"
              startIcon={<PlayCircleRoundedIcon />}
              onClick={() => trackEvent({ name: 'hero_cta_click', target: 'secondary' })}
              sx={{ px: 3.5 }}
            >
              Ver como funciona
            </Button>
          </Stack>

          <Stack direction="row" spacing={2.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
            {RISK_REDUCERS.map((item) => (
              <Stack key={item} direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
                <CheckCircleRoundedIcon sx={{ fontSize: 18, color: 'var(--pt-success)' }} />
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>{item}</Typography>
              </Stack>
            ))}
          </Stack>
        </Box>

        <HeroMockup />
      </Box>
    </Box>
  )
}

/**
 * Composição estilizada do painel + notificação de venda — ilustração
 * própria (tokens `--pt-*`), não um screenshot real do produto (nenhum
 * disponível para uso público nesta entrega).
 */
function HeroMockup() {
  return (
    <Box
      sx={{
        position: 'relative',
        borderRadius: 'var(--pt-radius-xl)',
        border: '1px solid var(--pt-border)',
        backgroundColor: 'var(--pt-surface)',
        boxShadow: 'var(--pt-shadow-lg)',
        p: { xs: 2.5, md: 3 },
        overflow: 'hidden',
      }}
      aria-hidden="true"
    >
      <Stack direction="row" spacing={1} sx={{ mb: 2 }}>
        <Box sx={{ width: 10, height: 10, borderRadius: '50%', backgroundColor: 'var(--pt-danger)' }} />
        <Box sx={{ width: 10, height: 10, borderRadius: '50%', backgroundColor: 'var(--pt-warning)' }} />
        <Box sx={{ width: 10, height: 10, borderRadius: '50%', backgroundColor: 'var(--pt-success)' }} />
      </Stack>

      <Stack direction="row" spacing={1.5} sx={{ mb: 2 }}>
        {['Novo', 'Em preparo', 'Entregue'].map((status, index) => (
          <Box
            key={status}
            sx={{
              flex: 1,
              borderRadius: 'var(--pt-radius-md)',
              backgroundColor: 'var(--pt-surface-soft)',
              border: '1px solid var(--pt-border)',
              p: 1.25,
            }}
          >
            <Typography sx={{ fontSize: 11.5, fontWeight: 600, color: 'var(--pt-muted)', mb: 0.75 }}>
              {status}
            </Typography>
            <Box
              sx={{
                height: 34,
                borderRadius: 'var(--pt-radius-sm)',
                backgroundColor: index === 0 ? 'var(--pt-secondary)' : 'var(--pt-surface)',
                opacity: index === 0 ? 0.16 : 1,
                border: index === 0 ? 'none' : '1px dashed var(--pt-border)',
              }}
            />
          </Box>
        ))}
      </Stack>

      <Box
        sx={{
          borderRadius: 'var(--pt-radius-md)',
          border: '1px solid var(--pt-border)',
          p: 2,
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-end',
        }}
      >
        <Box>
          <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>Vendas do dia</Typography>
          <Typography sx={{ fontSize: 22, fontWeight: 700, color: 'var(--pt-text)' }}>R$ 4.280,00</Typography>
        </Box>
        <Stack direction="row" spacing={0.75} sx={{ height: 44, alignItems: 'flex-end' }}>
          {[16, 26, 20, 34, 28, 40].map((height, index) => (
            <Box
              key={index}
              sx={{ width: 8, height, borderRadius: 2, backgroundColor: 'var(--pt-accent)', opacity: 0.85 }}
            />
          ))}
        </Stack>
      </Box>

      <Box
        sx={{
          position: 'absolute',
          top: { xs: 12, md: 20 },
          right: { xs: 12, md: -8 },
          backgroundColor: 'var(--pt-surface)',
          border: '1px solid var(--pt-border)',
          boxShadow: 'var(--pt-shadow-md)',
          borderRadius: 'var(--pt-radius-md)',
          px: 1.5,
          py: 1,
          display: { xs: 'none', sm: 'flex' },
          alignItems: 'center',
          gap: 1,
        }}
      >
        <CheckCircleRoundedIcon sx={{ fontSize: 18, color: 'var(--pt-success)' }} />
        <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-text)' }}>Nova venda confirmada</Typography>
      </Box>
    </Box>
  )
}
