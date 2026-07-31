import { Box, FormControlLabel, MenuItem, Stack, Switch, TextField } from '@mui/material'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ClientAddressFields } from '../../components/client/ClientAddressFields'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import * as clientService from '../../services/clientService'
import * as idealService from '../../services/idealService'
import * as locationService from '../../services/locationService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { ClientPayload, DiaIdeal, PeriodoIdeal } from '../../types/client'
import type { Bairro, Cidade, Estado } from '../../types/location'
import { formatCpfCnpj, isValidCpfCnpj, normalizeCpfCnpj } from '../../utils/cpfCnpj'

/** Distingue `GeolocationPositionError` (não estende `Error`) de erros de rede/API na chamada de reverse geocode. */
function isGeolocationPositionError(error: unknown): error is GeolocationPositionError {
  return typeof error === 'object' && error !== null && 'code' in error && 'PERMISSION_DENIED' in error
}

function geolocationErrorMessage(error: unknown): string {
  if (isGeolocationPositionError(error)) {
    if (error.code === error.PERMISSION_DENIED) {
      return 'Permissão de localização negada. Habilite o acesso à localização do navegador para usar este recurso.'
    }
    if (error.code === error.TIMEOUT) {
      return 'Não foi possível obter sua localização a tempo. Tente novamente.'
    }
    return 'Não foi possível obter sua localização agora (verifique se o site está em HTTPS). Preencha o endereço manualmente.'
  }
  return getApiErrorMessage(error, 'Não foi possível obter sua localização agora. Preencha o endereço manualmente.')
}

interface ClientFormState {
  name: string
  phone_primary: string
  phone_secondary: string
  cpf_cnpj: string
  ie: string
  ie_indicator: 'contribuinte' | 'isento' | 'nao_contribuinte'
  notes: string
  is_trusted: boolean
  is_active: boolean
  dia_ideal_uuid: string
  periodo_ideal_uuid: string
  estado_uuid: string
  cidade_uuid: string
  bairro_uuid: string
  logradouro: string
  numero: string
  complemento: string
  cep: string
}

const EMPTY_FORM: ClientFormState = {
  name: '',
  phone_primary: '',
  phone_secondary: '',
  cpf_cnpj: '',
  ie: '',
  ie_indicator: 'nao_contribuinte',
  notes: '',
  is_trusted: true,
  is_active: true,
  dia_ideal_uuid: '',
  periodo_ideal_uuid: '',
  estado_uuid: '',
  cidade_uuid: '',
  bairro_uuid: '',
  logradouro: '',
  numero: '',
  complemento: '',
  cep: '',
}

const EMPTY_COORDS = { lat: null as number | null, lng: null as number | null }

