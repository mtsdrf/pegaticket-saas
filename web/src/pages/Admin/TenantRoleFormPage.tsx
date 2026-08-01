import { Box, FormControlLabel, InputAdornment, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { PermissionMatrix } from '../../components/admin/PermissionMatrix'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import * as tenantRoleService from '../../services/tenantRoleService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Functionality, TenantRolePermission } from '../../types/admin'
import { ACTION_LABELS_PT, getTenantRoleActionOptions } from '../../types/admin'

export function TenantRoleFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [functionalities, setFunctionalities] = useState<Functionality[]>([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [selected, setSelected] = useState<Record<string, string[]>>({})
  // Limite percentual de desconto do perfil (roadmap A1.5) — string no estado
  // pra permitir input vazio sem virar `0`/`NaN` durante a digitação; vazio =
  // sem limite. Só faz sentido pra functionality 'sales' (aplicado a todas
  // as linhas dela no payload de sync, ver handleSubmit).
  const [discountLimitPercent, setDiscountLimitPercent] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    setLoadError(null)
    Promise.all([
      tenantRoleService.listAvailableFunctionalities(),
      uuid ? tenantRoleService.getTenantRole(uuid) : Promise.resolve(null),
      uuid ? tenantRoleService.getTenantRolePermissions(uuid) : Promise.resolve([] as TenantRolePermission[]),
    ])
      .then(([functionalityItems, role, permissions]) => {
        setFunctionalities(functionalityItems)
        if (role) {
          setName(role.name)
          setSlug(role.slug)
          setIsActive(role.is_active)
        }
        const next: Record<string, string[]> = {}
        let ordersDiscountLimit: number | null = null
        permissions.forEach((permission) => {
          next[permission.functionality] = [...(next[permission.functionality] ?? []), permission.action]
          if (
            permission.functionality === 'sales' &&
            permission.discount_limit_percent !== null &&
            permission.discount_limit_percent !== undefined
          ) {
            ordersDiscountLimit =
              ordersDiscountLimit === null
                ? permission.discount_limit_percent
                : Math.min(ordersDiscountLimit, permission.discount_limit_percent)
          }
        })
        setSelected(next)
        setDiscountLimitPercent(ordersDiscountLimit !== null ? String(ordersDiscountLimit) : '')
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do perfil agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  const permissionPayload = useMemo<TenantRolePermission[]>(() => {
    const trimmedLimit = discountLimitPercent.trim()
    const limitValue = trimmedLimit ? Number(trimmedLimit) : null

    return Object.entries(selected).flatMap(([functionality, actions]) =>
      actions.map((action) => ({
        functionality,
        action,
        ...(functionality === 'sales' ? { discount_limit_percent: limitValue } : {}),
      })),
    )
  }, [selected, discountLimitPercent])

  function handleToggle(functionalitySlug: string, action: string, checked: boolean) {
    setSelected((current) => {
      const actions = new Set(current[functionalitySlug] ?? [])
      if (checked) actions.add(action)
      else actions.delete(action)
      return { ...current, [functionalitySlug]: Array.from(actions) }
    })
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const payload = { name: name.trim(), slug: slug.trim(), is_active: isActive }
      const role = uuid ? await tenantRoleService.updateTenantRole(uuid, payload) : await tenantRoleService.createTenantRole(payload)
      await tenantRoleService.syncTenantRolePermissions(role.uuid, permissionPayload)
      navigate('/admin/tenant-roles')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o perfil agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Perfis da empresa"
      backTo="/admin/tenant-roles"
      title={isEditMode ? 'Editar perfil da empresa' : 'Novo perfil da empresa'}
      subtitle={isEditMode ? 'Atualize dados e permissões do perfil.' : 'Cadastre um novo perfil com suas permissões.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/tenant-roles' }, { label: 'Perfis da empresa', to: '/admin/tenant-roles' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
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
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
        </Box>
        <FormControlLabel control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />} label="Perfil ativo" />

        <Box>
          <TextField
            label="Limite de desconto em pedidos"
            type="number"
            value={discountLimitPercent}
            onChange={(e) => setDiscountLimitPercent(e.target.value)}
            helperText="Desconto manual máximo (% abaixo do preço de tabela) que este perfil pode aplicar num item de pedido/venda. Deixe em branco para não limitar."
            sx={{ maxWidth: { sm: 360 } }}
            fullWidth
            slotProps={{
              input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
              htmlInput: { min: 0, max: 100, step: '0.01' },
            }}
          />
          {!selected.orders?.length && discountLimitPercent.trim() && (
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-warning)', mt: 0.5 }}>
              Este perfil não tem nenhuma permissão de "Pedidos" marcada abaixo — o limite só tem efeito se o
              perfil puder criar/editar pedidos.
            </Typography>
          )}
        </Box>

        <PermissionMatrix
          title="Permissões do perfil"
          functionalities={functionalities}
          getActionsForFunctionality={getTenantRoleActionOptions}
          selected={selected}
          onToggle={handleToggle}
          actionLabels={ACTION_LABELS_PT}
        />
      </Stack>
    </CrudFormShell>
  )
}
