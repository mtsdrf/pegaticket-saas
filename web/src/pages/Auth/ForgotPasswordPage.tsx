import { Alert, Box, Button, Paper, Stack, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { AuthPageShell } from '../../components/auth/AuthPageShell'
import { Logo } from '../../components/ui/Logo'
import * as authService from '../../services/authService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/**
 * Pública, sem `ProtectedRoute`/`AppLayout` (mesmo padrão de `AcceptInvitePage`).
 * A resposta da API é SEMPRE tratada como sucesso visual — o backend nunca revela
 * se o e-mail existe (`POST /auth/forgot-password` sempre 200). Só o erro de
 * formato de e-mail (validação client-side) aparece como erro de campo normal;
 * qualquer erro de rede/servidor também cai no mesmo estado de sucesso genérico
 * para não vazar nenhuma informação sobre a existência da conta.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [fieldError, setFieldError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFieldError(null)

    const trimmed = email.trim()
    if (!EMAIL_PATTERN.test(trimmed)) {
      setFieldError('Informe um e-mail válido.')
      return
    }

    setIsSubmitting(true)
    try {
      await authService.forgotPassword(trimmed)
    } catch {
      // Silenciado de propósito: a tela sempre mostra a mesma mensagem de
      // sucesso genérica, mesmo em erro de rede/servidor, para não vazar
      // se o e-mail existe ou se algo falhou no backend.
    } finally {
      setIsSubmitting(false)
      setIsSubmitted(true)
    }
  }

  return (
    <AuthPageShell
      headline="Recupere seu acesso"
      subheadline="Enviamos um link seguro para você redefinir sua senha."
    >
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
          Esqueci minha senha
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)', mb: 3.5 }}>
          Informe o e-mail da sua conta e enviaremos um link para redefinir sua senha.
        </Typography>

        {isSubmitted ? (
          <Stack spacing={2.25}>
            <Alert severity="success" variant="outlined" role="status">
              Se o e-mail informado estiver cadastrado, você receberá um link para redefinir sua senha em instantes.
              Verifique também sua caixa de spam.
            </Alert>
            <Button component={RouterLink} to="/login" type="button" variant="contained" size="large">
              Voltar para o login
            </Button>
          </Stack>
        ) : (
          <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
            <Stack spacing={2.25}>
              <TextField
                label="E-mail"
                name="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                error={Boolean(fieldError)}
                helperText={fieldError}
                fullWidth
                required
                autoFocus
              />

              <Button type="submit" variant="contained" size="large" disabled={isSubmitting} sx={{ mt: 0.5 }}>
                {isSubmitting ? 'Enviando…' : 'Enviar link de redefinição'}
              </Button>

              <Button component={RouterLink} to="/login" type="button" variant="text">
                Voltar para o login
              </Button>
            </Stack>
          </Box>
        )}
      </Paper>
    </AuthPageShell>
  )
}