export function ClientFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [form, setForm] = useState<ClientFormState>(EMPTY_FORM)
  const [coords, setCoords] = useState<{ lat: number | null; lng: number | null }>(EMPTY_COORDS)
  const [estados, setEstados] = useState<Estado[]>([])
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [diaIdeais, setDiaIdeais] = useState<DiaIdeal[]>([])
  const [periodoIdeais, setPeriodoIdeais] = useState<PeriodoIdeal[]>([])

  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)
  const [isLocatingGps, setIsLocatingGps] = useState(false)

  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function init() {
      setIsLoadingForm(true)
      setLoadError(null)

      try {
        const [estadosList, diaList, periodoList] = await Promise.all([
          locationService.getEstados(),
          idealService.getDiaIdeais(),
          idealService.getPeriodoIdeais(),
        ])
        if (cancelled) return
        setEstados(estadosList)
        setDiaIdeais(diaList)
        setPeriodoIdeais(periodoList)

        if (uuid) {
          const client = await clientService.getClient(uuid)
          if (cancelled) return

          // Busca a cadeia de cidades/bairros ANTES de preencher o form,
          // senão os Selects ficam com um value fora das opções carregadas.
          const [cidadesList, bairrosList] = await Promise.all([
            locationService.getCidades(client.endereco.estado_uuid),
            locationService.getBairros(client.endereco.cidade_uuid),
          ])
          if (cancelled) return
          setCidades(cidadesList)
          setBairros(bairrosList)

          setForm({
            name: client.name,
            phone_primary: client.phone_primary ?? '',
            phone_secondary: client.phone_secondary ?? '',
            cpf_cnpj: client.cpf_cnpj ? formatCpfCnpj(client.cpf_cnpj) : '',
            ie: client.ie ?? '',
            ie_indicator: client.ie_indicator ?? 'nao_contribuinte',
            notes: client.notes ?? '',
            is_trusted: client.is_trusted,
            is_active: client.is_active,
            dia_ideal_uuid: client.dia_ideal?.uuid ?? '',
            periodo_ideal_uuid: client.periodo_ideal?.uuid ?? '',
            estado_uuid: client.endereco.estado_uuid,
            cidade_uuid: client.endereco.cidade_uuid,
            bairro_uuid: client.endereco.bairro_uuid,
            logradouro: client.endereco.logradouro,
            numero: client.endereco.numero ?? '',
            complemento: client.endereco.complemento ?? '',
            cep: client.endereco.cep ?? '',
          })
          setCoords({ lat: client.endereco.lat, lng: client.endereco.lng })
        } else {
          setForm(EMPTY_FORM)
          setCoords(EMPTY_COORDS)
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
  }, [uuid])

  /** Retorna a lista de cidades carregadas — usado por `handleUseCurrentLocation` para checar se o `cidade_uuid` sugerido está entre as opções, sem depender do estado `cidades` (que só reflete após o próximo render). */
  async function handleEstadoChange(newEstadoUuid: string): Promise<Cidade[]> {
    setForm((current) => ({ ...current, estado_uuid: newEstadoUuid, cidade_uuid: '', bairro_uuid: '' }))
    setBairros([])
    setCidades([])
    if (!newEstadoUuid) return []

    setIsLoadingCidades(true)
    try {
      const list = await locationService.getCidades(newEstadoUuid)
      setCidades(list)
      return list
    } catch {
      setCidades([])
      return []
    } finally {
      setIsLoadingCidades(false)
    }
  }

  /** Idem `handleEstadoChange`, retornando a lista de bairros carregados. */
  async function handleCidadeChange(newCidadeUuid: string): Promise<Bairro[]> {
    setForm((current) => ({ ...current, cidade_uuid: newCidadeUuid, bairro_uuid: '' }))
    setBairros([])
    if (!newCidadeUuid) return []

    setIsLoadingBairros(true)
    try {
      const list = await locationService.getBairros(newCidadeUuid)
      setBairros(list)
      return list
    } catch {
      setBairros([])
      return []
    } finally {
      setIsLoadingBairros(false)
    }
  }

  function handleBairroChange(newBairroUuid: string) {
    setForm((current) => ({ ...current, bairro_uuid: newBairroUuid }))
  }

  function handleAddressTextChange(field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  /**
   * Fluxo GPS → reverse geocode → preenchimento em cascata. Nunca trava nem
   * mostra erro só por não achar um campo (`estado_uuid`/`cidade_uuid`/`bairro_uuid`
   * `null` = usuário preenche manualmente); erros de geolocalização (permissão
   * negada, timeout, HTTPS ausente) e de rede são sempre tratados, nunca deixam
   * promise rejeitada solta.
   */
  async function handleUseCurrentLocation() {
    setFormError(null)

    if (!navigator.geolocation) {
      setFormError('Seu navegador não oferece suporte a geolocalização.')
      return
    }

    setIsLocatingGps(true)
    try {
      const position = await new Promise<GeolocationPosition>((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000, enableHighAccuracy: true })
      })
      const { latitude, longitude } = position.coords
      setCoords({ lat: latitude, lng: longitude })

      const result = await locationService.reverseGeocode(latitude, longitude)

      if (result.estado_uuid) {
        const cidadesList = await handleEstadoChange(result.estado_uuid)
        if (result.cidade_uuid && cidadesList.some((cidade) => cidade.uuid === result.cidade_uuid)) {
          const bairrosList = await handleCidadeChange(result.cidade_uuid)
          if (result.bairro_uuid && bairrosList.some((bairro) => bairro.uuid === result.bairro_uuid)) {
            handleBairroChange(result.bairro_uuid)
          }
        }
      }

      setForm((current) => ({
        ...current,
        logradouro: !current.logradouro.trim() && result.logradouro ? result.logradouro : current.logradouro,
        cep: !current.cep.trim() && result.cep ? result.cep : current.cep,
      }))
    } catch (error) {
      setFormError(geolocationErrorMessage(error))
    } finally {
      setIsLocatingGps(false)
    }
  }

  function validateClientSide(): Record<string, string[]> {
    const errors: Record<string, string[]> = {}
    if (form.name.trim().length < 2) errors.name = ['Informe pelo menos 2 caracteres.']

    // CPF/CNPJ só é obrigatório na criação — clientes antigos sem o dado
    // continuam editáveis sem travar (ver ClientPayload/Client em types/client.ts).
    const cpfCnpjValue = normalizeCpfCnpj(form.cpf_cnpj)
    if (!isEditMode && !cpfCnpjValue) {
      errors.cpf_cnpj = ['Informe o CPF ou CNPJ do cliente.']
    } else if (cpfCnpjValue && !isValidCpfCnpj(cpfCnpjValue)) {
      errors.cpf_cnpj = ['CPF ou CNPJ inválido.']
    }

    if (!form.logradouro.trim()) errors.logradouro = ['Campo obrigatório.']
    if (!form.estado_uuid) errors.estado_uuid = ['Campo obrigatório.']
    if (!form.cidade_uuid) errors.cidade_uuid = ['Campo obrigatório.']
    if (!form.bairro_uuid) errors.bairro_uuid = ['Campo obrigatório.']
    return errors
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    const clientErrors = validateClientSide()
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }
    setFieldErrors({})
    setIsSubmitting(true)

    const payload: ClientPayload = {
      name: form.name.trim(),
      phone_primary: form.phone_primary.trim() || undefined,
      phone_secondary: form.phone_secondary.trim() || undefined,
      cpf_cnpj: normalizeCpfCnpj(form.cpf_cnpj) || undefined,
      ie: form.ie.trim() || undefined,
      ie_indicator: form.ie_indicator || undefined,
      notes: form.notes.trim() || undefined,
      is_trusted: form.is_trusted,
      is_active: form.is_active,
      dia_ideal_uuid: form.dia_ideal_uuid || undefined,
      periodo_ideal_uuid: form.periodo_ideal_uuid || undefined,
      logradouro: form.logradouro.trim(),
      numero: form.numero.trim() || undefined,
      complemento: form.complemento.trim() || undefined,
      cep: form.cep.trim() || undefined,
      estado_uuid: form.estado_uuid,
      cidade_uuid: form.cidade_uuid,
      bairro_uuid: form.bairro_uuid,
      // Backend ignora se só um dos dois vier — sempre mandar os dois juntos ou nenhum.
      ...(coords.lat !== null && coords.lng !== null ? { lat: coords.lat, lng: coords.lng } : {}),
    }

    try {
      if (isEditMode && uuid) {
        await clientService.updateClient(uuid, payload)
      } else {
        await clientService.createClient(payload)
      }
      navigate('/clientes')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o cliente agora. Tente novamente.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Clientes"
      backTo="/clientes"
      title={isEditMode ? 'Editar cliente' : 'Novo cliente'}
      subtitle={isEditMode ? 'Atualize os dados do cliente.' : 'Cadastre um novo cliente e o endereço de atendimento.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <Box sx={{ ...FORM_GRID_3_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr)' }, mb: 2 }}>
        <TextField
          label="Nome"
          value={form.name}
          onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
          error={Boolean(fieldErrors.name)}
          helperText={fieldErrors.name?.[0]}
          required
          slotProps={{ htmlInput: { maxLength: 90 } }}
        />
        <TextField
          label="Telefone principal"
          value={form.phone_primary}
          onChange={(event) => setForm((current) => ({ ...current, phone_primary: event.target.value }))}
          error={Boolean(fieldErrors.phone_primary)}
          helperText={fieldErrors.phone_primary?.[0]}
          inputMode="tel"
          slotProps={{ htmlInput: { maxLength: 30 } }}
        />
        <TextField
          label="Telefone secundário"
          value={form.phone_secondary}
          onChange={(event) => setForm((current) => ({ ...current, phone_secondary: event.target.value }))}
          error={Boolean(fieldErrors.phone_secondary)}
          helperText={fieldErrors.phone_secondary?.[0]}
          inputMode="tel"
          slotProps={{ htmlInput: { maxLength: 30 } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 1fr) minmax(0, 2fr)' }, mb: 2 }}>
        <TextField
          label="CPF/CNPJ"
          value={form.cpf_cnpj}
          onChange={(event) => setForm((current) => ({ ...current, cpf_cnpj: formatCpfCnpj(event.target.value) }))}
          error={Boolean(fieldErrors.cpf_cnpj)}
          helperText={
            fieldErrors.cpf_cnpj?.[0] ??
            (isEditMode
              ? 'Necessário para gerar a cobrança Pix corretamente para o cliente.'
              : 'Necessário para gerar a cobrança Pix corretamente para o cliente. Obrigatório para novos clientes.')
          }
          required={!isEditMode}
          inputMode="text"
          slotProps={{ htmlInput: { maxLength: 18 } }}
        />
        <TextField
          label="Inscrição estadual"
          value={form.ie}
          onChange={(event) => setForm((current) => ({ ...current, ie: event.target.value }))}
          error={Boolean(fieldErrors.ie)}
          helperText={fieldErrors.ie?.[0] ?? 'Use quando o cliente for contribuinte do ICMS.'}
          slotProps={{ htmlInput: { maxLength: 30 } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 1fr) minmax(0, 2fr)' }, mb: 2 }}>
        <TextField
          select
          label="Indicador da inscrição estadual"
          value={form.ie_indicator}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              ie_indicator: event.target.value as ClientFormState['ie_indicator'],
            }))
          }
          error={Boolean(fieldErrors.ie_indicator)}
          helperText={fieldErrors.ie_indicator?.[0] ?? 'Define como o cliente deve ser tratado fiscalmente.'}
        >
          <MenuItem value="contribuinte">Contribuinte</MenuItem>
          <MenuItem value="isento">Isento</MenuItem>
          <MenuItem value="nao_contribuinte">Não contribuinte</MenuItem>
        </TextField>
      </Box>

      <TextField
        label="Observação"
        value={form.notes}
        onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))}
        error={Boolean(fieldErrors.notes)}
        helperText={fieldErrors.notes?.[0]}
        fullWidth
        multiline
        minRows={2}
        sx={{ mb: 2 }}
      />

      <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
        <LocalAutocomplete
          label="Dia ideal"
          placeholder="Não definido"
          options={diaIdeais}
          value={diaIdeais.find((dia) => dia.uuid === form.dia_ideal_uuid) ?? null}
          onChange={(dia) => setForm((current) => ({ ...current, dia_ideal_uuid: dia?.uuid ?? '' }))}
          getOptionLabel={(dia) => dia.name}
          getOptionKey={(dia) => dia.uuid}
          error={Boolean(fieldErrors.dia_ideal_uuid)}
          helperText={fieldErrors.dia_ideal_uuid?.[0]}
        />

        <LocalAutocomplete
          label="Período ideal"
          placeholder="Não definido"
          options={periodoIdeais}
          value={periodoIdeais.find((periodo) => periodo.uuid === form.periodo_ideal_uuid) ?? null}
          onChange={(periodo) => setForm((current) => ({ ...current, periodo_ideal_uuid: periodo?.uuid ?? '' }))}
          getOptionLabel={(periodo) => periodo.name}
          getOptionKey={(periodo) => periodo.uuid}
          error={Boolean(fieldErrors.periodo_ideal_uuid)}
          helperText={fieldErrors.periodo_ideal_uuid?.[0]}
        />
      </Box>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mb: 3 }}>
        <FormControlLabel
          control={
            <Switch
              checked={form.is_trusted}
              onChange={(event) => setForm((current) => ({ ...current, is_trusted: event.target.checked }))}
            />
          }
          label="Confiável"
        />
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
        cidades={cidades}
        bairros={bairros}
        isLoadingCidades={isLoadingCidades}
        isLoadingBairros={isLoadingBairros}
        fieldErrors={fieldErrors}
        onEstadoChange={(value) => void handleEstadoChange(value)}
        onCidadeChange={(value) => void handleCidadeChange(value)}
        onBairroChange={handleBairroChange}
        onTextChange={handleAddressTextChange}
        onUseCurrentLocation={() => void handleUseCurrentLocation()}
        isLocating={isLocatingGps}
      />
    </CrudFormShell>
  )
}
