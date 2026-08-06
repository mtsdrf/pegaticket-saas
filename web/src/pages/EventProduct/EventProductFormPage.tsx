import { Box, FormControlLabel, InputAdornment, Stack, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { DATETIME_FIELD_SLOT_PROPS, sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import { FormSection } from '../../components/form/FormSection'
import * as eventService from '../../services/eventService'
import * as eventProductService from '../../services/eventProductService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import {
  EVENT_PRODUCT_KIND_OPTIONS,
  EVENT_PRODUCT_STATUS_OPTIONS,
  type EventProductKind,
  type EventProductStatus,
} from '../../types/eventProduct'

interface EventProductFormState {
  event_uuid: string
  name: string
  price: string
  description: string
  quantity_available: string
  max_per_order: string
  sales_start_at: string
  sales_end_at: string
  kind: EventProductKind
  requires_plate: boolean
  requires_model: boolean
  requires_color: boolean
  status: EventProductStatus
}

const EMPTY_FORM: EventProductFormState = {
  event_uuid: '',
  name: '',
  price: '',
  description: '',
  quantity_available: '',
  max_per_order: '',
  sales_start_at: '',
  sales_end_at: '',
  kind: 'addon',
  requires_plate: false,
  requires_model: false,
  requires_color: false,
  status: 'rascunho',
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

/** Backend guarda `datetime` (`YYYY-MM-DD HH:mm:ss`) — `<input type="datetime-local">` usa `YYYY-MM-DDTHH:mm`. */
function toDateTimeLocal(value: string | null | undefined): string {
  if (!value) return ''
  return value.replace(' ', 'T').slice(0, 16)
}

export function EventProductFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [form, setForm] = useState<EventProductFormState>(EMPTY_FORM)
  const [eventOptions, setEventOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingForm(true)
    setLoadError(null)

    const eventsPromise = eventService.listEvents({ per_page: 100 })
    const recordPromise = uuid ? eventProductService.getEventProduct(uuid) : Promise.resolve(null)

    Promise.all([eventsPromise, recordPromise])
      .then(([events, record]) => {
        setEventOptions(events.items.map((event) => ({ value: event.uuid, label: event.name })))

        if (record) {
          setForm({
            event_uuid: record.event?.uuid ?? '',
            name: record.name,
            price: String(record.price),
            description: record.description ?? '',
            quantity_available: record.quantity_available === null ? '' : String(record.quantity_available),
            max_per_order: record.max_per_order === null ? '' : String(record.max_per_order),
            sales_start_at: toDateTimeLocal(record.sales_start_at),
            sales_end_at: toDateTimeLocal(record.sales_end_at),
            kind: record.kind,
            requires_plate: record.requires_plate,
            requires_model: record.requires_model,
            requires_color: record.requires_color,
            status: record.status,
          })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do adicional agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof EventProductFormState>(key: K, value: EventProductFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      event_uuid: form.event_uuid,
      name: form.name.trim(),
      price: Number(form.price),
      description: form.description.trim() || undefined,
      quantity_available: toOptionalNumber(form.quantity_available),
      max_per_order: toOptionalNumber(form.max_per_order),
      sales_start_at: form.sales_start_at ? form.sales_start_at.replace('T', ' ') : null,
      sales_end_at: form.sales_end_at ? form.sales_end_at.replace('T', ' ') : null,
      kind: form.kind,
      requires_plate: form.requires_plate,
      requires_model: form.requires_model,
      requires_color: form.requires_color,
      status: form.status,
    }

    try {
      if (uuid) {
        await eventProductService.updateEventProduct(uuid, payload)
      } else {
        await eventProductService.createEventProduct(payload)
      }
      navigate('/adicionais')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o adicional agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Adicionais"
      backTo="/adicionais"
      title={isEditMode ? 'Editar adicional' : 'Novo adicional'}
      subtitle={isEditMode ? 'Atualize os dados do adicional.' : 'Cadastre um novo adicional (ex.: estacionamento) para um evento.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Dados principais" description="Associe o adicional ao evento e defina preço, tipo e disponibilidade comercial.">
      <Box sx={FORM_GRID_2_SX}>
        <LocalAutocomplete
          label="Evento"
          options={eventOptions}
          value={eventOptions.find((option) => option.value === form.event_uuid) ?? null}
          onChange={(option) => updateField('event_uuid', option?.value ?? '')}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          required
          fullWidth
          error={Boolean(fieldErrors.event_uuid)}
          helperText={fieldErrors.event_uuid?.[0]}
        />
        <TextField
          label="Nome"
          value={form.name}
          onChange={(event) => updateField('name', event.target.value)}
          error={Boolean(fieldErrors.name)}
          helperText={fieldErrors.name?.[0]}
          required
        />
      </Box>
      <TextField
        label="Preço"
        type="number"
        value={form.price}
        onChange={(event) => updateField('price', event.target.value)}
        error={Boolean(fieldErrors.price)}
        helperText={fieldErrors.price?.[0]}
        required
        fullWidth
        slotProps={{
          input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
          htmlInput: { min: 0, step: '0.01' },
        }}
      />

      <Box sx={FORM_GRID_3_SX}>
        <LocalAutocomplete
          label="Tipo"
          options={EVENT_PRODUCT_KIND_OPTIONS}
          value={EVENT_PRODUCT_KIND_OPTIONS.find((option) => option.value === form.kind) ?? null}
          onChange={(option) => updateField('kind', (option?.value ?? 'addon') as EventProductKind)}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          error={Boolean(fieldErrors.kind)}
          helperText={fieldErrors.kind?.[0]}
        />
        <TextField
          label="Qtd. disponível"
          type="number"
          placeholder="Ilimitado"
          value={form.quantity_available}
          onChange={(event) => updateField('quantity_available', sanitizePositiveIntegerInput(event.target.value))}
          error={Boolean(fieldErrors.quantity_available)}
          helperText={fieldErrors.quantity_available?.[0]}
          slotProps={{ htmlInput: { min: 0, step: '1' } }}
        />
        <TextField
          label="Máx. por compra"
          type="number"
          value={form.max_per_order}
          onChange={(event) => updateField('max_per_order', sanitizePositiveIntegerInput(event.target.value))}
          error={Boolean(fieldErrors.max_per_order)}
          helperText={fieldErrors.max_per_order?.[0]}
          slotProps={{ htmlInput: { min: 1, step: '1' } }}
        />
      </Box>

      <Box sx={FORM_GRID_2_SX}>
        <TextField
          label="Início das vendas"
          type="datetime-local"
          value={form.sales_start_at}
          onChange={(event) => updateField('sales_start_at', event.target.value)}
          error={Boolean(fieldErrors.sales_start_at)}
          helperText={fieldErrors.sales_start_at?.[0]}
          slotProps={DATETIME_FIELD_SLOT_PROPS}
        />
        <TextField
          label="Fim das vendas"
          type="datetime-local"
          value={form.sales_end_at}
          onChange={(event) => updateField('sales_end_at', event.target.value)}
          error={Boolean(fieldErrors.sales_end_at)}
          helperText={fieldErrors.sales_end_at?.[0]}
          slotProps={DATETIME_FIELD_SLOT_PROPS}
        />
      </Box>

      <LocalAutocomplete
        label="Status"
        options={EVENT_PRODUCT_STATUS_OPTIONS}
        value={EVENT_PRODUCT_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
        onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as EventProductStatus)}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        error={Boolean(fieldErrors.status)}
        helperText={fieldErrors.status?.[0]}
      />
      </FormSection>

      <FormSection title="Descrição e requisitos" description="Explique o item vendido e, quando aplicável, exija os dados do veículo.">
      <TextField
        label="Descrição"
        value={form.description}
        onChange={(event) => updateField('description', event.target.value)}
        error={Boolean(fieldErrors.description)}
        helperText={fieldErrors.description?.[0]}
        fullWidth
        multiline
        minRows={2}
      />

      {form.kind === 'parking' && (
        <Stack spacing={1}>
          <FormControlLabel
            control={
              <Switch
                checked={form.requires_plate}
                onChange={(event) => updateField('requires_plate', event.target.checked)}
              />
            }
            label="Exige placa do veículo"
          />
          <FormControlLabel
            control={
              <Switch
                checked={form.requires_model}
                onChange={(event) => updateField('requires_model', event.target.checked)}
              />
            }
            label="Exige modelo do veículo"
          />
          <FormControlLabel
            control={
              <Switch
                checked={form.requires_color}
                onChange={(event) => updateField('requires_color', event.target.checked)}
              />
            }
            label="Exige cor do veículo"
          />
        </Stack>
      )}
      </FormSection>
    </CrudFormShell>
  )
}
