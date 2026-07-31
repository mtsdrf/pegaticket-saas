import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as productCategoryService from '../../services/productCategoryService'
import * as productTypeService from '../../services/productTypeService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const DEFAULT_VALUES: CrudFormValues = { name: '', priority: '', is_active: true, product_category_uuid: '' }

export function ProductTypeFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [values, setValues] = useState<CrudFormValues>(DEFAULT_VALUES)
  const [categoryOptions, setCategoryOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingRecord, setIsLoadingRecord] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingRecord(true)
    setLoadError(null)

    const categoriesPromise = productCategoryService.listProductCategories({ per_page: 100 })
    const recordPromise = uuid ? productTypeService.getProductType(uuid) : Promise.resolve(null)

    Promise.all([categoriesPromise, recordPromise])
      .then(([categories, record]) => {
        setCategoryOptions(categories.items.map((category) => ({ value: category.uuid, label: category.name })))
        if (record) {
          setValues({
            name: record.name,
            priority: record.priority ?? '',
            is_active: record.is_active,
            product_category_uuid: record.product_category_uuid,
          })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  const fields: CrudFieldDef[] = [
    {
      name: 'product_category_uuid',
      label: 'Categoria',
      type: 'select',
      required: true,
      options: categoryOptions,
    },
    { name: 'name', label: 'Nome', type: 'text', required: true, maxLength: 255 },
    { name: 'priority', label: 'Prioridade', type: 'number', half: true },
    { name: 'is_active', label: 'Ativo', type: 'switch', half: true },
  ]

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: String(values.name).trim(),
      priority: values.priority === '' ? null : Number(values.priority),
      is_active: Boolean(values.is_active),
      product_category_uuid: String(values.product_category_uuid),
    }

    try {
      if (uuid) {
        await productTypeService.updateProductType(uuid, payload)
      } else {
        await productTypeService.createProductType(payload)
      }
      navigate('/produtos/tipos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o tipo agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Tipos de produto"
      backTo="/produtos/tipos"
      title={isEditMode ? 'Editar tipo de produto' : 'Novo tipo de produto'}
      subtitle={isEditMode ? 'Atualize os dados do tipo.' : 'Cadastre um novo tipo dentro de uma categoria.'}
      breadcrumbs={[
        { label: 'Produtos', to: '/produtos' },
        { label: 'Tipos', to: '/produtos/tipos' },
        { label: isEditMode ? 'Editar' : 'Novo' },
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
