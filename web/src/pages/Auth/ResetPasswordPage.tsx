import { Alert, Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { AuthPageShell } from '../../components/auth/AuthPageShell'
import { PasswordField } from '../../components/form/PasswordField'
import { Logo } from '../../components/ui/Logo'
import * as authService from '../../services/authService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

const INVALID_TOKEN_CODE = 'INVALID_PASSWORD_RESET_TOKEN'

/** Pública, sem `ProtectedRoute`/`AppLayout` — mesmo padrão de `AcceptInvitePage`. */
export function ResetPasswordPage() {
  const { token } = useParams<{ token: string }>()
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
      setFormError('Link de redefinição inválido.')
      setIsInvalidToken(true)
      return
    }

    setFormError(null)
    setFieldErrors({})

    if (password !== passwordConfirmation) {
      setFieldErrors({ password_confirmation: ['A confirmação não corresponde à nova senha.'] })
      return
    }

    setIsSubmitting(true)
    try {
      await authService.resetPassword(token, password, passwordConfirmation)
      navigate('/login?reset=success', { replace: true })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível redefinir sua senha agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
        setIsInvalidToken(error.code === INVALID_TOKEN_CODE)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AuthPageShell
      headline="Defina sua nova senha"
      subheadline="Escolha uma senha forte para proteger o acesso ao Maskats."
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
          Redefinir senha
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)', mb: 3.5 }}>
          Crie uma nova senha de acesso para sua conta.
        </Typography>

        {!token ? (
          <Alert severity="error" variant="outlined" role="alert">
            Link de redefinição inválido. Solicite um novo link para continuar.
          </Alert>
        ) : (
          <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
            <Stack spacing={2.25}>
              {formError && (
                <Alert severity="error" variant="outlined" role="alert">
                  {formError}
                </Alert>
              )}

              {isInvalidToken ? (
                <Button component={RouterLink} to="/esqueci-senha" type="button" variant="contained" size="large">
                  Solicitar novo link
                </Button>
              ) : (
                <>
                  <PasswordField
                    label="Nova senha"
                    autoComplete="new-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    onGenerate={handleGeneratePassword}
                    error={Boolean(fieldErrors.password?.[0])}
                    helperText={fieldErrors.password?.[0] ?? PASSWORD_POLICY_HINT}
                    fullWidth
                    required
                    slotProps={{ htmlInput: { minLength: 12 } }}
                  />

                  <PasswordField
                    label="Confirmar nova senha"
                    autoComplete="new-password"
                    value={passwordConfirmation}
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                    error={Boolean(fieldErrors.password_confirmation?.[0])}
                    helperText={fieldErrors.password_confirmation?.[0]}
                    fullWidth
                    required
                  />

                  <Button type="submit" variant="contained" size="large" disabled={isSubmitting} sx={{ mt: 0.5 }}>
                    {isSubmitting ? 'Salvando…' : 'Redefinir senha'}
                  </Button>
                </>
              )}

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
