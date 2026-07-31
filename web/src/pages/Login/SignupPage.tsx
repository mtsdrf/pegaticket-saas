import {
  Alert,
  Box,
  Button,
  Checkbox,
  FormControlLabel,
  Link,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import { PasswordField } from '../../components/form/PasswordField'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import { useAuth } from '../../hooks/useAuth'
import * as authService from '../../services/authService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { PublicPlan } from '../../types/auth'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

const HIGHLIGHTS = [
  'Crie sua empresa e já entre no ambiente inicial',
  'O teste é liberado automaticamente no primeiro acesso',
  'A escolha do plano só será necessária quando o período de teste terminar',
]

function normalizeTenantSlug(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 255)
}

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

      <Box sx={{ position: 'relative', maxWidth: 420 }}>
        <Typography sx={{ fontSize: 30, fontWeight: 600, lineHeight: 1.25, mb: 1.5 }}>
          Comece sua operação sem depender de suporte.
        </Typography>
        <Typography sx={{ fontSize: 15, color: 'color-mix(in srgb, #FFFFFF 78%, transparent)', mb: 3 }}>
          Escolha o plano, crie a empresa e já entre com o proprietário inicial pronto para configurar o ambiente.
        </Typography>

        <Stack spacing={1.25}>
          {HIGHLIGHTS.map((item) => (
            <Box key={item} sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
              <Box
                aria-hidden="true"
                sx={{
                  width: 6,
                  height: 6,
                  borderRadius: '50%',
                  bgcolor: 'var(--mk-accent)',
                  flexShrink: 0,
                }}
              />
              <Typography sx={{ fontSize: 14, color: 'color-mix(in srgb, #FFFFFF 85%, transparent)' }}>
                {item}
              </Typography>
            </Box>
          ))}
        </Stack>
      </Box>

      <Typography sx={{ position: 'relative', fontSize: 13, color: 'color-mix(in srgb, #FFFFFF 55%, transparent)' }}>
        © {new Date().getFullYear()} Maskats. Todos os direitos reservados.
      </Typography>
    </Box>
  )
}

