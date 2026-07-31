import { FormControlLabel, Stack, Switch } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ClientAddressFields } from '../../components/client/ClientAddressFields'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { useLocationCascade } from '../../hooks/useLocationCascade'
import * as enderecoService from '../../services/enderecoService'
import * as locationService from '../../services/locationService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Estado } from '../../types/location'

interface EnderecoFormState {
  logradouro: string
  numero: string
  complemento: string
  cep: string
  is_active: boolean
  estado_uuid: string
  cidade_uuid: string
  bairro_uuid: string
}

const EMPTY_FORM: EnderecoFormState = {
  logradouro: '',
  numero: '',
  complemento: '',
  cep: '',
  is_active: true,
  estado_uuid: '',
  cidade_uuid: '',
  bairro_uuid: '',
}

export function EnderecoFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [form, setForm] = useState<EnderecoFormState>(EMPTY_FORM)
  const [estados, setEstados] = useState<Estado[]>([])
  const cascade = useLocationCascade()

  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function init() {
      setIsLoadingForm(true)
      setLoadError(null)

      try {
        const estadosList = await locationService.getEstados()
        if (cancelled) return
        setEstados(estadosList)

        if (uuid) {
          const endereco = await enderecoService.getEndereco(uuid)
          if (cancelled) return

          // Busca cidades/bairros da cadeia ANTES de preencher o form,
          // senão os Selects ficam com um value fora das opções carregadas
          // (mesmo cuidado de ClientFormPage).
          const [cidadesList, bairrosList] = await Promise.all([
            locationService.getCidades(endereco.estado_uuid),
            locationService.getBairros(endereco.cidade_uuid),
          ])
          if (cancelled) return
          cascade.setCidades(cidadesList)
          cascade.setBairros(bairrosList)

          setForm({
            logradouro: endereco.logradouro,
            numero: endereco.numero ?? '',
            complemento: endereco.complemento ?? '',
            cep: endereco.cep ?? '',
            is_active: endereco.is_active,
            estado_uuid: endereco.estado_uuid,
            cidade_uuid: endereco.cidade_uuid,
            bairro_uuid: endereco.bairro_uuid,
          })
        } else {
          setForm(EMPTY_FORM)
        }
      } catch (error) {
        if (!cancelled) setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do formulário agora.'))
      } finally {
        if (!cancelled) setIsLoadingForm(false)
      }
    }

    void init()
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uuid])

  async function handleEstadoChange(newEstadoUuid: string) {
    setForm((current) => ({ ...current, estado_uuid: newEstadoUuid, cidade_uuid: '', bairro_uuid: '' }))
    await cascade.loadCidades(newEstadoUuid)
  }

  async function handleCidadeChange(newCidadeUuid: string) {
    setForm((current) => ({ ...current, cidade_uuid: newCidadeUuid, bairro_uuid: '' }))
    await cascade.loadBairros(newCidadeUuid)
  }

  function handleBairroChange(newBairroUuid: string) {
    setForm((current) => ({ ...current, bairro_uuid: newBairroUuid }))
  }

  function handleAddressTextChange(field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  function validateClientSide(): Record<string, string[]> {
    const errors: Record<string, string[]> = {}
    if (!form.logradouro.trim()) errors.logradouro = ['Campo obrigatório.']
    if (!form.estado_uuid) errors.estado_uuid = ['Campo obrigatório.']
    if (!form.cidade_uuid) errors.cidade_uuid = ['Campo obrigatório.']
    if (!form.bairro_uuid) errors.bairro_uuid = ['Campo obrigatório.']
    return errors
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    const clientErrors = validateClientSide()
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      logradouro: form.logradouro.trim(),
      numero: form.numero.trim() || undefined,
      complemento: form.complemento.trim() || undefined,
      cep: form.cep.trim() || undefined,
      is_active: form.is_active,
      estado_uuid: form.estado_uuid,
      cidade_uuid: form.cidade_uuid,
      bairro_uuid: form.bairro_uuid,
    }

    try {
      if (isEditMode && uuid) {
        await enderecoService.updateEndereco(uuid, payload)
      } else {
        await enderecoService.createEndereco(payload)
      }
      navigate('/enderecos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o endereço agora. Tente novamente.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Endereços"
      backTo="/enderecos"
      title={isEditMode ? 'Editar endereço' : 'Novo endereço'}
      subtitle={isEditMode ? 'Atualize os dados do endereço.' : 'Cadastre um novo endereço para a empresa.'}
      breadcrumbs={[
        { label: 'Endereço', to: '/enderecos' },
        { label: 'Endereços', to: '/enderecos' },
        { label: isEditMode ? 'Editar' : 'Novo' },
      ]}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <ClientAddressFields
        values={{
          estado_uuid: form.estado_uuid,
          cidade_uuid: form.cidade_uuid,
          bairro_uuid: form.bairro_uuid,
          logradouro: form.logradouro,
          numero: form.numero,
          complemento: form.complemento,
          cep: form.cep,
        }}
        estados={estados}
        cidades={cascade.cidades}
        bairros={cascade.bairros}
        isLoadingCidades={cascade.isLoadingCidades}
        isLoadingBairros={cascade.isLoadingBairros}
        fieldErrors={fieldErrors}
        onEstadoChange={(value) => void handleEstadoChange(value)}
        onCidadeChange={(value) => void handleCidadeChange(value)}
        onBairroChange={handleBairroChange}
        onTextChange={handleAddressTextChange}
      />

      <Stack direction="row" sx={{ mt: 1 }}>
        <FormControlLabel
          control={
            <Switch
              checked={form.is_active}
              onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
            />
          }
          label="Ativo"
        />
      </Stack>
    </CrudFormShell>
  )
}
