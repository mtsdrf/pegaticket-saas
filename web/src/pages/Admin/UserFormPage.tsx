import { Box, FormControl, FormControlLabel, InputLabel, MenuItem, Select, Stack, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { FormSection } from '../../components/form/FormSection'
import { PasswordField } from '../../components/form/PasswordField'
import * as adminGroupService from '../../services/adminGroupService'
import * as adminUserService from '../../services/adminUserService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { AdminGroup } from '../../types/admin'
import { PASSWORD_POLICY_HINT, generateStrongPassword } from '../../utils/password'

export function UserFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [groups, setGroups] = useState<AdminGroup[]>([])
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [groupUuids, setGroupUuids] = useState<string[]>([])
  const [canManageGroups, setCanManageGroups] = useState(true)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    setLoadError(null)
    Promise.allSettled([adminGroupService.listGroups({ per_page: 100 }), uuid ? adminUserService.getUser(uuid) : Promise.resolve(null)])
      .then(([groupResult, userResult]) => {
        if (groupResult.status === 'fulfilled') {
          setGroups(groupResult.value.items)
          setCanManageGroups(true)
        } else {
          setGroups([])
          setCanManageGroups(false)
        }

        if (userResult.status === 'fulfilled') {
          const user = userResult.value
          if (user) {
            setName(user.name)
            setEmail(user.email)
            setIsActive(user.is_active)
            setGroupUuids(user.groups?.map((group) => group.uuid) ?? [])
          }
        } else {
          setLoadError(getApiErrorMessage(userResult.reason, 'Não foi possível carregar os dados do usuário agora.'))
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do usuário agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  function handleGeneratePassword() {
    setPassword(generateStrongPassword())
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const payload = {
        name: name.trim(),
        email: email.trim(),
        ...(password.trim() ? { password: password.trim() } : {}),
        is_active: isActive,
        group_uuids: groupUuids,
      }
      if (uuid) await adminUserService.updateUser(uuid, payload)
      else await adminUserService.createUser(payload)
      navigate('/admin/usuarios')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o usuário agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Usuários"
      backTo="/admin/usuarios"
      title={isEditMode ? 'Editar usuário' : 'Novo usuário'}
      subtitle={isEditMode ? 'Atualize os dados do usuário.' : 'Cadastre um novo usuário do sistema.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/usuarios' }, { label: 'Usuários', to: '/admin/usuarios' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <FormSection title="Dados principais" description="Cadastre identidade, credencial de acesso e vínculo com grupos administrativos.">
        <Stack spacing={2}>
          <Box sx={FORM_GRID_2_SX}>
            <TextField
              label="Nome"
              value={name}
              onChange={(e) => setName(e.target.value)}
              error={Boolean(fieldErrors.name)}
              helperText={fieldErrors.name?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <TextField
              label="E-mail"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              error={Boolean(fieldErrors.email)}
              helperText={fieldErrors.email?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <PasswordField
              label={isEditMode ? 'Nova senha (opcional)' : 'Senha'}
              autoComplete={isEditMode ? 'new-password' : 'new-password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              onGenerate={handleGeneratePassword}
              error={Boolean(fieldErrors.password)}
              helperText={fieldErrors.password?.[0] ?? PASSWORD_POLICY_HINT}
              required={!isEditMode}
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255, minLength: 12 } }}
            />
            {canManageGroups && (
              <FormControl fullWidth>
                <InputLabel id="user-groups">Grupos</InputLabel>
                <Select
                  labelId="user-groups"
                  label="Grupos"
                  multiple
                  value={groupUuids}
                  onChange={(e) => setGroupUuids(typeof e.target.value === 'string' ? e.target.value.split(',') : e.target.value)}
                >
                  {groups.map((group) => (
                    <MenuItem key={group.uuid} value={group.uuid}>{group.name}</MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}
          </Box>
          <FormControlLabel control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />} label="Usuário ativo" />
        </Stack>
      </FormSection>
    </CrudFormShell>
  )
}
