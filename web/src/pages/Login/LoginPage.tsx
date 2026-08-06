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
import { AuthPageShell } from '../../components/auth/AuthPageShell'
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { useAuth } from '../../hooks/useAuth'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

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
    <AuthPageShell
      headline="O acesso ao evento comeca aqui."
      subheadline="Vendas, ingressos, check-in e operacao reunidos em uma marca feita para eventos em movimento."
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
          Bem-vindo ao PegaTicket
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'var(--pt-muted)', mb: 3.5 }}>
          Do ingresso ao check-in, tudo em um so lugar.
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
    </AuthPageShell>
  )
}
