import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as clientCategoryService from '../../services/clientCategoryService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const FIELDS: CrudFieldDef[] = [
  { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
  { name: 'is_active', label: 'Ativa', type: 'switch', half: true },
]

const DEFAULT_VALUES: CrudFormValues = { name: '', is_active: true }

export function ClientCategoryFormPage() {
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
    setLoadError(null)

    clientCategoryService
      .getClientCategory(uuid)
      .then((category) => setValues({ name: category.name, is_active: category.is_active }))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a categoria agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: String(values.name).trim(),
      is_active: Boolean(values.is_active),
    }

    try {
      if (uuid) {
        await clientCategoryService.updateClientCategory(uuid, payload)
      } else {
        await clientCategoryService.createClientCategory(payload)
      }
      navigate('/clientes/categorias')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a categoria agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Categorias"
      backTo="/clientes/categorias"
      title={isEditMode ? 'Editar categoria de cliente' : 'Nova categoria de cliente'}
      subtitle={isEditMode ? 'Atualize os dados da categoria.' : 'Cadastre uma nova categoria de cliente.'}
      breadcrumbs={[
        { label: 'Clientes', to: '/clientes' },
        { label: 'Categorias', to: '/clientes/categorias' },
        { label: isEditMode ? 'Editar' : 'Nova' },
      ]}
      fields={FIELDS}
      values={values}
      onChange={(name, value) => setValues((current) => ({ ...current, [name]: value }))}
      fieldErrors={fieldErrors}
      formError={formError}
      loadError={loadError}
      isLoadingRecord={isLoadingRecord}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    />
  )
}
