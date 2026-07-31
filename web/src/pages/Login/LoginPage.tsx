import {
  Alert,
  Box,
  Button,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useState, type FormEvent } from 'react'
import { PasswordField } from '../../components/form/PasswordField'
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import { useAuth } from '../../hooks/useAuth'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const HIGHLIGHTS = [
  'Pedidos, clientes e produtos em um só lugar',
  'Indicadores atualizados em tempo real',
  'Acesso por empresa com permissões por perfil',
]

function BrandPanel() {
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
            fontWeight: 700,
            fontSize: 22,
            letterSpacing: '-0.01em',
          }}
        >
          PegaTicket
        </Typography>
      </Box>

      <Box sx={{ position: 'relative', maxWidth: 400 }}>
        <Typography sx={{ fontSize: 30, fontWeight: 600, lineHeight: 1.25, mb: 1.5 }}>
          Gestão clara para empresas em movimento.
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'color-mix(in srgb, #FFFFFF 78%, transparent)', mb: 3 }}>
          Acompanhe pedidos, clientes e resultados do seu negócio em um painel único.
        </Typography>

        <Stack spacing={1.25}>
          {HIGHLIGHTS.map((item) => (
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

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const showResetSuccess = searchParams.get('reset') === 'success'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await login({ email, password })
      navigate('/', { replace: true })
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setFormError(getApiErrorMessage(error, 'Não foi possível entrar. Tente novamente.'))
        setFieldErrors(error.errors)
      } else {
        setFormError(getApiErrorMessage(error, 'Não foi possível conectar ao servidor. Tente novamente.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'grid',
        gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 1fr) minmax(0, 1fr)' },
      }}
    >
      <BrandPanel />

      <Box
        sx={{
          position: 'relative',
          overflow: 'hidden',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          p: { xs: 2, sm: 3 },
          background: {
            xs: 'linear-gradient(160deg, var(--pt-bg) 0%, var(--pt-surface-soft) 45%, color-mix(in srgb, var(--pt-primary) 16%, var(--pt-bg)) 100%)',
            md: 'var(--pt-bg)',
          },
        }}
      >
        <Box
          aria-hidden="true"
          sx={{
            display: { xs: 'block', md: 'none' },
            position: 'absolute',
            width: '18rem',
            height: '18rem',
            top: '-6rem',
            left: '-5rem',
            borderRadius: '50%',
            filter: 'blur(70px)',
            background: 'color-mix(in srgb, var(--pt-primary) 35%, transparent)',
          }}
        />
        <Box
          aria-hidden="true"
          sx={{
            display: { xs: 'block', md: 'none' },
            position: 'absolute',
            width: '14rem',
            height: '14rem',
            bottom: '-4rem',
            right: '-3rem',
            borderRadius: '50%',
            filter: 'blur(70px)',
            background: 'color-mix(in srgb, var(--pt-accent) 30%, transparent)',
          }}
        />

        <Paper
          className="pt-reveal"
          elevation={0}
          sx={{
            ...ELEVATED_SURFACE_SX,
            position: 'relative',
            width: '100%',
            maxWidth: 400,
            p: { xs: 3, sm: 5 },
          }}
        >
          <Box sx={{ display: { xs: 'flex', md: 'none' }, mb: 3 }}>
            <Logo size={50} />
          </Box>

          <Typography sx={{ fontSize: { xs: 20, sm: 22 }, fontWeight: 600, mb: 0.5 }}>
            Bem-vindo ao PegaTicket
          </Typography>
          <Typography sx={{ fontSize: 15, color: 'var(--pt-muted)', mb: 3.5 }}>
            Gestão clara para empresas em movimento.
          </Typography>

          <Box component="form" onSubmit={handleSubmit} noValidate>
            <Stack spacing={2.25}>
              {showResetSuccess && (
                <Alert severity="success" variant="outlined" role="status">
                  Senha redefinida com sucesso. Entre com sua nova senha.
                </Alert>
              )}

              {formError && (
                <Alert severity="error" variant="outlined" role="alert">
                  {formError}
                </Alert>
              )}

              <TextField
                label="E-mail"
                name="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                error={Boolean(fieldErrors.email?.[0])}
                helperText={fieldErrors.email?.[0]}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <PasswordField
                label="Senha"
                name="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                error={Boolean(fieldErrors.password?.[0])}
                helperText={fieldErrors.password?.[0]}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: -1.5 }}>
                <Typography
                  component={RouterLink}
                  to="/esqueci-senha"
                  sx={{
                    fontSize: 13.5,
                    color: 'var(--pt-primary)',
                    textDecoration: 'none',
                    fontWeight: 500,
                    minHeight: UI_SIZE.control,
                    display: 'flex',
                    alignItems: 'center',
                    '&:hover': { textDecoration: 'underline' },
                  }}
                >
                  Esqueci minha senha
                </Typography>
              </Box>

              <Button type="submit" variant="contained" size="large" disabled={isSubmitting} sx={{ mt: 0.5 }}>
                {isSubmitting ? 'Entrando…' : 'Entrar no painel'}
              </Button>

              <Button component={RouterLink} to="/cadastro" type="button" variant="text">
                Criar nova empresa
              </Button>
            </Stack>
          </Box>
        </Paper>
      </Box>
      <ThemeFab />
    </Box>
  )
}
