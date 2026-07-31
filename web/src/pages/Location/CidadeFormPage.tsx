import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as cidadeService from '../../services/cidadeService'
import * as locationService from '../../services/locationService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const DEFAULT_VALUES: CrudFormValues = { name: '', estado_uuid: '', is_active: true }

export function CidadeFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [values, setValues] = useState<CrudFormValues>(DEFAULT_VALUES)
  const [estadoOptions, setEstadoOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingRecord, setIsLoadingRecord] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingRecord(true)
    setLoadError(null)

    const estadosPromise = locationService.getEstados()
    const recordPromise = uuid ? cidadeService.getCidade(uuid) : Promise.resolve(null)

    Promise.all([estadosPromise, recordPromise])
      .then(([estados, record]) => {
        setEstadoOptions(estados.map((estado) => ({ value: estado.uuid, label: `${estado.name} (${estado.uf})` })))
        if (record) {
          setValues({ name: record.name, estado_uuid: record.estado_uuid, is_active: record.is_active })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  const fields: CrudFieldDef[] = [
    { name: 'estado_uuid', label: 'Estado', type: 'select', required: true, options: estadoOptions },
    { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
    { name: 'is_active', label: 'Ativo', type: 'switch' },
  ]

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: String(values.name).trim(),
      estado_uuid: String(values.estado_uuid),
      is_active: Boolean(values.is_active),
    }

    try {
      if (uuid) {
        await cidadeService.updateCidade(uuid, payload)
      } else {
        await cidadeService.createCidade(payload)
      }
      navigate('/cidades')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a cidade agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Cidades"
      backTo="/cidades"
      title={isEditMode ? 'Editar cidade' : 'Nova cidade'}
      subtitle={isEditMode ? 'Atualize os dados da cidade.' : 'Cadastre uma nova cidade dentro de um estado.'}
      breadcrumbs={[
        { label: 'Endereço', to: '/cidades' },
        { label: 'Cidades', to: '/cidades' },
        { label: isEditMode ? 'Editar' : 'Nova' },
      ]}
      fields={fields}
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
