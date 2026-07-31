import AddCircleOutlineIcon from '@mui/icons-material/AddCircleOutlineOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import { Alert, Box, Button, Divider, IconButton, Skeleton, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { ClientAddressFields, type ClientAddressValues } from '../../../components/client/ClientAddressFields'
import * as locationService from '../../../services/locationService'
import * as storeAddressService from '../../../services/storeAddressService'
import * as storeBusinessHoursService from '../../../services/storeBusinessHoursService'
import { ApiRequestError, getApiErrorMessage } from '../../../types/api'
import { WEEKDAY_LABELS, type DayOfWeek, type StoreBusinessHourDay } from '../../../types/storeBusinessHours'
import type { Bairro, Cidade, Estado } from '../../../types/location'

const WEEKDAY_ORDER: DayOfWeek[] = [0, 1, 2, 3, 4, 5, 6]
const MAX_SHIFTS_PER_DAY = 4

/**
 * `GET` devolve `opens_at`/`closes_at` como `HH:mm:ss` (coluna `TIME` sem
 * cast no backend, ver `StoreBusinessHourResource`), mas o `PUT` só aceita
 * `HH:mm` (`date_format:H:i` na Request) — trunca aqui, um único lugar,
 * pra reenviar o mesmo valor carregado sem erro de validação.
 */
function normalizeTime(value: string | null): string {
  return value ? value.slice(0, 5) : ''
}

interface Shift {
  opens_at: string
  closes_at: string
}

interface DayState {
  isClosed: boolean
  shifts: Shift[]
}

type ShiftErrors = Partial<Record<'opens_at' | 'closes_at', string>>

/** Agrupa os itens do backend por `day_of_week` — dia sem turno aberto vira "fechado". */
function groupDays(data: StoreBusinessHourDay[]): Record<DayOfWeek, DayState> {
  const result = {} as Record<DayOfWeek, DayState>
  for (const day of WEEKDAY_ORDER) result[day] = { isClosed: true, shifts: [] }

  for (const entry of data) {
    if (entry.is_closed || !entry.opens_at || !entry.closes_at) continue
    const day = entry.day_of_week
    result[day].isClosed = false
    result[day].shifts.push({ opens_at: normalizeTime(entry.opens_at), closes_at: normalizeTime(entry.closes_at) })
  }

  for (const day of WEEKDAY_ORDER) {
    if (!result[day].isClosed && result[day].shifts.length === 0) result[day].isClosed = true
  }

  return result
}

/** Serializa o estado agrupado no array flexível que o backend espera (turnos abertos + placeholder fechado por dia). */
function buildPayload(days: Record<DayOfWeek, DayState>): StoreBusinessHourDay[] {
  const out: StoreBusinessHourDay[] = []
  for (const day of WEEKDAY_ORDER) {
    const state = days[day]
    if (state.isClosed || state.shifts.length === 0) {
      out.push({ day_of_week: day, opens_at: null, closes_at: null, is_closed: true })
      continue
    }
    for (const shift of state.shifts) {
      out.push({ day_of_week: day, opens_at: shift.opens_at || null, closes_at: shift.closes_at || null, is_closed: false })
    }
  }
  return out
}

function minutesOfDay(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}

/** Turno em minutos, estendendo o fim em 24h quando atravessa a meia-noite (mesma regra do backend). */
function shiftRange(shift: Shift): [number, number] {
  const start = minutesOfDay(shift.opens_at)
  let end = minutesOfDay(shift.closes_at)
  if (end <= start) end += 24 * 60
  return [start, end]
}

