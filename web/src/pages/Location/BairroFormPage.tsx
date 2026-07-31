import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { SchemaFormPage } from '../../components/crud/SchemaFormPage'
import type { CrudFieldDef, CrudFormValues } from '../../components/crud/schemaFormTypes'
import * as bairroService from '../../services/bairroService'
import * as cidadeService from '../../services/cidadeService'
import * as locationService from '../../services/locationService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

const DEFAULT_VALUES: CrudFormValues = { name: '', estado_uuid: '', cidade_uuid: '', is_active: true }

/**
 * `estado_uuid` aqui é só UX (filtra as opções de `cidade_uuid`) — Bairro
 * não guarda estado, só `cidade_uuid`. Nunca entra no payload de submit.
 */
export function BairroFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [values, setValues] = useState<CrudFormValues>(DEFAULT_VALUES)
  const [estadoOptions, setEstadoOptions] = useState<{ value: string; label: string }[]>([])
  const [cidadeOptions, setCidadeOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingRecord, setIsLoadingRecord] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingRecord(true)
    setLoadError(null)

    async function init() {
      const estados = await locationService.getEstados()
      setEstadoOptions(estados.map((estado) => ({ value: estado.uuid, label: `${estado.name} (${estado.uf})` })))

      if (!uuid) return

      const record = await bairroService.getBairro(uuid)
      // Resource de Bairro só traz cidade_uuid/cidade_name — resolve o
      // estado da cidade só pra pré-selecionar o filtro em modo edição.
      const cidade = await cidadeService.getCidade(record.cidade_uuid)
      const cidadesDoEstado = await locationService.getCidades(cidade.estado_uuid)

      setCidadeOptions(cidadesDoEstado.map((item) => ({ value: item.uuid, label: item.name })))
      setValues({
        name: record.name,
        estado_uuid: cidade.estado_uuid,
        cidade_uuid: record.cidade_uuid,
        is_active: record.is_active,
      })
    }

    init()
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  async function handleEstadoFilterChange(estadoUuid: string) {
    setValues((current) => ({ ...current, estado_uuid: estadoUuid, cidade_uuid: '' }))
    setCidadeOptions([])
    if (!estadoUuid) return

    setIsLoadingCidades(true)
    try {
      const cidades = await locationService.getCidades(estadoUuid)
      setCidadeOptions(cidades.map((item) => ({ value: item.uuid, label: item.name })))
    } catch {
      setCidadeOptions([])
    } finally {
      setIsLoadingCidades(false)
    }
  }

  function handleChange(name: string, value: string | number | boolean) {
    if (name === 'estado_uuid') {
      void handleEstadoFilterChange(String(value))
      return
    }
    setValues((current) => ({ ...current, [name]: value }))
  }

  const fields: CrudFieldDef[] = [
    { name: 'estado_uuid', label: 'Estado (filtro pra achar a cidade)', type: 'select', options: estadoOptions },
    {
      name: 'cidade_uuid',
      label: isLoadingCidades ? 'Cidade (carregando…)' : 'Cidade',
      type: 'select',
      required: true,
      options: cidadeOptions,
    },
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
      cidade_uuid: String(values.cidade_uuid),
      is_active: Boolean(values.is_active),
    }

    try {
      if (uuid) {
        await bairroService.updateBairro(uuid, payload)
      } else {
        await bairroService.createBairro(payload)
      }
      navigate('/bairros')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o bairro agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <SchemaFormPage
      backLabel="Bairros"
      backTo="/bairros"
      title={isEditMode ? 'Editar bairro' : 'Novo bairro'}
      subtitle={isEditMode ? 'Atualize os dados do bairro.' : 'Cadastre um novo bairro dentro de uma cidade.'}
      breadcrumbs={[
        { label: 'Endereço', to: '/bairros' },
        { label: 'Bairros', to: '/bairros' },
        { label: isEditMode ? 'Editar' : 'Novo' },
      ]}
      fields={fields}
      values={values}
      onChange={handleChange}
      fieldErrors={fieldErrors}
      formError={formError}
      loadError={loadError}
      isLoadingRecord={isLoadingRecord}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    />
  )
}
