import MarkEmailUnreadOutlinedIcon from '@mui/icons-material/MarkEmailUnreadOutlined'
import { Alert, Box, Button, Divider, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { AvatarUpload } from '../../components/account/AvatarUpload'
import { FormSection } from '../../components/form/FormSection'
import { PasswordField } from '../../components/form/PasswordField'
import { PageHeader } from '../../components/layout/PageHeader'
import { useUserProfile } from '../../hooks/useUserProfile'
import { FORM_GRID_2_SX, PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import * as profileService from '../../services/profileService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

function BasicDataSection() {
  const { profile, setProfile } = useUserProfile()
  const [name, setName] = useState(profile?.name ?? '')
  const [phone, setPhone] = useState(profile?.phone ?? '')
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (profile) {
      setName(profile.name)
      setPhone(profile.phone ?? '')
    }
  }, [profile])

  if (!profile) return null

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      const updated = await profileService.updateProfile(
        { name: name.trim(), phone: phone.trim() || null },
        avatarFile,
      )
      setProfile(updated)
      setAvatarFile(null)
      setSuccessMessage('Dados atualizados com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar seus dados agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <FormSection
      title="Dados básicos"
      description="Atualize seu nome, telefone e a foto usada nas áreas autenticadas do sistema."
    >
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        {formError && (
          <Alert severity="error" sx={{ mb: 2.5 }}>
            {formError}
          </Alert>
        )}
        {successMessage && (
          <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
            {successMessage}
          </Alert>
        )}

        <Stack spacing={2.5} sx={{ width: '100%' }}>
          <AvatarUpload name={profile.name} existingAvatarUrl={profile.avatar_url} onFileSelected={setAvatarFile} />

          <TextField
            label="Nome"
            value={name}
            onChange={(event) => setName(event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0]}
            required
            fullWidth
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />

          <TextField
            label="Telefone"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            error={Boolean(fieldErrors.phone)}
            helperText={fieldErrors.phone?.[0] ?? 'Usado como dado de contato da cobrança da assinatura.'}
            placeholder="(11) 91234-5678"
            autoComplete="tel"
            fullWidth
            slotProps={{ htmlInput: { maxLength: 30, inputMode: 'numeric' } }}
          />
        </Stack>

        <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
          <Button type="submit" variant="contained" disabled={isSubmitting} sx={{ minWidth: 140 }}>
            {isSubmitting ? 'Salvando…' : 'Salvar'}
          </Button>
        </Stack>
      </Box>
    </FormSection>
  )
}

function EmailSection() {
  const { profile, refresh } = useUserProfile()
  const [newEmail, setNewEmail] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  if (!profile) return null

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      await profileService.requestEmailChange({ new_email: newEmail.trim(), current_password: currentPassword })
      setSuccessMessage(
        `Enviamos um link de confirmação para ${newEmail.trim()}. O e-mail só será alterado depois que você confirmar pelo link.`,
      )
      setNewEmail('')
      setCurrentPassword('')
      await refresh()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível solicitar a troca de e-mail agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <FormSection
      title="E-mail"
      description="Gerencie o endereço usado para autenticação. A troca só é concluída após a confirmação no novo e-mail."
    >

      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: profile.pending_email ? 1.5 : 2.5 }}>
        <Typography sx={{ fontSize: 14.5, color: 'var(--pt-text)' }}>{profile.email}</Typography>
      </Stack>

      {profile.pending_email && (
        <Alert
          severity="warning"
          icon={<MarkEmailUnreadOutlinedIcon fontSize="inherit" />}
          sx={{ mb: 2.5 }}
        >
          Confirmação pendente para <strong>{profile.pending_email}</strong> — verifique sua caixa de entrada para
          concluir a troca.
        </Alert>
      )}

      <Divider sx={{ mb: 2.5 }} />

      <Typography sx={{ fontSize: 13, fontWeight: 500, color: 'var(--pt-text)', mb: 1.5 }}>
        Trocar e-mail de acesso
      </Typography>

      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        {formError && (
          <Alert severity="error" sx={{ mb: 2.5 }}>
            {formError}
          </Alert>
        )}
        {successMessage && (
          <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
            {successMessage}
          </Alert>
        )}

        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Novo e-mail"
            type="email"
            value={newEmail}
            onChange={(event) => setNewEmail(event.target.value)}
            error={Boolean(fieldErrors.new_email)}
            helperText={fieldErrors.new_email?.[0]}
            required
            autoComplete="email"
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
          <PasswordField
            label="Senha atual"
            value={currentPassword}
            onChange={(event) => setCurrentPassword(event.target.value)}
            error={Boolean(fieldErrors.current_password)}
            helperText={fieldErrors.current_password?.[0]}
            required
            autoComplete="current-password"
          />
        </Box>

        <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
          <Button type="submit" variant="contained" disabled={isSubmitting} sx={{ minWidth: 180 }}>
            {isSubmitting ? 'Enviando…' : 'Solicitar troca de e-mail'}
          </Button>
        </Stack>
      </Box>
    </FormSection>
  )
}

