import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineOutlined'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useEffect, useRef, useState } from 'react'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import * as profileService from '../../services/profileService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'

type ConfirmState = 'confirming' | 'success' | 'error'

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
          Confirmando seu novo e-mail.
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'color-mix(in srgb, #FFFFFF 78%, transparent)' }}>
          Só mais um passo pra manter sua conta segura e atualizada.
        </Typography>
      </Box>

      <Typography sx={{ position: 'relative', fontSize: 13, color: 'color-mix(in srgb, #FFFFFF 55%, transparent)' }}>
        © {new Date().getFullYear()} Maskats. Todos os direitos reservados.
      </Typography>
    </Box>
  )
}

/** Pública (fora de `ProtectedRoute`), clicada de um link de e-mail — mesmo padrão de `AcceptInvitePage`. */
export function ConfirmEmailPage() {
  const { token } = useParams<{ token: string }>()
  const [state, setState] = useState<ConfirmState>('confirming')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  // Token de confirmação é de uso único (não idempotente) — evita o duplo
  // efeito do StrictMode em dev disparar `confirmEmail` duas vezes e a
  // segunda chamada (token já consumido) sobrescrever um sucesso real com erro.
  const hasStartedRef = useRef(false)

  useEffect(() => {
    if (!token) {
      setState('error')
      setErrorMessage('Link de confirmação inválido.')
      return
    }

    if (hasStartedRef.current) return
    hasStartedRef.current = true

    profileService
      .confirmEmail(token)
      .then(() => setState('success'))
      .catch((error) => {
        setState('error')
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível confirmar seu e-mail agora.'))
      })
  }, [token])

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
        <Paper
          elevation={0}
          sx={{
            ...ELEVATED_SURFACE_SX,
            position: 'relative',
            width: '100%',
            maxWidth: 420,
            p: { xs: 3, sm: 5 },
            textAlign: 'center',
          }}
        >
          <Box sx={{ display: { xs: 'flex', md: 'none' }, justifyContent: 'center', mb: 3 }}>
            <Logo size={50} />
          </Box>

          {state === 'confirming' && (
            <Stack spacing={2} sx={{ alignItems: 'center', py: 2 }}>
              <CircularProgress size={36} />
              <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)' }}>Confirmando seu e-mail…</Typography>
            </Stack>
          )}

          {state === 'success' && (
            <Stack spacing={2} sx={{ alignItems: 'center' }}>
              <CheckCircleOutlineIcon sx={{ fontSize: 48, color: 'var(--mk-success)' }} />
              <Typography sx={{ fontSize: 20, fontWeight: 600 }}>E-mail confirmado!</Typography>
              <Typography sx={{ fontSize: 14.5, color: 'var(--mk-muted)' }}>
                Seu e-mail de acesso foi atualizado. Faça login novamente para continuar.
              </Typography>
              <Button component={RouterLink} to="/login" variant="contained" size="large" fullWidth sx={{ mt: 1 }}>
                Ir para o login
              </Button>
            </Stack>
          )}

          {state === 'error' && (
            <Stack spacing={2} sx={{ alignItems: 'center' }}>
              <ErrorOutlineIcon sx={{ fontSize: 48, color: 'var(--mk-danger)' }} />
              <Typography sx={{ fontSize: 20, fontWeight: 600 }}>Não foi possível confirmar</Typography>
              <Alert severity="error" variant="outlined" role="alert" sx={{ width: '100%', textAlign: 'left' }}>
                {errorMessage}
              </Alert>
              <Button component={RouterLink} to="/login" variant="outlined" size="large" fullWidth sx={{ mt: 1 }}>
                Voltar para o login
              </Button>
            </Stack>
          )}
        </Paper>
      </Box>
      <ThemeFab />
    </Box>
  )
}
