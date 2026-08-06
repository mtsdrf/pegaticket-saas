import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineOutlined'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useEffect, useRef, useState } from 'react'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { AuthPageShell } from '../../components/auth/AuthPageShell'
import { Logo } from '../../components/ui/Logo'
import * as profileService from '../../services/profileService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'

type ConfirmState = 'confirming' | 'success' | 'error'

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
    <AuthPageShell
      headline="Confirmando seu novo e-mail."
      subheadline="Mais um passo para manter seu acesso seguro e sua operacao pronta."
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
            <Typography sx={{ fontSize: 15, color: 'var(--pt-muted)' }}>Confirmando seu e-mail…</Typography>
          </Stack>
        )}

        {state === 'success' && (
          <Stack spacing={2} sx={{ alignItems: 'center' }}>
            <CheckCircleOutlineIcon sx={{ fontSize: 48, color: 'var(--pt-success)' }} />
            <Typography sx={{ fontSize: 20, fontWeight: 600 }}>E-mail confirmado!</Typography>
            <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)' }}>
              Seu e-mail de acesso foi atualizado. Faça login novamente para continuar.
            </Typography>
            <Button component={RouterLink} to="/login" variant="contained" size="large" fullWidth sx={{ mt: 1 }}>
              Ir para o login
            </Button>
          </Stack>
        )}

        {state === 'error' && (
          <Stack spacing={2} sx={{ alignItems: 'center' }}>
            <ErrorOutlineIcon sx={{ fontSize: 48, color: 'var(--pt-danger)' }} />
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
    </AuthPageShell>
  )
}
