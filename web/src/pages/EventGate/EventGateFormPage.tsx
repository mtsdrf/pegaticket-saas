import { Autocomplete, Box, FormControlLabel, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { FormSection } from '../../components/form/FormSection'
import * as eventGateService from '../../services/eventGateService'
import * as eventService from '../../services/eventService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { TicketType } from '../../types/ticketType'

interface EventGateFormState {
  name: string
  is_active: boolean
}

const EMPTY_FORM: EventGateFormState = {
  name: '',
  is_active: true,
}

export function EventGateFormPage() {
  const navigate = useNavigate()
  const { eventUuid = '', gateUuid } = useParams<{ eventUuid: string; gateUuid?: string }>()
  const isEditMode = Boolean(gateUuid)
  const [eventName, setEventName] = useState('Evento')
  const [form, setForm] = useState<EventGateFormState>(EMPTY_FORM)
  const [ticketTypes, setTicketTypes] = useState<TicketType[]>([])
  const [allowedTicketTypes, setAllowedTicketTypes] = useState<TicketType[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    const eventPromise = eventService.getEvent(eventUuid)
    const gatePromise = gateUuid ? eventGateService.getEventGate(eventUuid, gateUuid) : Promise.resolve(null)
    const ticketTypesPromise = ticketTypeService.listTicketTypes({ event_uuid: eventUuid, per_page: 100, sort_by: 'name', sort_dir: 'asc' })

    Promise.all([eventPromise, gatePromise, ticketTypesPromise])
      .then(([event, gate, ticketTypeResult]) => {
        setEventName(event.name)
        setTicketTypes(ticketTypeResult.items)

        if (gate) {
          setForm({
            name: gate.name,
            is_active: gate.is_active,
          })
          setAllowedTicketTypes(
            ticketTypeResult.items.filter((ticketType) => gate.allowed_ticket_types.some((allowed) => allowed.uuid === ticketType.uuid)),
          )
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados da portaria agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [eventUuid, gateUuid])

  function updateField<K extends keyof EventGateFormState>(key: K, value: EventGateFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      is_active: form.is_active,
      ticket_type_uuids: allowedTicketTypes.map((ticketType) => ticketType.uuid),
    }

    try {
      if (gateUuid) {
        await eventGateService.updateEventGate(eventUuid, gateUuid, payload)
      } else {
        await eventGateService.createEventGate(eventUuid, payload)
      }

      navigate(`/eventos/${eventUuid}/portarias`)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a portaria agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Portarias"
      backTo={`/eventos/${eventUuid}/portarias`}
      title={isEditMode ? 'Editar portaria' : 'Nova portaria'}
      subtitle={`${isEditMode ? 'Atualize' : 'Cadastre'} o ponto de acesso de ${eventName}.`}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Identidade da portaria" description="Defina o nome operacional do ponto de acesso e se ele está liberado para uso.">
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Nome da portaria"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0] ?? 'Ex.: Entrada principal, Camarotes, Backstage.'}
            required
          />
          <Box sx={{ display: 'flex', alignItems: 'center', minHeight: 44 }}>
            <FormControlLabel
              control={<Switch checked={form.is_active} onChange={(event) => updateField('is_active', event.target.checked)} />}
              label="Portaria ativa"
            />
          </Box>
        </Box>
      </FormSection>

      <FormSection
        title="Regras de acesso"
        description="Defina quais tipos de ingresso esta portaria aceita. Deixe vazio para aceitar qualquer tipo."
      >
        <Autocomplete
          multiple
          size="small"
          options={ticketTypes}
          value={allowedTicketTypes}
          onChange={(_event, value) => setAllowedTicketTypes(value)}
          getOptionLabel={(ticketType) => ticketType.name}
          isOptionEqualToValue={(option, val) => option.uuid === val.uuid}
          noOptionsText="Nenhum tipo de ingresso cadastrado"
          renderInput={(params) => (
            <TextField
              {...params}
              label="Tipos de ingresso permitidos"
              error={Boolean(fieldErrors.ticket_type_uuids)}
              helperText={fieldErrors.ticket_type_uuids?.[0] ?? 'Vazio = portaria aberta, aceita qualquer tipo de ingresso.'}
            />
          )}
        />
      </FormSection>
    </CrudFormShell>
  )
}
