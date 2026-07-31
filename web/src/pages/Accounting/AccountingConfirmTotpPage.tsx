import { Alert, Box, Button, Link as MuiLink, Stack, TextField } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import * as accountingAuthService from '../../services/accountingAuthService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { AccountingAuthLayout } from './AccountingAuthLayout'

/**
 * Confirmação do 1º código TOTP (habilita o login). O e-mail costuma chegar
 * por `location.state` (vindo do cadastro), mas o campo continua editável pra
 * quem abrir a tela direto.
 */
export function AccountingConfirmTotpPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const prefilledEmail = (location.state as { email?: string } | null)?.email ?? ''

  const [email, setEmail] = useState(prefilledEmail)
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await accountingAuthService.confirmTotp({ email, password, code })
      navigate('/contador/entrar', {
        replace: true,
        state: { email, totpConfirmed: true },
      })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível confirmar o código. Revise e tente novamente.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AccountingAuthLayout
      title="Confirme o código"
      subtitle="Digite o código de 6 dígitos que aparece no seu app autenticador para ativar o acesso."
    >
      <Box component="form" onSubmit={handleSubmit} noValidate>
        <Stack spacing={2.25}>
          {formError && (
            <Alert severity="error" variant="outlined" role="alert">
              {formError}
            </Alert>
          )}

          <TextField
            label="E-mail"
            type="email"
            autoComplete="email"
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
            {isSubmitting ? 'Confirmando…' : 'Confirmar e ativar'}
          </Button>

          <MuiLink component={RouterLink} to="/contador/entrar" sx={{ fontSize: 13, textAlign: 'center' }}>
            Voltar para o login
          </MuiLink>
        </Stack>
      </Box>
    </AccountingAuthLayout>
  )
}