export function SignupPage() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [plans, setPlans] = useState<PublicPlan[]>([])
  const [ownerName, setOwnerName] = useState('')
  const [ownerEmail, setOwnerEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [tenantName, setTenantName] = useState('')
  const [tenantSlug, setTenantSlug] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [hasEditedSlug, setHasEditedSlug] = useState(false)
  const [termsAccepted, setTermsAccepted] = useState(false)
  const [privacyAccepted, setPrivacyAccepted] = useState(false)

  useEffect(() => {
    let cancelled = false

    authService
      .listSignupPlans()
      .then((data) => {
        if (cancelled) return
        setPlans(data)
      })
      .catch(() => undefined)

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (hasEditedSlug) return
    setTenantSlug(normalizeTenantSlug(tenantName))
  }, [tenantName, hasEditedSlug])

  const trialPlan = useMemo(() => plans.find((plan) => plan.is_trial_default) ?? plans[0] ?? null, [plans])
  const trialDays = trialPlan?.trial_days ?? 30

  function handleGeneratePassword() {
    const generated = generateStrongPassword()
    setPassword(generated)
    setPasswordConfirmation(generated)
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await register({
        owner_name: ownerName,
        owner_email: ownerEmail,
        password,
        password_confirmation: passwordConfirmation,
        tenant_name: tenantName,
        tenant_slug: tenantSlug,
        terms_accepted: true,
        privacy_accepted: true,
      })
      navigate('/', { replace: true })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível concluir seu cadastro agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

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
          className="mk-reveal"
          elevation={0}
          sx={{
            ...ELEVATED_SURFACE_SX,
            position: 'relative',
            width: '100%',
            maxWidth: 520,
            p: { xs: 3, sm: 5 },
          }}
        >
          <Box sx={{ display: { xs: 'flex', md: 'none' }, mb: 3 }}>
            <Logo size={50} />
          </Box>

          <Typography sx={{ fontSize: { xs: 20, sm: 22 }, fontWeight: 600, mb: 0.5 }}>
            Criar empresa no Maskats
          </Typography>
          <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)', mb: 3.5 }}>
            Configure o proprietário inicial e a empresa. O período de teste é ativado automaticamente no primeiro acesso.
          </Typography>

          <Box component="form" onSubmit={handleSubmit} noValidate>
            <Stack spacing={2.25}>
              {formError && (
                <Alert severity="error" variant="outlined" role="alert">
                  {formError}
                </Alert>
              )}

              <Alert severity="info" variant="outlined">
                Seu cadastro começa com <strong>{trialDays} dias de teste</strong>
                {trialPlan ? ` usando os recursos do plano ${trialPlan.name}` : ''}. A escolha do plano definitivo só será necessária após esse período.
              </Alert>

              <TextField
                label="Nome do proprietário"
                value={ownerName}
                onChange={(event) => setOwnerName(event.target.value)}
                error={Boolean(fieldErrors.owner_name?.[0])}
                helperText={fieldErrors.owner_name?.[0]}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <TextField
                label="E-mail do proprietário"
                type="email"
                autoComplete="email"
                value={ownerEmail}
                onChange={(event) => setOwnerEmail(event.target.value)}
                error={Boolean(fieldErrors.owner_email?.[0])}
                helperText={fieldErrors.owner_email?.[0]}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

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
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <TextField
                label="Nome da empresa"
                value={tenantName}
                onChange={(event) => setTenantName(event.target.value)}
                error={Boolean(fieldErrors.tenant_name?.[0])}
                helperText={fieldErrors.tenant_name?.[0]}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <TextField
                label="Identificador da empresa"
                value={tenantSlug}
                onChange={(event) => {
                  setHasEditedSlug(true)
                  setTenantSlug(normalizeTenantSlug(event.target.value))
                }}
                error={Boolean(fieldErrors.tenant_slug?.[0])}
                helperText={fieldErrors.tenant_slug?.[0] ?? 'Usado como identificador técnico único da empresa.'}
                fullWidth
                required
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              <Stack spacing={1}>
                <FormControlLabel
                  sx={{ alignItems: 'flex-start', m: 0 }}
                  control={
                    <Checkbox
                      checked={termsAccepted}
                      onChange={(event) => setTermsAccepted(event.target.checked)}
                      sx={{ pt: 0 }}
                      required
                    />
                  }
                  label={
                    <Typography sx={{ fontSize: 14, color: 'var(--mk-text)' }}>
                      Li e aceito os{' '}
                      <Link component={RouterLink} to="/termos" target="_blank" rel="noopener" underline="hover">
                        Termos de Uso
                      </Link>
                      .
                    </Typography>
                  }
                />

                <FormControlLabel
                  sx={{ alignItems: 'flex-start', m: 0 }}
                  control={
                    <Checkbox
                      checked={privacyAccepted}
                      onChange={(event) => setPrivacyAccepted(event.target.checked)}
                      sx={{ pt: 0 }}
                      required
                    />
                  }
                  label={
                    <Typography sx={{ fontSize: 14, color: 'var(--mk-text)' }}>
                      Li e aceito a{' '}
                      <Link component={RouterLink} to="/privacidade" target="_blank" rel="noopener" underline="hover">
                        Política de Privacidade
                      </Link>
                      .
                    </Typography>
                  }
                />
              </Stack>

              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={isSubmitting || !termsAccepted || !privacyAccepted}
              >
                {isSubmitting ? 'Criando ambiente…' : 'Criar empresa e iniciar teste'}
              </Button>

              <Button component={RouterLink} to="/login" type="button" variant="text">
                Já tenho acesso
              </Button>
            </Stack>
          </Box>
        </Paper>
      </Box>
      <ThemeFab />
    </Box>
  )
}
