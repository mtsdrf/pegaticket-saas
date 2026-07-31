import { Alert, Box, Button, Link as MuiLink, Stack, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { formatCountdown, useCountdown } from '../../hooks/useCountdown'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

type OtpStep = 'email' | 'code'

/**
 * Identificação por e-mail + código OTP (`FinalCustomer`) — extraído de
 * `StorefrontCheckoutPage.tsx` (Delivery Fase 1) para reaproveitamento em
 * qualquer fluxo que precise da mesma identidade antes de agir (favoritar
 * produto no catálogo, Delivery Fase 4). Mesma lógica de `PortalLoginPage`,
 * via `usePortalAuth()` — não duplicar em nenhum lugar novo, sempre
 * reaproveitar este componente.
 */
export function OtpIdentifyForm({ onAuthenticated }: { onAuthenticated?: () => void }) {
  const { requestOtp, verifyOtp } = usePortalAuth()
  const [step, setStep] = useState<OtpStep>('email')
  const [email, setEmail] = useState('')
  const [code, setCode] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [infoMessage, setInfoMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isResending, setIsResending] = useState(false)
  const { secondsLeft, isExpired, restart } = useCountdown(0)

  async function handleRequestOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const expiresInMinutes = await requestOtp(email)
      setInfoMessage(`Enviamos um código de 6 dígitos para ${email}.`)
      restart(expiresInMinutes * 60)
      setStep('code')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível conectar ao servidor. Tente novamente.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleResendOtp() {
    setFormError(null)
    setIsResending(true)
    try {
      const expiresInMinutes = await requestOtp(email)
      setInfoMessage(`Reenviamos o código para ${email}.`)
      restart(expiresInMinutes * 60)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível reenviar o código agora. Tente novamente.'))
    } finally {
      setIsResending(false)
    }
  }

  async function handleVerifyOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      await verifyOtp(email, code)
      onAuthenticated?.()
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setFormError(
          error.code === 'TOO_MANY_ATTEMPTS'
            ? getApiErrorMessage(error, 'Muitas tentativas. Aguarde alguns minutos antes de tentar de novo.')
            : getApiErrorMessage(error, 'Código inválido ou expirado. Confira e tente novamente.'),
        )
        setFieldErrors(error.errors)
      } else {
        setFormError(getApiErrorMessage(error, 'Não foi possível conectar ao servidor. Tente novamente.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box>
      <Typography sx={{ fontSize: { xs: 18, sm: 20 }, fontWeight: 600, mb: 0.5 }}>Identifique-se para continuar</Typography>
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2.5 }}>
        {step === 'email'
          ? 'Informe seu e-mail para receber um código de confirmação.'
          : 'Digite o código de 6 dígitos que enviamos para o seu e-mail.'}
      </Typography>

      {infoMessage && (
        <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
          {infoMessage}
        </Alert>
      )}
      {formError && (
        <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 2 }}>
          {formError}
        </Alert>
      )}

      {step === 'email' && (
        <Box component="form" onSubmit={(event) => void handleRequestOtp(event)} noValidate>
          <Stack spacing={2}>
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
            <Button type="submit" variant="contained" size="large" disabled={isSubmitting}>
              {isSubmitting ? 'Enviando…' : 'Receber código por e-mail'}
            </Button>
          </Stack>
        </Box>
      )}

      {step === 'code' && (
        <Box component="form" onSubmit={(event) => void handleVerifyOtp(event)} noValidate>
          <Stack spacing={2}>
            <TextField
              label="Código de 6 dígitos"
              inputMode="numeric"
              autoComplete="one-time-code"
              autoFocus
              value={code}
              onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
              error={Boolean(fieldErrors.code?.[0])}
              helperText={fieldErrors.code?.[0]}
              slotProps={{ htmlInput: { maxLength: 6, style: { letterSpacing: '0.4em', fontWeight: 600 } } }}
              fullWidth
              required
            />
            <Typography sx={{ fontSize: 12.5, color: isExpired ? 'var(--mk-danger)' : 'var(--mk-muted)', textAlign: 'center' }}>
              {isExpired ? 'Código expirado' : `Código expira em ${formatCountdown(secondsLeft)}`}
            </Typography>
            <Button type="submit" variant="contained" size="large" disabled={isSubmitting || code.length !== 6}>
              {isSubmitting ? 'Verificando…' : 'Confirmar código'}
            </Button>
            <Stack direction="row" spacing={2} sx={{ justifyContent: 'center', flexWrap: 'wrap' }}>
              <MuiLink component="button" type="button" onClick={() => setStep('email')} sx={{ fontSize: 13 }}>
                Trocar e-mail
              </MuiLink>
              {isExpired && (
                <MuiLink component="button" type="button" onClick={() => void handleResendOtp()} disabled={isResending} sx={{ fontSize: 13 }}>
                  {isResending ? 'Reenviando…' : 'Reenviar código'}
                </MuiLink>
              )}
            </Stack>
          </Stack>
        </Box>
      )}
    </Box>
  )
}
