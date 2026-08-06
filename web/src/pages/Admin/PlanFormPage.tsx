import { Box, FormControlLabel, Stack, Switch, TextField } from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { FeatureMatrix } from '../../components/admin/FeatureMatrix'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { FormSection } from '../../components/form/FormSection'
import { sanitizeIntegerInput } from '../../components/form/fieldHelpers'
import * as functionalityService from '../../services/functionalityService'
import * as planService from '../../services/planService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Functionality } from '../../types/admin'

export function PlanFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [functionalities, setFunctionalities] = useState<Functionality[]>([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [description, setDescription] = useState('')
  const [sortOrder, setSortOrder] = useState(0)
  const [isActive, setIsActive] = useState(true)
  const [selected, setSelected] = useState<string[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    setLoadError(null)
    Promise.all([
      functionalityService.listFunctionalities({ per_page: 200 }),
      uuid ? planService.getPlan(uuid) : Promise.resolve(null),
      uuid ? planService.getPlanFunctionalities(uuid) : Promise.resolve([]),
    ])
      .then(([functionalityResult, plan, planFunctionalities]) => {
        setFunctionalities(functionalityResult.items)
        if (plan) {
          setName(plan.name)
          setSlug(plan.slug)
          setDescription(plan.description ?? '')
          setSortOrder(plan.sort_order)
          setIsActive(plan.is_active)
        }
        setSelected(planFunctionalities.map((item) => item.functionality))
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do plano agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  const sortedFunctionalities = useMemo(
    () => [...functionalities].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')),
    [functionalities],
  )

  function handleToggle(functionalitySlug: string, checked: boolean) {
    setSelected((current) =>
      checked ? [...new Set([...current, functionalitySlug])] : current.filter((item) => item !== functionalitySlug),
    )
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)
    try {
      const payload = {
        name: name.trim(),
        slug: slug.trim(),
        description: description.trim() || null,
        sort_order: Number.isFinite(sortOrder) ? sortOrder : 0,
        is_active: isActive,
      }
      const plan = uuid ? await planService.updatePlan(uuid, payload) : await planService.createPlan(payload)
      await planService.syncPlanFunctionalities(plan.uuid, selected)
      navigate('/admin/planos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o plano agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Planos"
      backTo="/admin/planos"
      title={isEditMode ? 'Editar plano' : 'Novo plano'}
      subtitle={isEditMode ? 'Atualize os dados e o pacote de funcionalidades do plano.' : 'Cadastre um novo plano com as funcionalidades liberadas.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/planos' }, { label: 'Planos', to: '/admin/planos' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2}>
        <FormSection
          title="Dados principais"
          description="Defina a identificação do plano, sua ordem de exibição e se ele está disponível para uso."
        >
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
              label="Abreviatura"
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              error={Boolean(fieldErrors.slug)}
              helperText={fieldErrors.slug?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 100 } }}
            />
            <TextField
              label="Descrição"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              error={Boolean(fieldErrors.description)}
              helperText={fieldErrors.description?.[0]}
              fullWidth
              sx={{ gridColumn: '1 / -1' }}
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <TextField
              label="Ordem"
              type="number"
              value={sortOrder}
              onChange={(e) => setSortOrder(Number(sanitizeIntegerInput(e.target.value) || '0'))}
              error={Boolean(fieldErrors.sort_order)}
              helperText={fieldErrors.sort_order?.[0]}
              fullWidth
              slotProps={{ htmlInput: { min: 0, step: 1 } }}
            />
            <FormControlLabel
              control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />}
              label="Plano ativo"
              sx={{ minHeight: 56, alignItems: 'center' }}
            />
          </Box>
        </FormSection>

        <FormSection
          title="Pacote de funcionalidades"
          description="Escolha quais recursos ficam liberados para quem contratar este plano."
        >
          <FeatureMatrix
            title="Funcionalidades liberadas"
            functionalities={sortedFunctionalities}
            selected={selected}
            onToggle={handleToggle}
          />
        </FormSection>
      </Stack>
    </CrudFormShell>
  )
}
