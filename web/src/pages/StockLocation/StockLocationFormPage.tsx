import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as stockLocationService from '../../services/stockLocationService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const FIELDS: CrudFieldDef[] = [
  { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
  { name: 'type', label: 'Tipo', type: 'text', half: true, maxLength: 255 },
  { name: 'is_default', label: 'Local padrão', type: 'switch', half: true },
  { name: 'address', label: 'Endereço', type: 'text', maxLength: 255 },
  { name: 'is_active', label: 'Ativo', type: 'switch', half: true },
]

const DEFAULT_VALUES: CrudFormValues = {
  name: '',
  type: '',
  address: '',
  is_default: false,
  is_active: true,
}

export function StockLocationFormPage() {
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

    stockLocationService
      .getStockLocation(uuid)
      .then((location) =>
        setValues({
          name: location.name,
          type: location.type ?? '',
          address: location.address ?? '',
          is_default: location.is_default,
          is_active: location.is_active,
        }),
      )
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o local agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: String(values.name).trim(),
      type: String(values.type).trim() || null,
      address: String(values.address).trim() || null,
      is_default: Boolean(values.is_default),
      is_active: Boolean(values.is_active),
    }

    try {
      if (uuid) {
        await stockLocationService.updateStockLocation(uuid, payload)
      } else {
        await stockLocationService.createStockLocation(payload)
      }
      navigate('/estoque/locais')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o local agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Locais de estoque"
      backTo="/estoque/locais"
      title={isEditMode ? 'Editar local de estoque' : 'Novo local de estoque'}
      subtitle={isEditMode ? 'Atualize os dados do local.' : 'Cadastre um novo local para controle de estoque.'}
      breadcrumbs={[
        { label: 'Estoque', to: '/estoque/locais' },
        { label: 'Locais', to: '/estoque/locais' },
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
