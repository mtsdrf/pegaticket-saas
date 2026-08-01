import { Alert, Box, Button, Link as MuiLink, Paper, Stack, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { formatCountdown, useCountdown } from '../../hooks/useCountdown'
import { usePortalAuth } from '../../hooks/usePortalAuth'
import { createPortalLink } from '../../services/portalSaleService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

type Step = 'email' | 'code'

export function PortalLoginPage() {
  const { requestOtp, verifyOtp } = usePortalAuth()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const orderToLink = searchParams.get('vincular')
  const sessionExpired = searchParams.get('sessao') === 'expirada'

  const [step, setStep] = useState<Step>('email')
  const [email, setEmail] = useState('')
  const [code, setCode] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [infoMessage, setInfoMessage] = useState<string | null>(
    sessionExpired ? 'Sua sessão expirou. Faça login novamente para continuar.' : null,
  )
  const [isSubmitting, setIsSubmitting] = useState(false)
  const { secondsLeft, isExpired, restart } = useCountdown(0)

  async function handleRequestOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      const expiresInMinutes = await requestOtp(email)
      setInfoMessage(`Enviamos um código de 6 dígitos para ${email}. Confira sua caixa de entrada (e o spam).`)
      restart(expiresInMinutes * 60)
      setStep('code')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível conectar ao servidor. Tente novamente.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleVerifyOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await verifyOtp(email, code)

      if (orderToLink) {
        try {
          await createPortalLink({ order_uuid: orderToLink })
        } catch (linkError) {
          navigate('/portal/compras', {
            replace: true,
            state: {
              linkError: getApiErrorMessage(
                linkError,
                'Não foi possível vincular automaticamente o pedido que você estava vendo. Você já está logado — tente novamente pela tela de rastreio.',
              ),
            },
          })
          return
        }
      }

      navigate('/portal/compras', { replace: true })
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

  async function handleResend() {
    setFormError(null)
    setInfoMessage(null)
    setIsSubmitting(true)

    try {
      const expiresInMinutes = await requestOtp(email)
      setInfoMessage(`Reenviamos o código para ${email}.`)
      restart(expiresInMinutes * 60)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível reenviar o código agora. Tente novamente em instantes.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  function handleChangeEmail() {
    setStep('email')
    setCode('')
    setFormError(null)
    setInfoMessage(null)
    setFieldErrors({})
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        background:
          'var(--pt-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ width: '100%', maxWidth: 400 }}>
        <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', justifyContent: 'center', mb: 3 }}>
          <Logo size={40} />
        </Stack>

        <Paper
          elevation={0}
          sx={{
            ...ELEVATED_SURFACE_SX,
            p: { xs: 3, sm: 4 },
          }}
        >
          <Typography sx={{ fontSize: { xs: 19, sm: 21 }, fontWeight: 600, mb: 0.5 }}>
            Minha conta PegaTicket
          </Typography>
          <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', mb: 3 }}>
            {step === 'email'
              ? 'Entre com seu e-mail para ver os pedidos de todas as lojas que você confirmar que são suas.'
              : 'Digite o código de 6 dígitos que enviamos para o seu e-mail.'}
          </Typography>

          {orderToLink && (
            <Alert severity="info" variant="outlined" sx={{ mb: 2.25 }}>
              Ao entrar, vamos vincular automaticamente o pedido que você estava vendo à sua conta.
            </Alert>
          )}

          {infoMessage && (
            <Alert severity="info" variant="outlined" sx={{ mb: 2.25 }}>
              {infoMessage}
            </Alert>
          )}

          {formError && (
            <Alert severity="error" variant="outlined" role="alert" sx={{ mb: 2.25 }}>
              {formError}
            </Alert>
          )}

          {step === 'email' && (
            <Box component="form" onSubmit={handleRequestOtp} noValidate>
              <Stack spacing={2.25}>
                <TextField
                  label="E-mail"
                  name="email"
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
            <Box component="form" onSubmit={handleVerifyOtp} noValidate>
              <Stack spacing={2.25}>
                <TextField
                  label="Código de 6 dígitos"
                  name="code"
                  type="text"
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

                <Typography sx={{ fontSize: 12.5, color: isExpired ? 'var(--pt-danger)' : 'var(--pt-muted)', textAlign: 'center' }}>
                  {isExpired ? 'Código expirado' : `Código expira em ${formatCountdown(secondsLeft)}`}
                </Typography>

                <Button
                  type="submit"
                  variant="contained"
                  size="large"
                  disabled={isSubmitting || code.length !== 6}
                >
                  {isSubmitting ? 'Verificando…' : 'Entrar'}
                </Button>

                <Stack direction="row" sx={{ justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
                  <MuiLink component="button" type="button" onClick={handleChangeEmail} sx={{ fontSize: 13 }}>
                    Trocar e-mail
                  </MuiLink>
                  <MuiLink
                    component="button"
                    type="button"
                    onClick={handleResend}
                    sx={{ fontSize: 13 }}
                    disabled={isSubmitting || !isExpired}
                  >
                    Reenviar código
                  </MuiLink>
                </Stack>
              </Stack>
            </Box>
          )}
        </Paper>

        <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)', textAlign: 'center', mt: 3 }}>
          PegaTicket — do acesso a experiencia, tudo em movimento.
        </Typography>
      </Box>
    </Box>
  )
}
