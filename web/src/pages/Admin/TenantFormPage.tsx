import { Box, Divider, FormControlLabel, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { FormSection } from '../../components/form/FormSection'
import { ImageUploadField } from '../../components/shared/ImageUploadField'
import { TenantFeatureOverrideMatrix, type FeatureOverrideState } from '../../components/admin/TenantFeatureOverrideMatrix'
import * as functionalityService from '../../services/functionalityService'
import * as planService from '../../services/planService'
import * as tenantAdminService from '../../services/tenantAdminService'
import * as tenantFeatureOverrideService from '../../services/tenantFeatureOverrideService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import type { Functionality, Plan } from '../../types/admin'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

interface TenantFormState {
  name: string
  slug: string
  plan_uuid: string
  is_active: boolean
}

const EMPTY_FORM: TenantFormState = { name: '', slug: '', plan_uuid: '', is_active: true }

export function TenantFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [plans, setPlans] = useState<Plan[]>([])
  const [form, setForm] = useState<TenantFormState>(EMPTY_FORM)
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [existingLogoUrl, setExistingLogoUrl] = useState<string | null>(null)
  const [isLoadingRecord, setIsLoadingRecord] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Feature flag por tenant individual (roadmap A5, item 19) — só faz
  // sentido em modo edição (precisa de um tenant existente).
  const [functionalities, setFunctionalities] = useState<Functionality[]>([])
  const [featureOverrides, setFeatureOverrides] = useState<Record<string, boolean>>({})

  useEffect(() => {
    setIsLoadingRecord(true)
    setLoadError(null)
    Promise.all([
      planService.listPlans({ per_page: 200 }),
      uuid ? tenantAdminService.getTenant(uuid) : Promise.resolve(null),
      uuid ? functionalityService.listFunctionalities({ per_page: 200 }) : Promise.resolve(null),
      uuid ? tenantFeatureOverrideService.getTenantFeatureOverrides(uuid) : Promise.resolve([]),
    ])
      .then(([planResult, item, functionalityResult, overrides]) => {
        setPlans(planResult.items.filter((plan) => plan.is_active || plan.uuid === item?.plan_uuid))
        if (item) {
          setForm({ name: item.name, slug: item.slug, plan_uuid: item.plan_uuid ?? '', is_active: item.is_active })
          setExistingLogoUrl(item.logo_url)
        } else {
          const firstPlanUuid = planResult.items.find((plan) => plan.is_active)?.uuid ?? ''
          setForm({ ...EMPTY_FORM, plan_uuid: firstPlanUuid })
        }
        if (functionalityResult) {
          setFunctionalities(
            [...functionalityResult.items].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')),
          )
        }
        setFeatureOverrides(
          Object.fromEntries(overrides.map((override) => [override.functionality, override.is_enabled])),
        )
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a empresa agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  function handleFeatureOverrideChange(functionalitySlug: string, value: FeatureOverrideState) {
    setFeatureOverrides((current) => {
      if (value === null) {
        const { [functionalitySlug]: _removed, ...rest } = current
        return rest
      }
      return { ...current, [functionalitySlug]: value }
    })
  }

  function updateField<K extends keyof TenantFormState>(key: K, value: TenantFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setIsSubmitting(true)
    setFormError(null)
    setFieldErrors({})
    try {
      if (uuid) {
        await tenantAdminService.updateTenant(
          uuid,
          { name: form.name.trim(), plan_uuid: form.plan_uuid, is_active: form.is_active },
          logoFile,
        )
        await tenantFeatureOverrideService.syncTenantFeatureOverrides(
          uuid,
          Object.entries(featureOverrides).map(([functionality, is_enabled]) => ({ functionality, is_enabled })),
        )
      } else {
        await tenantAdminService.createTenant(
          { name: form.name.trim(), slug: form.slug.trim(), plan_uuid: form.plan_uuid, is_active: form.is_active },
          logoFile,
        )
      }
      navigate('/admin/tenants')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a empresa agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Empresas"
      backTo="/admin/tenants"
      title={isEditMode ? 'Editar empresa' : 'Nova empresa'}
      subtitle={isEditMode ? 'Atualize os dados e o plano da empresa.' : 'Cadastre uma nova empresa da plataforma.'}
      breadcrumbs={[{ label: 'Administração', to: '/admin/tenants' }, { label: 'Empresas', to: '/admin/tenants' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      loadError={loadError}
      isLoadingRecord={isLoadingRecord}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Dados principais" description="Defina a identidade da empresa, o plano contratado e o status de operação.">
        <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: isEditMode ? 'minmax(0, 1fr)' : 'repeat(2, minmax(0, 1fr))' } }}>
          <TextField
            label="Nome"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0]}
            required
            fullWidth
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
          {!isEditMode && (
            <TextField
              label="Abreviatura"
              value={form.slug}
              onChange={(event) => updateField('slug', event.target.value)}
              error={Boolean(fieldErrors.slug)}
              helperText={fieldErrors.slug?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
          )}
        </Box>

        <LocalAutocomplete
          label="Plano"
          options={plans}
          value={plans.find((plan) => plan.uuid === form.plan_uuid) ?? null}
          onChange={(plan) => updateField('plan_uuid', plan?.uuid ?? '')}
          getOptionLabel={(plan) => plan.name}
          getOptionKey={(plan) => plan.uuid}
          required
          fullWidth
          error={Boolean(fieldErrors.plan_uuid)}
          helperText={fieldErrors.plan_uuid?.[0]}
        />

        <FormControlLabel
          control={<Switch checked={form.is_active} onChange={(event) => updateField('is_active', event.target.checked)} />}
          label="Ativo"
        />
      </FormSection>

      <FormSection title="Identidade visual" description="Opcionalmente envie a logo da empresa para uso nas interfaces da plataforma.">
        <ImageUploadField label="Logo da empresa" existingImageUrl={existingLogoUrl} onFileSelected={setLogoFile} />
      </FormSection>

      {isEditMode && (
        <>
          <Divider sx={{ my: 3 }} />
          <TenantFeatureOverrideMatrix
            title="Funcionalidades"
            functionalities={functionalities}
            overrides={featureOverrides}
            onChange={handleFeatureOverrideChange}
          />
        </>
      )}
    </CrudFormShell>
  )
}
