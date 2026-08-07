import { Box, TextField } from '@mui/material'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AsyncAutocomplete } from '../../components/crud/AsyncAutocomplete'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import { FormSection } from '../../components/form/FormSection'
import * as eventService from '../../services/eventService'
import * as eventSessionService from '../../services/eventSessionService'
import * as guestListService from '../../services/guestListService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Event } from '../../types/event'
import type { EventSession } from '../../types/eventSession'
import type { TicketType } from '../../types/ticketType'

interface GuestListFormState {
  name: string
  quantity_per_entry: string
  notes: string
}

const EMPTY_FORM: GuestListFormState = {
  name: '',
  quantity_per_entry: '1',
  notes: '',
}

export function GuestListFormPage() {
  const navigate = useNavigate()
  const { uuid } = useParams<{ uuid?: string }>()
  const isEditMode = Boolean(uuid)
  const [form, setForm] = useState<GuestListFormState>(EMPTY_FORM)
  const [selectedEvent, setSelectedEvent] = useState<Event | null>(null)
  const [selectedSession, setSelectedSession] = useState<EventSession | null>(null)
  const [selectedTicketType, setSelectedTicketType] = useState<TicketType | null>(null)
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (!uuid) {
      setIsLoadingForm(false)
      return
    }

    Promise.all([
      guestListService.getGuestList(uuid),
    ])
      .then(async ([guestList]) => {
        const [event, session, ticketType] = await Promise.all([
          eventService.getEvent(guestList.event.uuid),
          guestList.session ? eventSessionService.getEventSession(guestList.event.uuid, guestList.session.uuid) : Promise.resolve(null),
          ticketTypeService.getTicketType(guestList.ticket_type.uuid),
        ])

        setSelectedEvent(event)
        setSelectedSession(session)
        setSelectedTicketType(ticketType)
        setForm({
          name: guestList.name,
          quantity_per_entry: String(guestList.quantity_per_entry),
          notes: guestList.notes ?? '',
        })
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a lista de convidados agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof GuestListFormState>(key: K, value: GuestListFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  const fetchEvents = useCallback(
    (query: string) => eventService.listEvents({ name: query || undefined, per_page: 15, sort_by: 'starts_at', sort_dir: 'desc' }).then((r) => r.items),
    [],
  )

  const fetchSessions = useCallback(
    (query: string) =>
      selectedEvent
        ? eventSessionService
            .listEventSessions(selectedEvent.uuid, { per_page: 100 })
            .then((r) => r.items.filter((session) => !query || (session.name ?? '').toLowerCase().includes(query.toLowerCase())))
        : Promise.resolve([]),
    [selectedEvent],
  )

  const fetchTicketTypes = useCallback(
    (query: string) =>
      selectedEvent
        ? ticketTypeService
            .listTicketTypes({ name: query || undefined, event_uuid: selectedEvent.uuid, per_page: 15, sort_by: 'name', sort_dir: 'asc' })
            .then((r) => r.items)
        : Promise.resolve([]),
    [selectedEvent],
  )

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      event_uuid: selectedEvent?.uuid,
      event_session_uuid: selectedSession?.uuid ?? null,
      ticket_type_uuid: selectedTicketType?.uuid,
      name: form.name.trim(),
      quantity_per_entry: Number(form.quantity_per_entry) || 1,
      notes: form.notes.trim() || null,
    }

    try {
      if (uuid) {
        await guestListService.updateGuestList(uuid, payload)
      } else {
        await guestListService.createGuestList({
          event_uuid: payload.event_uuid ?? '',
          event_session_uuid: payload.event_session_uuid ?? undefined,
          ticket_type_uuid: payload.ticket_type_uuid ?? '',
          name: payload.name,
          quantity_per_entry: payload.quantity_per_entry,
          notes: payload.notes ?? undefined,
        })
      }
      navigate('/listas-de-convidados')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a lista agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Listas de convidados"
      backTo="/listas-de-convidados"
      title={isEditMode ? 'Editar lista de convidados' : 'Nova lista de convidados'}
      subtitle={isEditMode ? 'Atualize as regras da lista e o contexto do evento.' : 'Cadastre uma lista para distribuir cortesias com link individual.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Contexto da lista" description="Defina o evento, a sessão opcional e o tipo de ingresso vinculado a esta lista.">
        <Box sx={FORM_GRID_2_SX}>
          <AsyncAutocomplete
            label="Evento"
            value={selectedEvent}
            onChange={(value) => {
              setSelectedEvent(value)
              setSelectedSession(null)
              setSelectedTicketType(null)
            }}
            fetchOptions={fetchEvents}
            getOptionLabel={(item) => item.name}
            getOptionKey={(item) => item.uuid}
            required
            error={Boolean(fieldErrors.event_uuid)}
            helperText={fieldErrors.event_uuid?.[0]}
          />
          <AsyncAutocomplete
            label="Sessão"
            value={selectedSession}
            onChange={setSelectedSession}
            fetchOptions={fetchSessions}
            getOptionLabel={(item) => item.name ?? 'Sessão sem nome'}
            getOptionKey={(item) => item.uuid}
            disabled={!selectedEvent}
            error={Boolean(fieldErrors.event_session_uuid)}
            helperText={fieldErrors.event_session_uuid?.[0] ?? (!selectedEvent ? 'Escolha o evento primeiro.' : 'Opcional.')}
          />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <AsyncAutocomplete
            label="Tipo de ingresso"
            value={selectedTicketType}
            onChange={setSelectedTicketType}
            fetchOptions={fetchTicketTypes}
            getOptionLabel={(item) => item.name}
            getOptionKey={(item) => item.uuid}
            required
            disabled={!selectedEvent}
            error={Boolean(fieldErrors.ticket_type_uuid)}
            helperText={fieldErrors.ticket_type_uuid?.[0] ?? (!selectedEvent ? 'Escolha o evento primeiro.' : undefined)}
          />
          <TextField
            label="Ingressos por convidado"
            type="number"
            value={form.quantity_per_entry}
            onChange={(event) => updateField('quantity_per_entry', sanitizePositiveIntegerInput(event.target.value))}
            error={Boolean(fieldErrors.quantity_per_entry)}
            helperText={fieldErrors.quantity_per_entry?.[0]}
            slotProps={{ htmlInput: { min: 1, max: 20, step: '1' } }}
            required
          />
        </Box>
      </FormSection>

      <FormSection title="Identidade da lista" description="Defina o nome operacional e observações internas desta lista de convidados.">
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Nome da lista"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0]}
            required
          />
          <TextField
            label="Observações"
            value={form.notes}
            onChange={(event) => updateField('notes', event.target.value)}
            error={Boolean(fieldErrors.notes)}
            helperText={fieldErrors.notes?.[0] ?? 'Opcional. Uso interno da equipe.'}
            multiline
            minRows={1}
          />
        </Box>
      </FormSection>
    </CrudFormShell>
  )
}
