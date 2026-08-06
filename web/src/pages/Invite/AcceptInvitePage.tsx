import { Alert, Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { AuthPageShell } from '../../components/auth/AuthPageShell'
import { PasswordField } from '../../components/form/PasswordField'
import { Logo } from '../../components/ui/Logo'
import { useAuth } from '../../hooks/useAuth'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

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
    <AuthPageShell
      headline="Você foi convidado para o PegaTicket."
      subheadline="Defina sua senha e entre na operacao do evento com seguranca e fluidez."
    >
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
          Definir senha de acesso
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'var(--pt-muted)', mb: 3.5 }}>
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
    </AuthPageShell>
  )
}
