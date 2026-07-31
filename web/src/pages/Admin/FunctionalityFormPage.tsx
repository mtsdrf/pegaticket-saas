import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as functionalityService from '../../services/functionalityService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const FIELDS: CrudFieldDef[] = [
  { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
  { name: 'slug', label: 'Abreviatura', type: 'text', required: true, maxLength: 100 },
  { name: 'description', label: 'Descrição', type: 'text', maxLength: 255 },
  { name: 'is_active', label: 'Ativa', type: 'switch', half: true },
]
const DEFAULT_VALUES: CrudFormValues = { name: '', slug: '', description: '', is_active: true }

export function FunctionalityFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [values, setValues] = useState<CrudFormValues>(DEFAULT_VALUES)
  const [isLoadingRecord, setIsLoadingRecord] = useState(isEditMode)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  useEffect(() => {
    if (!uuid) return
    setIsLoadingRecord(true)
    functionalityService.getFunctionality(uuid).then((item) => setValues({ name: item.name, slug: item.slug, description: item.description ?? '', is_active: item.is_active })).catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a funcionalidade agora.'))).finally(() => setIsLoadingRecord(false))
  }, [uuid])
  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setIsSubmitting(true)
    setFormError(null)
    setFieldErrors({})
    const payload = { name: String(values.name).trim(), slug: String(values.slug).trim(), description: String(values.description).trim() || null, is_active: Boolean(values.is_active) }
    try {
      if (uuid) await functionalityService.updateFunctionality(uuid, payload)
      else await functionalityService.createFunctionality(payload)
      navigate('/admin/funcionalidades')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a funcionalidade agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }
  return <SchemaFormPage backLabel="Funcionalidades" backTo="/admin/funcionalidades" title={isEditMode ? 'Editar funcionalidade' : 'Nova funcionalidade'} subtitle={isEditMode ? 'Atualize os dados da funcionalidade.' : 'Cadastre uma nova funcionalidade de permissão.'} breadcrumbs={[{ label: 'Administração', to: '/admin/funcionalidades' }, { label: 'Funcionalidades', to: '/admin/funcionalidades' }, { label: isEditMode ? 'Editar' : 'Nova' }]} fields={FIELDS} values={values} onChange={(name, value) => setValues((current) => ({ ...current, [name]: value }))} fieldErrors={fieldErrors} formError={formError} loadError={loadError} isLoadingRecord={isLoadingRecord} isSubmitting={isSubmitting} onSubmit={(event) => void handleSubmit(event)} />
}
