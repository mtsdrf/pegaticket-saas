import { Box, FormControlLabel, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import * as ticketBatchService from '../../services/ticketBatchService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { TICKET_BATCH_STATUS_OPTIONS, type TicketBatchStatus } from '../../types/ticketBatch'

interface TicketBatchFormState {
  name: string
  price: string
  quantity: string
  starts_at: string
  ends_at: string
  priority: string
  auto_advance: boolean
  status: TicketBatchStatus
}

const EMPTY_FORM: TicketBatchFormState = {
  name: '',
  price: '',
  quantity: '',
  starts_at: '',
  ends_at: '',
  priority: '',
  auto_advance: true,
  status: 'rascunho',
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

export function TicketBatchFormPage() {
  const navigate = useNavigate()
  const { ticketTypeUuid = '', batchUuid } = useParams<{ ticketTypeUuid: string; batchUuid?: string }>()
  const isEditMode = Boolean(batchUuid)
  const [ticketTypeName, setTicketTypeName] = useState('Tipo de ingresso')
  const [form, setForm] = useState<TicketBatchFormState>(EMPTY_FORM)
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    const ticketTypePromise = ticketTypeService.getTicketType(ticketTypeUuid)
    const batchPromise = batchUuid ? ticketBatchService.getTicketBatch(ticketTypeUuid, batchUuid) : Promise.resolve(null)

    Promise.all([ticketTypePromise, batchPromise])
      .then(([ticketType, batch]) => {
        setTicketTypeName(ticketType.name)

        if (batch) {
          setForm({
            name: batch.name,
            price: String(batch.price),
            quantity: String(batch.quantity),
            starts_at: toDateTimeLocal(batch.starts_at),
            ends_at: toDateTimeLocal(batch.ends_at),
            priority: batch.priority === null ? '' : String(batch.priority),
            auto_advance: batch.auto_advance,
            status: batch.status,
          })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do lote agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [batchUuid, ticketTypeUuid])

  function updateField<K extends keyof TicketBatchFormState>(key: K, value: TicketBatchFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      price: Number(form.price),
      quantity: Number(form.quantity),
      starts_at: toApiDateTime(form.starts_at),
      ends_at: toApiDateTime(form.ends_at),
      priority: toOptionalNumber(form.priority),
      auto_advance: form.auto_advance,
      status: form.status,
    }

    try {
      if (batchUuid) {
        await ticketBatchService.updateTicketBatch(ticketTypeUuid, batchUuid, payload)
      } else {
        await ticketBatchService.createTicketBatch(ticketTypeUuid, payload)
      }

      navigate(`/tipos-de-ingresso/${ticketTypeUuid}/lotes`)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o lote agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Lotes"
      backTo={`/tipos-de-ingresso/${ticketTypeUuid}/lotes`}
      title={isEditMode ? 'Editar lote' : 'Novo lote'}
      subtitle={`${isEditMode ? 'Atualize' : 'Cadastre'} a estratégia comercial de ${ticketTypeName}.`}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
        <TextField
          label="Nome do lote"
          value={form.name}
          onChange={(event) => updateField('name', event.target.value)}
          error={Boolean(fieldErrors.name)}
          helperText={fieldErrors.name?.[0]}
          required
        />
        <TextField
          label="Preço"
          type="number"
          value={form.price}
          onChange={(event) => updateField('price', event.target.value)}
          error={Boolean(fieldErrors.price)}
          helperText={fieldErrors.price?.[0]}
          required
          slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
        />
        <TextField
          label="Quantidade"
          type="number"
          value={form.quantity}
          onChange={(event) => updateField('quantity', event.target.value)}
          error={Boolean(fieldErrors.quantity)}
          helperText={fieldErrors.quantity?.[0]}
          required
          slotProps={{ htmlInput: { min: 1, step: '1' } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
        <TextField
          label="Início"
          type="datetime-local"
          value={form.starts_at}
          onChange={(event) => updateField('starts_at', event.target.value)}
          error={Boolean(fieldErrors.starts_at)}
          helperText={fieldErrors.starts_at?.[0]}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="Fim"
          type="datetime-local"
          value={form.ends_at}
          onChange={(event) => updateField('ends_at', event.target.value)}
          error={Boolean(fieldErrors.ends_at)}
          helperText={fieldErrors.ends_at?.[0]}
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
        <TextField
          label="Prioridade"
          type="number"
          value={form.priority}
          onChange={(event) => updateField('priority', event.target.value)}
          error={Boolean(fieldErrors.priority)}
          helperText={fieldErrors.priority?.[0] ?? 'Menor número = entra antes.'}
          slotProps={{ htmlInput: { min: 0, step: '1' } }}
        />
        <LocalAutocomplete
          label="Status"
          options={TICKET_BATCH_STATUS_OPTIONS}
          value={TICKET_BATCH_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
          onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as TicketBatchStatus)}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          error={Boolean(fieldErrors.status)}
          helperText={fieldErrors.status?.[0]}
        />
        <FormControlLabel
          control={<Switch checked={form.auto_advance} onChange={(event) => updateField('auto_advance', event.target.checked)} />}
          label="Avanço automático"
          sx={{ minHeight: 56, alignItems: 'center' }}
        />
      </Box>
    </CrudFormShell>
  )
}
