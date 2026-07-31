import { Alert, Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { PasswordField } from '../../components/form/PasswordField'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import { useAuth } from '../../hooks/useAuth'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

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
          'linear-gradient(155deg, var(--mk-primary) 0%, color-mix(in srgb, var(--mk-primary) 65%, black) 55%, color-mix(in srgb, var(--mk-accent) 45%, black) 100%)',
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
          background: 'color-mix(in srgb, var(--mk-accent) 55%, transparent)',
          '@keyframes mk-float-a': {
            '0%, 100%': { transform: 'translate(0, 0)' },
            '50%': { transform: 'translate(-24px, 28px)' },
          },
          animation: 'mk-float-a 14s ease-in-out infinite',
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
          '@keyframes mk-float-b': {
            '0%, 100%': { transform: 'translate(0, 0)' },
            '50%': { transform: 'translate(20px, -18px)' },
          },
          animation: 'mk-float-b 16s ease-in-out infinite',
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
          Maskats
        </Typography>
      </Box>

      <Box sx={{ position: 'relative', maxWidth: 400 }}>
        <Typography sx={{ fontSize: 30, fontWeight: 600, lineHeight: 1.25, mb: 1.5 }}>
          Você foi convidado para o Maskats.
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'color-mix(in srgb, #FFFFFF 78%, transparent)', mb: 3 }}>
          Defina sua senha de acesso e comece a acompanhar a operação da empresa.
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
                  bgcolor: 'var(--mk-accent)',
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
        © {new Date().getFullYear()} Maskats. Todos os direitos reservados.
      </Typography>
    </Box>
  )
}

export function AcceptInvitePage() {
  const { token } = useParams<{ token: string }>()
  const { acceptInvite } = useAuth()
  const navigate = useNavigate()

  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isInvalidToken, setIsInvalidToken] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function handleGeneratePassword() {
    const generated = generateStrongPassword()
    setPassword(generated)
    setPasswordConfirmation(generated)
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!token) {
      setFormError('Link de convite inválido.')
      setIsInvalidToken(true)
      return
    }

    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await acceptInvite({ token, password, password_confirmation: passwordConfirmation })
      navigate('/', { replace: true })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível concluir o convite agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
        setIsInvalidToken(error.code === 'INVALID_INVITE_TOKEN')
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
            xs: 'linear-gradient(160deg, var(--mk-bg) 0%, var(--mk-surface-soft) 45%, color-mix(in srgb, var(--mk-primary) 16%, var(--mk-bg)) 100%)',
            md: 'var(--mk-bg)',
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
            background: 'color-mix(in srgb, var(--mk-primary) 35%, transparent)',
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
            background: 'color-mix(in srgb, var(--mk-accent) 30%, transparent)',
          }}
        />

        <Paper
          className="mk-reveal"
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
            Definir senha de acesso
          </Typography>
          <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)', mb: 3.5 }}>
            Crie uma senha para ativar seu acesso ao painel da empresa.
          </Typography>

          {!token ? (
            <Alert severity="error" variant="outlined" role="alert">
              Link de convite inválido. Verifique o link recebido por e-mail ou peça um novo convite.
            </Alert>
          ) : (
            <Box component="form" onSubmit={handleSubmit} noValidate>
              <Stack spacing={2.25}>
                {formError && (
                  <Alert severity="error" variant="outlined" role="alert">
                    {formError}
                    {isInvalidToken && ' Peça um novo convite para quem administra a empresa.'}
                  </Alert>
                )}

                <PasswordField
                  label="Senha"
                  autoComplete="new-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  onGenerate={handleGeneratePassword}
                  error={Boolean(fieldErrors.password?.[0])}
                  helperText={fieldErrors.password?.[0] ?? PASSWORD_POLICY_HINT}
                  fullWidth
                  required
                  disabled={isInvalidToken}
                  slotProps={{ htmlInput: { maxLength: 255, minLength: 12 } }}
                />

                <PasswordField
                  label="Confirmar senha"
                  autoComplete="new-password"
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  error={Boolean(fieldErrors.password_confirmation?.[0])}
                  helperText={fieldErrors.password_confirmation?.[0]}
                  fullWidth
                  required
                  disabled={isInvalidToken}
                  slotProps={{ htmlInput: { maxLength: 255 } }}
                />

                <Button
                  type="submit"
                  variant="contained"
                  size="large"
                  disabled={isSubmitting || isInvalidToken}
                  sx={{ mt: 0.5 }}
                >
                  {isSubmitting ? 'Ativando acesso…' : 'Ativar acesso'}
                </Button>

                <Button component={RouterLink} to="/login" type="button" variant="text">
                  Já tenho acesso
                </Button>
              </Stack>
            </Box>
          )}
        </Paper>
      </Box>
      <ThemeFab />
    </Box>
  )
}