/** Horário de funcionamento — múltiplos turnos por dia, get/replace em lote (nunca CRUD por dia). */
function BusinessHoursSection() {
  const [days, setDays] = useState<Record<DayOfWeek, DayState>>(() => groupDays([]))
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [shiftErrors, setShiftErrors] = useState<Record<string, ShiftErrors>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    storeBusinessHoursService
      .getBusinessHours()
      .then((data) => setDays(groupDays(data)))
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os horários agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  function patchDay(day: DayOfWeek, patch: (state: DayState) => DayState) {
    setDays((current) => ({ ...current, [day]: patch(current[day]) }))
  }

  function handleToggleClosed(day: DayOfWeek, isOpen: boolean) {
    patchDay(day, (state) =>
      isOpen
        ? { isClosed: false, shifts: state.shifts.length > 0 ? state.shifts : [{ opens_at: '', closes_at: '' }] }
        : { isClosed: true, shifts: [] },
    )
  }

  function addShift(day: DayOfWeek) {
    patchDay(day, (state) =>
      state.shifts.length >= MAX_SHIFTS_PER_DAY ? state : { ...state, shifts: [...state.shifts, { opens_at: '', closes_at: '' }] },
    )
  }

  function removeShift(day: DayOfWeek, index: number) {
    patchDay(day, (state) => ({ ...state, shifts: state.shifts.filter((_, i) => i !== index) }))
  }

  function updateShift(day: DayOfWeek, index: number, patch: Partial<Shift>) {
    patchDay(day, (state) => ({
      ...state,
      shifts: state.shifts.map((shift, i) => (i === index ? { ...shift, ...patch } : shift)),
    }))
  }

  function validate(): boolean {
    const errors: Record<string, ShiftErrors> = {}

    for (const day of WEEKDAY_ORDER) {
      const state = days[day]
      if (state.isClosed) continue

      state.shifts.forEach((shift, index) => {
        const key = `${day}:${index}`
        const err: ShiftErrors = {}
        if (!shift.opens_at) err.opens_at = 'Informe a abertura.'
        if (!shift.closes_at) err.closes_at = 'Informe o fechamento.'
        if (shift.opens_at && shift.closes_at && shift.opens_at === shift.closes_at) {
          err.closes_at = 'Abertura e fechamento não podem ser iguais.'
        }
        if (Object.keys(err).length > 0) errors[key] = err
      })

      const complete = state.shifts.every((shift) => shift.opens_at && shift.closes_at && shift.opens_at !== shift.closes_at)
      if (complete) {
        for (let i = 0; i < state.shifts.length; i++) {
          for (let j = i + 1; j < state.shifts.length; j++) {
            const [aStart, aEnd] = shiftRange(state.shifts[i])
            const [bStart, bEnd] = shiftRange(state.shifts[j])
            if (aStart < bEnd && bStart < aEnd) {
              const key = `${day}:${j}`
              errors[key] = { ...errors[key], opens_at: 'Turno sobreposto a outro.' }
            }
          }
        }
      }
    }

    setShiftErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSubmit() {
    setFormError(null)
    setSuccessMessage(null)

    if (!validate()) {
      setFormError('Corrija os horários destacados antes de salvar.')
      return
    }

    setIsSubmitting(true)
    try {
      const updated = await storeBusinessHoursService.updateBusinessHours(buildPayload(days))
      setDays(groupDays(updated))
      setSuccessMessage('Horários salvos com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar os horários agora.'))
      if (error instanceof ApiRequestError) validate()
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <ScheduleOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Horário de funcionamento</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Configure vários turnos no mesmo dia (ex.: manhã e tarde). Fora do horário, o checkout da loja online bloqueia
        novos pedidos automaticamente.
      </Typography>

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={280} />
      ) : (
        !loadError && (
          <>
            {formError && (
              <Alert severity="error" sx={{ mb: 2.5 }}>
                {formError}
              </Alert>
            )}
            {successMessage && (
              <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
                {successMessage}
              </Alert>
            )}

            <Stack spacing={2}>
              {WEEKDAY_ORDER.map((day) => {
                const state = days[day]
                return (
                  <Box key={day}>
                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ alignItems: { xs: 'stretch', sm: 'flex-start' } }}>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', minWidth: { sm: 200 }, pt: { sm: 0.75 } }}>
                        <Switch
                          checked={!state.isClosed}
                          onChange={(event) => handleToggleClosed(day, event.target.checked)}
                          slotProps={{ input: { 'aria-label': `${WEEKDAY_LABELS[day]} aberto` } }}
                        />
                        <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>{WEEKDAY_LABELS[day]}</Typography>
                      </Stack>

                      {state.isClosed ? (
                        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', pt: { sm: 1 } }}>Fechado</Typography>
                      ) : (
                        <Stack spacing={1} sx={{ flex: 1 }}>
                          {state.shifts.map((shift, index) => {
                            const errors = shiftErrors[`${day}:${index}`]
                            return (
                              <Stack key={index} direction="row" spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                                <TextField
                                  label="Abre"
                                  type="time"
                                  size="small"
                                  value={shift.opens_at}
                                  onChange={(event) => updateShift(day, index, { opens_at: event.target.value })}
                                  error={Boolean(errors?.opens_at)}
                                  helperText={errors?.opens_at}
                                  slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <TextField
                                  label="Fecha"
                                  type="time"
                                  size="small"
                                  value={shift.closes_at}
                                  onChange={(event) => updateShift(day, index, { closes_at: event.target.value })}
                                  error={Boolean(errors?.closes_at)}
                                  helperText={
                                    errors?.closes_at ??
                                    (shift.opens_at && shift.closes_at && shift.closes_at <= shift.opens_at ? 'Dia seguinte' : undefined)
                                  }
                                  slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <IconButton
                                  aria-label={`Remover turno ${index + 1} de ${WEEKDAY_LABELS[day]}`}
                                  disabled={state.shifts.length <= 1}
                                  onClick={() => removeShift(day, index)}
                                  sx={{ mt: 0.5 }}
                                >
                                  <DeleteOutlineIcon fontSize="small" />
                                </IconButton>
                              </Stack>
                            )
                          })}
                          {state.shifts.length < MAX_SHIFTS_PER_DAY && (
                            <Box>
                              <Button size="small" variant="text" startIcon={<AddCircleOutlineIcon />} onClick={() => addShift(day)}>
                                Adicionar turno
                              </Button>
                            </Box>
                          )}
                        </Stack>
                      )}
                    </Stack>
                    <Divider sx={{ mt: 2 }} />
                  </Box>
                )
              })}
            </Stack>

            <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
              <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 160 }}>
                {isSubmitting ? 'Salvando…' : 'Salvar horários'}
              </Button>
            </Stack>
          </>
        )
      )}
    </Box>
  )
}

