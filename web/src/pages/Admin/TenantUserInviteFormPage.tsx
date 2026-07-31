import { Alert, Stack, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import * as tenantRoleService from '../../services/tenantRoleService'
import * as tenantUserService from '../../services/tenantUserService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { TenantRole } from '../../types/admin'

export function TenantUserInviteFormPage() {
  const navigate = useNavigate()
  const [roles, setRoles] = useState<TenantRole[]>([])
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [roleUuid, setRoleUuid] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    tenantRoleService
      .listTenantRoles({ per_page: 100 })
      .then((result) => setRoles(result.items))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os perfis da empresa agora.')))
      .finally(() => setIsLoading(false))
  }, [])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      await tenantUserService.inviteTenantUser({ name, email, role_uuid: roleUuid })
      setSuccessMessage('Convite enviado! A pessoa vai receber um e-mail para definir a senha e acessar.')
      setTimeout(() => navigate('/admin/tenant-users'), 1500)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível enviar o convite agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Usuários da empresa"
      backTo="/admin/tenant-users"
      title="Convidar usuário"
      subtitle="Envie um convite por e-mail para uma pessoa ainda sem conta acessar esta empresa."
      breadcrumbs={[
        { label: 'Administração', to: '/admin/tenant-users' },
        { label: 'Usuários da empresa', to: '/admin/tenant-users' },
        { label: 'Convidar' },
      ]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
      submitLabel="Enviar convite"
      submittingLabel="Enviando…"
    >
      <Stack spacing={2}>
        {successMessage && (
          <Alert severity="success" role="status">
            {successMessage}
          </Alert>
        )}

        <TextField
          label="Nome"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={Boolean(fieldErrors.name?.[0])}
          helperText={fieldErrors.name?.[0]}
          fullWidth
          required
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />

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
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />

        <LocalAutocomplete
          label="Perfil"
          fullWidth
          required
          options={roles}
          value={roles.find((role) => role.uuid === roleUuid) ?? null}
          onChange={(role) => setRoleUuid(role?.uuid ?? '')}
          getOptionLabel={(role) => role.name}
          getOptionKey={(role) => role.uuid}
          error={Boolean(fieldErrors.role_uuid?.[0])}
          helperText={fieldErrors.role_uuid?.[0]}
        />
      </Stack>
    </CrudFormShell>
  )
}
