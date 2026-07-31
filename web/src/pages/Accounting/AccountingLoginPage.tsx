import { Alert, Box, Button, Link as MuiLink, Stack, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import { useAccountingAuth } from '../../hooks/useAccountingAuth'
import * as accountingAuthService from '../../services/accountingAuthService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { AccountingAuthLayout } from './AccountingAuthLayout'

export function AccountingLoginPage() {
  const { setSession } = useAccountingAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const params = new URLSearchParams(location.search)
  const state = location.state as { email?: string; totpConfirmed?: boolean } | null

  const [email, setEmail] = useState(state?.email ?? '')
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [infoMessage, setInfoMessage] = useState<string | null>(
    state?.totpConfirmed
      ? 'Autenticador ativado. Faça login com seu código atual para continuar.'
      : params.get('sessao') === 'expirada'
        ? 'Sua sessão expirou. Faça login novamente para continuar.'
        : null,
  )
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setInfoMessage(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      const tokens = await accountingAuthService.login({ email, password, code })
      await setSession(tokens.access_token)
      navigate('/contador/empresas', { replace: true })
    } catch (error) {
      // 403 TOTP_SETUP_REQUIRED: TOTP nunca foi confirmado — direciona pra confirmação.
      if (error instanceof ApiRequestError && error.code === 'TOTP_SETUP_REQUIRED') {
        setFormError(
          'Seu autenticador ainda não foi configurado. Conclua a ativação do código antes de entrar.',
        )
        setFieldErrors(error.errors)
      } else if (error instanceof ApiRequestError) {
        setFormError(getApiErrorMessage(error, 'E-mail, senha ou código inválidos. Tente novamente.'))
        setFieldErrors(error.errors)
      } else {
        setFormError(getApiErrorMessage(error, 'Não foi possível conectar ao servidor. Tente novamente.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const needsTotpSetup =
    formError !== null && formError.startsWith('Seu autenticador ainda não foi configurado')

  return (
    <AccountingAuthLayout
      title="Portal do contador"
      subtitle="Entre com seu e-mail, senha e o código do seu autenticador."
    >
      <Box component="form" onSubmit={handleSubmit} noValidate>
        <Stack spacing={2.25}>
          {infoMessage && (
            <Alert severity="info" variant="outlined">
              {infoMessage}
            </Alert>
          )}

          {formError && (
            <Alert
              severity={needsTotpSetup ? 'warning' : 'error'}
              variant="outlined"
              role="alert"
              action={
                needsTotpSetup ? (
                  <Button component={RouterLink} to="/contador/cadastro/confirmar-totp" state={{ email }} size="small">
                    Configurar
                  </Button>
                ) : undefined
              }
            >
              {formError}
            </Alert>
          )}

          <TextField
            label="E-mail"
            type="email"
            autoComplete="email"
            autoFocus
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={Boolean(fieldErrors.email?.[0])}
            helperText={fieldErrors.email?.[0]}
            fullWidth
            required
          />
          <TextField
            label="Senha"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={Boolean(fieldErrors.password?.[0])}
            helperText={fieldErrors.password?.[0]}
            fullWidth
            required
          />
          <TextField
            label="Código de 6 dígitos"
            inputMode="numeric"
            autoComplete="one-time-code"
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
            error={Boolean(fieldErrors.code?.[0])}
            helperText={fieldErrors.code?.[0]}
            slotProps={{ htmlInput: { maxLength: 6, style: { letterSpacing: '0.4em', fontWeight: 600 } } }}
            fullWidth
            required
          />

          <Button type="submit" variant="contained" size="large" disabled={isSubmitting || code.length !== 6}>
            {isSubmitting ? 'Entrando…' : 'Entrar'}
          </Button>

          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', textAlign: 'center' }}>
            Ainda não tem conta?{' '}
            <MuiLink component={RouterLink} to="/contador/cadastro">
              Cadastrar escritório
            </MuiLink>
          </Typography>
        </Stack>
      </Box>
    </AccountingAuthLayout>
  )
}