const EMPTY_STORE_ADDRESS: ClientAddressValues = {
  estado_uuid: '',
  cidade_uuid: '',
  bairro_uuid: '',
  logradouro: '',
  numero: '',
  complemento: '',
  cep: '',
}

/** "Endereço da loja" — cria/edita o endereço próprio da empresa (`store-settings/address`), cascata Estado→Cidade→Bairro reaproveitando `ClientAddressFields` + `locationService` (staff). */
function StoreAddressSection() {
  const [values, setValues] = useState<ClientAddressValues>(EMPTY_STORE_ADDRESS)
  const [hasAddress, setHasAddress] = useState(false)
  const [estados, setEstados] = useState<Estado[]>([])
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function load() {
    let cancelled = false
    setIsLoading(true)
    setLoadError(null)
    ;(async () => {
      try {
        const [estadosList, address] = await Promise.all([locationService.getEstados(), storeAddressService.getStoreAddress()])
        if (cancelled) return
        setEstados(estadosList)

        if (address) {
          const [cidadesList, bairrosList] = await Promise.all([
            locationService.getCidades(address.estado_uuid),
            locationService.getBairros(address.cidade_uuid),
          ])
          if (cancelled) return
          setCidades(cidadesList)
          setBairros(bairrosList)
          setValues({
            estado_uuid: address.estado_uuid,
            cidade_uuid: address.cidade_uuid,
            bairro_uuid: address.bairro_uuid,
            logradouro: address.logradouro,
            numero: address.numero ?? '',
            complemento: address.complemento ?? '',
            cep: address.cep ?? '',
          })
          setHasAddress(true)
        }
      } catch (error) {
        if (!cancelled) setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o endereço da loja agora.'))
      } finally {
        if (!cancelled) setIsLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }

  useEffect(load, [])

  async function handleEstadoChange(estadoUuid: string) {
    setValues((current) => ({ ...current, estado_uuid: estadoUuid, cidade_uuid: '', bairro_uuid: '' }))
    setCidades([])
    setBairros([])
    if (!estadoUuid) return
    setIsLoadingCidades(true)
    try {
      setCidades(await locationService.getCidades(estadoUuid))
    } catch {
      setCidades([])
    } finally {
      setIsLoadingCidades(false)
    }
  }

  async function handleCidadeChange(cidadeUuid: string) {
    setValues((current) => ({ ...current, cidade_uuid: cidadeUuid, bairro_uuid: '' }))
    setBairros([])
    if (!cidadeUuid) return
    setIsLoadingBairros(true)
    try {
      setBairros(await locationService.getBairros(cidadeUuid))
    } catch {
      setBairros([])
    } finally {
      setIsLoadingBairros(false)
    }
  }

  function handleTextChange(field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) {
    setValues((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit() {
    setFormError(null)
    setSuccessMessage(null)

    const errors: Record<string, string[]> = {}
    if (!values.logradouro.trim()) errors.logradouro = ['Informe o logradouro.']
    if (!values.estado_uuid) errors.estado_uuid = ['Selecione o estado.']
    if (!values.cidade_uuid) errors.cidade_uuid = ['Selecione a cidade.']
    if (!values.bairro_uuid) errors.bairro_uuid = ['Selecione o bairro.']
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }
    setFieldErrors({})

    setIsSubmitting(true)
    try {
      const updated = await storeAddressService.updateStoreAddress({
        logradouro: values.logradouro.trim(),
        numero: values.numero.trim() || undefined,
        complemento: values.complemento.trim() || undefined,
        cep: values.cep.trim() || undefined,
        estado_uuid: values.estado_uuid,
        cidade_uuid: values.cidade_uuid,
        bairro_uuid: values.bairro_uuid,
      })
      setValues({
        estado_uuid: updated.estado_uuid,
        cidade_uuid: updated.cidade_uuid,
        bairro_uuid: updated.bairro_uuid,
        logradouro: updated.logradouro,
        numero: updated.numero ?? '',
        complemento: updated.complemento ?? '',
        cep: updated.cep ?? '',
      })
      setHasAddress(true)
      setSuccessMessage('Endereço da loja salvo com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o endereço da loja agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box sx={{ mt: 4 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <PlaceOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Endereço da loja</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Aparece no perfil público da loja online, junto do mapa de localização. Sem esse endereço, o cálculo da rota
        de entrega não tem de onde partir e pode falhar ou ficar incorreto — por isso é um passo obrigatório da
        implantação.
      </Typography>

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={() => load()}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={220} />
      ) : (
        !loadError && (
          <>
            {formError && (
              <Alert severity="error" sx={{ mb: 2.5 }}>
                {formError}
              </Alert>
            )}
            {successMessage && (
              <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
                {successMessage}
              </Alert>
            )}

            <ClientAddressFields
              values={values}
              estados={estados}
              cidades={cidades}
              bairros={bairros}
              isLoadingCidades={isLoadingCidades}
              isLoadingBairros={isLoadingBairros}
              fieldErrors={fieldErrors}
              onEstadoChange={(value) => void handleEstadoChange(value)}
              onCidadeChange={(value) => void handleCidadeChange(value)}
              onBairroChange={(value) => setValues((current) => ({ ...current, bairro_uuid: value }))}
              onTextChange={handleTextChange}
            />

            <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
              <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 160 }}>
                {isSubmitting ? 'Salvando…' : hasAddress ? 'Salvar endereço' : 'Cadastrar endereço'}
              </Button>
            </Stack>
          </>
        )
      )}
    </Box>
  )
}

/**
 * Bloco "Horário e Endereço" — migrado de `StoreBusinessSettingsPage`
 * (2026-07-24). Variável única/singleton por tenant (get/replace em lote),
 * diferente das 3 seções que ficam em `/configuracoes/loja-online`
 * (CRUDs de registros próprios: taxas por bairro, cupons, promoções).
 * Gated por `storefront,update` (mesma permissão que já protegia a tela
 * inteira antes da divisão).
 */
export function ScheduleAddressBlock() {
  return (
    <>
      <BusinessHoursSection />
      <StoreAddressSection />
    </>
  )
}
