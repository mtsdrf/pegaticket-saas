import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as estadoService from '../../services/estadoService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const FIELDS: CrudFieldDef[] = [
  { name: 'name', label: 'Nome', type: 'text', required: true, half: true, maxLength: 255 },
  { name: 'uf', label: 'UF', type: 'text', required: true, half: true, maxLength: 2 },
  { name: 'is_active', label: 'Ativo', type: 'switch' },
]

const DEFAULT_VALUES: CrudFormValues = { name: '', uf: '', is_active: true }

export function EstadoFormPage() {
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

    estadoService
      .getEstado(uuid)
      .then((estado) => {
        setValues({ name: estado.name, uf: estado.uf, is_active: estado.is_active })
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o estado agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: String(values.name).trim(),
      uf: String(values.uf).trim().toUpperCase(),
      is_active: Boolean(values.is_active),
    }

    try {
      if (uuid) {
        await estadoService.updateEstado(uuid, payload)
      } else {
        await estadoService.createEstado(payload)
      }
      navigate('/estados')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o estado agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Estados"
      backTo="/estados"
      title={isEditMode ? 'Editar estado' : 'Novo estado'}
      subtitle={isEditMode ? 'Atualize os dados do estado.' : 'Cadastre um novo estado para uso nos endereços.'}
      breadcrumbs={[
        { label: 'Endereço', to: '/estados' },
        { label: 'Estados', to: '/estados' },
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
