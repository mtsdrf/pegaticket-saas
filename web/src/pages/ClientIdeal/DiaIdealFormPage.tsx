import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as idealService from '../../services/idealService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const FIELDS: CrudFieldDef[] = [
  { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
  { name: 'is_active', label: 'Ativo', type: 'switch', half: true },
]

const DEFAULT_VALUES: CrudFormValues = { name: '', is_active: true }

export function DiaIdealFormPage() {
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

    idealService
      .getDiaIdeal(uuid)
      .then((item) => setValues({ name: item.name, is_active: item.is_active }))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o dia ideal agora.')))
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
        await idealService.updateDiaIdeal(uuid, payload)
      } else {
        await idealService.createDiaIdeal(payload)
      }
      navigate('/clientes/dias-ideais')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o dia ideal agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Dias ideais"
      backTo="/clientes/dias-ideais"
      title={isEditMode ? 'Editar dia ideal' : 'Novo dia ideal'}
      subtitle={isEditMode ? 'Atualize o cadastro.' : 'Cadastre um novo dia ideal.'}
      breadcrumbs={[
        { label: 'Clientes', to: '/clientes' },
        { label: 'Dias ideais', to: '/clientes/dias-ideais' },
        { label: isEditMode ? 'Editar' : 'Novo' },
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
