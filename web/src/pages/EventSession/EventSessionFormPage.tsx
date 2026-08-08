import { Box, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import { DateTimePickerField } from '../../components/form/DateTimePickerField'
import { FormSection } from '../../components/form/FormSection'
import * as eventService from '../../services/eventService'
import * as eventSessionService from '../../services/eventSessionService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { EVENT_SESSION_STATUS_OPTIONS, type EventSessionStatus } from '../../types/eventSession'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'

interface EventSessionFormState {
  name: string
  starts_at: string
  ends_at: string
  gate_opens_at: string
  capacity: string
  status: EventSessionStatus
  sales_start_at: string
  sales_end_at: string
}

const EMPTY_FORM: EventSessionFormState = {
  name: '',
  starts_at: '',
  ends_at: '',
  gate_opens_at: '',
  capacity: '',
  status: 'rascunho',
  sales_start_at: '',
  sales_end_at: '',
}

function toDateTimeLocal(value: string | null | undefined): string {
  if (!value) return ''
  return value.replace(' ', 'T').slice(0, 16)
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

function toApiDateTime(value: string): string | null {
  return value ? value.replace('T', ' ') : null
}

export function EventSessionFormPage() {
  const navigate = useNavigate()
  const { eventUuid = '', sessionUuid } = useParams<{ eventUuid: string; sessionUuid?: string }>()
  const isEditMode = Boolean(sessionUuid)
  const [eventName, setEventName] = useState('Evento')
  const [form, setForm] = useState<EventSessionFormState>(EMPTY_FORM)
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    const eventPromise = eventService.getEvent(eventUuid)
    const sessionPromise = sessionUuid ? eventSessionService.getEventSession(eventUuid, sessionUuid) : Promise.resolve(null)

    Promise.all([eventPromise, sessionPromise])
      .then(([event, session]) => {
        setEventName(event.name)

        if (session) {
          setForm({
            name: session.name ?? '',
            starts_at: toDateTimeLocal(session.starts_at),
            ends_at: toDateTimeLocal(session.ends_at),
            gate_opens_at: toDateTimeLocal(session.gate_opens_at),
            capacity: session.capacity === null ? '' : String(session.capacity),
            status: session.status,
            sales_start_at: toDateTimeLocal(session.sales_start_at),
            sales_end_at: toDateTimeLocal(session.sales_end_at),
          })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados da sessão agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [eventUuid, sessionUuid])

  function updateField<K extends keyof EventSessionFormState>(key: K, value: EventSessionFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim() || null,
      starts_at: toApiDateTime(form.starts_at) ?? '',
      ends_at: toApiDateTime(form.ends_at) ?? '',
      gate_opens_at: toApiDateTime(form.gate_opens_at),
      capacity: toOptionalNumber(form.capacity),
      status: form.status,
      sales_start_at: toApiDateTime(form.sales_start_at),
      sales_end_at: toApiDateTime(form.sales_end_at),
    }

    try {
      if (sessionUuid) {
        await eventSessionService.updateEventSession(eventUuid, sessionUuid, payload)
      } else {
        await eventSessionService.createEventSession(eventUuid, payload)
      }

      navigate(`/eventos/${eventUuid}/sessoes`)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a sessão agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Sessões"
      backTo={`/eventos/${eventUuid}/sessoes`}
      title={isEditMode ? 'Editar sessão' : 'Nova sessão'}
      subtitle={`${isEditMode ? 'Atualize' : 'Cadastre'} a operação de agenda de ${eventName}.`}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Identidade da sessão" description="Defina o nome operacional e o status atual desta agenda dentro do evento.">
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Nome da sessão"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0] ?? 'Opcional. Ex.: Sábado, abertura oficial, segundo horário.'}
          />
          <LocalAutocomplete
            label="Status"
            options={EVENT_SESSION_STATUS_OPTIONS}
            value={EVENT_SESSION_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
            onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as EventSessionStatus)}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            error={Boolean(fieldErrors.status)}
            helperText={fieldErrors.status?.[0]}
          />
        </Box>
      </FormSection>

      <FormSection title="Agenda e janelas" description="Configure a data principal da sessão, abertura de portões e a janela comercial de vendas.">
        <Box sx={FORM_GRID_2_SX}>
          <DateTimePickerField
            label="Início"
            value={form.starts_at}
            onChange={(value) => updateField('starts_at', value)}
            error={Boolean(fieldErrors.starts_at)}
            helperText={fieldErrors.starts_at?.[0]}
            required
          />
          <DateTimePickerField
            label="Término"
            value={form.ends_at}
            onChange={(value) => updateField('ends_at', value)}
            error={Boolean(fieldErrors.ends_at)}
            helperText={fieldErrors.ends_at?.[0]}
            required
          />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <DateTimePickerField
            label="Abertura dos portões"
            value={form.gate_opens_at}
            onChange={(value) => updateField('gate_opens_at', value)}
            error={Boolean(fieldErrors.gate_opens_at)}
            helperText={fieldErrors.gate_opens_at?.[0]}
          />
          <TextField
            label="Capacidade"
            type="number"
            value={form.capacity}
            onChange={(event) => updateField('capacity', sanitizePositiveIntegerInput(event.target.value))}
            error={Boolean(fieldErrors.capacity)}
            helperText={fieldErrors.capacity?.[0] ?? 'Opcional. Deixe vazio para não travar por capacidade aqui.'}
            slotProps={{ htmlInput: { min: 0, step: '1' } }}
          />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <DateTimePickerField
            label="Início das vendas"
            value={form.sales_start_at}
            onChange={(value) => updateField('sales_start_at', value)}
            error={Boolean(fieldErrors.sales_start_at)}
            helperText={fieldErrors.sales_start_at?.[0]}
          />
          <DateTimePickerField
            label="Fim das vendas"
            value={form.sales_end_at}
            onChange={(value) => updateField('sales_end_at', value)}
            error={Boolean(fieldErrors.sales_end_at)}
            helperText={fieldErrors.sales_end_at?.[0]}
          />
        </Box>
      </FormSection>
    </CrudFormShell>
  )
}