const EMPTY_PASSWORD_FORM = { current_password: '', new_password: '', new_password_confirmation: '' }

function PasswordSection() {
  const [form, setForm] = useState(EMPTY_PASSWORD_FORM)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateField(key: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function handleGeneratePassword() {
    const generated = generateStrongPassword()
    setForm((current) => ({ ...current, new_password: generated, new_password_confirmation: generated }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})

    if (form.new_password !== form.new_password_confirmation) {
      setFieldErrors({ new_password_confirmation: ['A confirmação não corresponde à nova senha.'] })
      return
    }

    setIsSubmitting(true)
    try {
      await profileService.changePassword(form)
      setSuccessMessage('Senha alterada com sucesso.')
      setForm(EMPTY_PASSWORD_FORM)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível alterar sua senha agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <FormSection
      title="Senha"
      description="Use uma senha forte, exclusiva e dentro da política atual de segurança da plataforma."
    >
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        {formError && (
          <Alert severity="error" sx={{ mb: 2.5 }}>
            {formError}
          </Alert>
        )}
        {successMessage && (
          <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
            {successMessage}
          </Alert>
        )}

        <Stack spacing={2} sx={{ width: '100%' }}>
          <PasswordField
            label="Senha atual"
            value={form.current_password}
            onChange={(event) => updateField('current_password', event.target.value)}
            error={Boolean(fieldErrors.current_password)}
            helperText={fieldErrors.current_password?.[0]}
            required
            autoComplete="current-password"
          />
          <PasswordField
            label="Nova senha"
            value={form.new_password}
            onChange={(event) => updateField('new_password', event.target.value)}
            onGenerate={handleGeneratePassword}
            error={Boolean(fieldErrors.new_password)}
            helperText={fieldErrors.new_password?.[0] ?? PASSWORD_POLICY_HINT}
            required
            autoComplete="new-password"
            slotProps={{ htmlInput: { maxLength: 255, minLength: 12 } }}
          />
          <PasswordField
            label="Confirmar nova senha"
            value={form.new_password_confirmation}
            onChange={(event) => updateField('new_password_confirmation', event.target.value)}
            error={Boolean(fieldErrors.new_password_confirmation)}
            helperText={fieldErrors.new_password_confirmation?.[0]}
            required
            autoComplete="new-password"
          />
        </Stack>

        <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
          <Button type="submit" variant="contained" disabled={isSubmitting} sx={{ minWidth: 140 }}>
            {isSubmitting ? 'Salvando…' : 'Alterar senha'}
          </Button>
        </Stack>
      </Box>
    </FormSection>
  )
}

/** Auto-serviço: qualquer usuário logado edita o próprio perfil, sem exigir permissão (`ProtectedRoute`, sem `PermissionRoute`). */
export function MyAccountPage() {
  const { profile, isLoading, error, refresh } = useUserProfile()

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: { xs: '100%', sm: 900, lg: 1100 } }}>
      <PageHeader title="Meus dados" subtitle="Gerencie suas informações de acesso ao PegaTicket." />

      {error && !profile && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={() => void refresh()}>
              Tentar novamente
            </Button>
          }
        >
          {error}
        </Alert>
      )}

      {isLoading && !profile ? (
        <Stack spacing={3}>
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={180} />
          ))}
        </Stack>
      ) : (
        profile && (
          <Stack spacing={3}>
            <BasicDataSection />
            <EmailSection />
            <PasswordSection />
          </Stack>
        )
      )}
    </Box>
  )
}
