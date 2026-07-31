import { Box, FormControl, FormControlLabel, InputLabel, MenuItem, Select, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import * as adminGroupService from '../../services/adminGroupService'
import * as adminUserService from '../../services/adminUserService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { AdminUser } from '../../types/admin'

export function GroupFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [users, setUsers] = useState<AdminUser[]>([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [userUuids, setUserUuids] = useState<string[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    setLoadError(null)
    Promise.all([adminUserService.listUsers({ per_page: 100 }), uuid ? adminGroupService.getGroup(uuid) : Promise.resolve(null)])
      .then(([userResult, group]) => {
        setUsers(userResult.items)
        if (group) {
          setName(group.name)
          setSlug(group.slug)
          setIsActive(group.is_active)
          setUserUuids(group.users?.map((user) => user.uuid) ?? [])
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do grupo agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const payload = { name: name.trim(), slug: slug.trim(), is_active: isActive }
      const group = uuid ? await adminGroupService.updateGroup(uuid, payload) : await adminGroupService.createGroup(payload)
      await adminGroupService.syncGroupUsers(group.uuid, userUuids)
      navigate('/admin/grupos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o grupo agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Grupos"
      backTo="/admin/grupos"
      title={isEditMode ? 'Editar grupo' : 'Novo grupo'}
      subtitle={isEditMode ? 'Atualize os dados do grupo.' : 'Cadastre um novo grupo sistêmico.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/grupos' }, { label: 'Grupos', to: '/admin/grupos' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2}>
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2 }}>
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
            label="Abreviatura"
            value={slug}
            onChange={(e) => setSlug(e.target.value)}
            error={Boolean(fieldErrors.slug)}
            helperText={fieldErrors.slug?.[0]}
            required
            fullWidth
            slotProps={{ htmlInput: { maxLength: 100 } }}
          />
        </Box>
        <FormControl fullWidth>
          <InputLabel id="group-users">Usuários do grupo</InputLabel>
          <Select
            labelId="group-users"
            label="Usuários do grupo"
            multiple
            value={userUuids}
            onChange={(e) => setUserUuids(typeof e.target.value === 'string' ? e.target.value.split(',') : e.target.value)}
          >
            {users.map((user) => <MenuItem key={user.uuid} value={user.uuid}>{user.name}</MenuItem>)}
          </Select>
        </FormControl>
        <Typography variant="caption" sx={{ color: 'var(--pt-muted)' }}>
          Permissões de grupo ainda não têm leitura dedicada na API; por segurança, esta tela gerencia apenas os dados básicos e os usuários vinculados.
        </Typography>
        <FormControlLabel control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />} label="Grupo ativo" />
      </Stack>
    </CrudFormShell>
  )
}
