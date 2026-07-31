import { FormControlLabel, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { PasswordField } from '../../components/form/PasswordField'
import { useTenants } from '../../hooks/useTenants'
import * as adminUserService from '../../services/adminUserService'
import * as tenantRoleService from '../../services/tenantRoleService'
import * as tenantUserService from '../../services/tenantUserService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { AdminUser, TenantRole } from '../../types/admin'

export function TenantUserFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const { tenants } = useTenants()
  const [users, setUsers] = useState<AdminUser[]>([])
  const [roles, setRoles] = useState<TenantRole[]>([])
  const [tenantUuid, setTenantUuid] = useState('')
  const [userUuid, setUserUuid] = useState('')
  const [roleUuid, setRoleUuid] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [createNewUser, setCreateNewUser] = useState(false)
  const [userName, setUserName] = useState('')
  const [userEmail, setUserEmail] = useState('')
  const [userPassword, setUserPassword] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    Promise.all([adminUserService.listUsers({ per_page: 100 }), tenantRoleService.listTenantRoles({ per_page: 100 }), uuid ? tenantUserService.getTenantUser(uuid) : Promise.resolve(null)])
      .then(([userResult, roleResult, tenantUser]) => {
        setUsers(userResult.items)
        setRoles(roleResult.items)
        if (tenantUser) {
          setTenantUuid(tenantUser.tenant_uuid)
          setUserUuid(tenantUser.user_uuid)
          setRoleUuid(tenantUser.role_uuid)
          setIsActive(tenantUser.is_active)
        } else if (tenants?.length === 1) {
          setTenantUuid(tenants[0].tenant_uuid)
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do vínculo agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid, tenants])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const payload = createNewUser
        ? {
            tenant_uuid: tenantUuid,
            role_uuid: roleUuid,
            is_active: isActive,
            user: {
              name: userName,
              email: userEmail,
              password: userPassword,
            },
          }
        : { tenant_uuid: tenantUuid, user_uuid: userUuid, role_uuid: roleUuid, is_active: isActive }

      if (uuid) await tenantUserService.updateTenantUser(uuid, { role_uuid: roleUuid, is_active: isActive })
      else await tenantUserService.createTenantUser(payload)
      navigate('/admin/tenant-users')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o vínculo agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Usuários da empresa"
      backTo="/admin/tenant-users"
      title={isEditMode ? 'Editar vínculo da empresa' : 'Novo vínculo da empresa'}
      subtitle={isEditMode ? 'Atualize perfil e status do vínculo.' : 'Relacione um usuário a uma empresa e perfil.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/tenant-users' }, { label: 'Usuários da empresa', to: '/admin/tenant-users' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2}>
        <LocalAutocomplete
          label="Empresa"
          fullWidth
          required
          disabled={isEditMode}
          options={tenants ?? []}
          value={(tenants ?? []).find((tenant) => tenant.tenant_uuid === tenantUuid) ?? null}
          onChange={(tenant) => setTenantUuid(tenant?.tenant_uuid ?? '')}
          getOptionLabel={(tenant) => tenant.tenant_name}
          getOptionKey={(tenant) => tenant.tenant_uuid}
          error={Boolean(fieldErrors.tenant_uuid)}
          helperText={fieldErrors.tenant_uuid?.[0]}
        />
        <LocalAutocomplete
          label="Usuário"
          fullWidth
          required
          disabled={isEditMode || createNewUser}
          options={users}
          value={users.find((user) => user.uuid === userUuid) ?? null}
          onChange={(user) => setUserUuid(user?.uuid ?? '')}
          getOptionLabel={(user) => `${user.name}${user.email ? ` • ${user.email}` : ''}`}
          getOptionKey={(user) => user.uuid}
          error={Boolean(fieldErrors.user_uuid) && !createNewUser}
          helperText={createNewUser ? 'Desative a opção abaixo para escolher um usuário já existente.' : fieldErrors.user_uuid?.[0]}
        />
        {!isEditMode && (
          <>
            <FormControlLabel
              control={
                <Switch
                  checked={createNewUser}
                  onChange={(event) => {
                    const checked = event.target.checked
                    setCreateNewUser(checked)
                    if (checked) {
                      setUserUuid('')
                    } else {
                      setUserName('')
                      setUserEmail('')
                      setUserPassword('')
                    }
                  }}
                />
              }
              label="Cadastrar um novo usuário agora"
            />
            {createNewUser && (
              <Stack spacing={2}>
                <Typography variant="body2" sx={{ color: 'var(--pt-muted)' }}>
                  O sistema criará o usuário e já fará o vínculo com a empresa e o perfil selecionado.
                </Typography>
                <TextField
                  label="Nome do usuário"
                  value={userName}
                  onChange={(event) => setUserName(event.target.value)}
                  required
                  fullWidth
                  error={Boolean(fieldErrors['user.name'])}
                  helperText={fieldErrors['user.name']?.[0]}
                />
                <TextField
                  label="E-mail do usuário"
                  type="email"
                  value={userEmail}
                  onChange={(event) => setUserEmail(event.target.value)}
                  required
                  fullWidth
                  error={Boolean(fieldErrors['user.email'])}
                  helperText={fieldErrors['user.email']?.[0]}
                />
                <PasswordField
                  label="Senha provisória"
                  value={userPassword}
                  onChange={(event) => setUserPassword(event.target.value)}
                  required
                  fullWidth
                  error={Boolean(fieldErrors['user.password'])}
                  helperText={fieldErrors['user.password']?.[0] ?? 'Depois o usuário poderá trocar a própria senha.'}
                />
              </Stack>
            )}
          </>
        )}
        <LocalAutocomplete
          label="Perfil"
          fullWidth
          required
          options={roles}
          value={roles.find((role) => role.uuid === roleUuid) ?? null}
          onChange={(role) => setRoleUuid(role?.uuid ?? '')}
          getOptionLabel={(role) => role.name}
          getOptionKey={(role) => role.uuid}
          error={Boolean(fieldErrors.role_uuid)}
          helperText={fieldErrors.role_uuid?.[0]}
        />
        <FormControlLabel control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />} label="Vínculo ativo" />
      </Stack>
    </CrudFormShell>
  )
}
