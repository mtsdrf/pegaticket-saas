import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Box,
  FormControlLabel,
  InputAdornment,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { ImageUploadField } from '../../components/shared/ImageUploadField'
import * as eventService from '../../services/eventService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { TICKET_TYPE_STATUS_OPTIONS, type TicketTypeStatus } from '../../types/ticketType'

interface TicketTypeFormState {
  event_uuid: string
  name: string
  price: string
  description: string
  quantity_available: string
  min_per_order: string
  max_per_order: string
  sales_start_at: string
  sales_end_at: string
  status: TicketTypeStatus
  sku: string
  barcode: string
  brand: string
  unit: string
  is_lot_controlled: boolean
  is_expiry_controlled: boolean
  is_serial_controlled: boolean
  min_stock: string
  max_stock: string
  reorder_point: string
  reorder_qty: string
  last_purchase_cost: string
}

const EMPTY_FORM: TicketTypeFormState = {
  event_uuid: '',
  name: '',
  price: '',
  description: '',
  quantity_available: '',
  min_per_order: '',
  max_per_order: '',
  sales_start_at: '',
  sales_end_at: '',
  status: 'rascunho',
  sku: '',
  barcode: '',
  brand: '',
  unit: '',
  is_lot_controlled: false,
  is_expiry_controlled: false,
  is_serial_controlled: false,
  min_stock: '',
  max_stock: '',
  reorder_point: '',
  reorder_qty: '',
  last_purchase_cost: '',
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

/** Backend guarda `datetime` (`YYYY-MM-DD HH:mm:ss`) — `<input type="datetime-local">` usa `YYYY-MM-DDTHH:mm`. */
function toDateTimeLocal(value: string | null | undefined): string {
  if (!value) return ''
  return value.replace(' ', 'T').slice(0, 16)
}

export function TicketTypeFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [form, setForm] = useState<TicketTypeFormState>(EMPTY_FORM)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null)
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
    const recordPromise = uuid ? ticketTypeService.getTicketType(uuid) : Promise.resolve(null)

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
            min_per_order: record.min_per_order === null ? '' : String(record.min_per_order),
            max_per_order: record.max_per_order === null ? '' : String(record.max_per_order),
            sales_start_at: toDateTimeLocal(record.sales_start_at),
            sales_end_at: toDateTimeLocal(record.sales_end_at),
            status: record.status,
            sku: record.sku ?? '',
            barcode: record.barcode ?? '',
            brand: record.brand ?? '',
            unit: record.unit ?? '',
            is_lot_controlled: record.is_lot_controlled,
            is_expiry_controlled: record.is_expiry_controlled,
            is_serial_controlled: record.is_serial_controlled,
            // decimal:3 no backend chega como string com 3 casas fixas
            // (ex. "5.000") — Number()+String() normaliza pro campo editável
            // sem o ".000" que confundia.
            min_stock: record.min_stock === null ? '' : String(Number(record.min_stock)),
            max_stock: record.max_stock === null ? '' : String(Number(record.max_stock)),
            reorder_point: record.reorder_point === null ? '' : String(Number(record.reorder_point)),
            reorder_qty: record.reorder_qty === null ? '' : String(Number(record.reorder_qty)),
            last_purchase_cost: record.last_purchase_cost == null ? '' : String(record.last_purchase_cost),
          })
          setExistingImageUrl(record.image_url)
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do tipo de ingresso agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof TicketTypeFormState>(key: K, value: TicketTypeFormState[K]) {
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
      min_per_order: toOptionalNumber(form.min_per_order),
      max_per_order: toOptionalNumber(form.max_per_order),
      sales_start_at: form.sales_start_at ? form.sales_start_at.replace('T', ' ') : null,
      sales_end_at: form.sales_end_at ? form.sales_end_at.replace('T', ' ') : null,
      status: form.status,
      sku: form.sku.trim() || undefined,
      barcode: form.barcode.trim() || undefined,
      brand: form.brand.trim() || undefined,
      unit: form.unit.trim() || undefined,
      is_lot_controlled: form.is_lot_controlled,
      is_expiry_controlled: form.is_expiry_controlled,
      is_serial_controlled: form.is_serial_controlled,
      min_stock: toOptionalNumber(form.min_stock),
      max_stock: toOptionalNumber(form.max_stock),
      reorder_point: toOptionalNumber(form.reorder_point),
      reorder_qty: toOptionalNumber(form.reorder_qty),
      last_purchase_cost: toOptionalNumber(form.last_purchase_cost),
    }

    try {
      if (uuid) {
        await ticketTypeService.updateTicketType(uuid, payload, imageFile)
      } else {
        await ticketTypeService.createTicketType(payload, imageFile)
      }
      navigate('/tipos-de-ingresso')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o tipo de ingresso agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Tipos de ingresso"
      backTo="/tipos-de-ingresso"
      title={isEditMode ? 'Editar tipo de ingresso' : 'Novo tipo de ingresso'}
      subtitle={isEditMode ? 'Atualize os dados do tipo de ingresso.' : 'Cadastre um novo tipo de ingresso para um evento.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
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
        sx={{ mb: 2, maxWidth: { sm: 560 } }}
      />

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr)' }, mb: 2 }}>
        <TextField
          label="Nome"
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
          slotProps={{
            input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
            htmlInput: { min: 0, step: '0.01' },
          }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
        <TextField
          label="Qtd. disponível"
          type="number"
          placeholder="Ilimitado"
          value={form.quantity_available}
          onChange={(event) => updateField('quantity_available', event.target.value)}
          error={Boolean(fieldErrors.quantity_available)}
          helperText={fieldErrors.quantity_available?.[0]}
          slotProps={{ htmlInput: { min: 0, step: '1' } }}
        />
        <TextField
          label="Mín. por pedido"
          type="number"
          value={form.min_per_order}
          onChange={(event) => updateField('min_per_order', event.target.value)}
          error={Boolean(fieldErrors.min_per_order)}
          helperText={fieldErrors.min_per_order?.[0]}
          slotProps={{ htmlInput: { min: 1, step: '1' } }}
        />
        <TextField
          label="Máx. por pedido"
          type="number"
          value={form.max_per_order}
          onChange={(event) => updateField('max_per_order', event.target.value)}
          error={Boolean(fieldErrors.max_per_order)}
          helperText={fieldErrors.max_per_order?.[0]}
          slotProps={{ htmlInput: { min: 1, step: '1' } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
        <TextField
          label="Início das vendas"
          type="datetime-local"
          value={form.sales_start_at}
          onChange={(event) => updateField('sales_start_at', event.target.value)}
          error={Boolean(fieldErrors.sales_start_at)}
          helperText={fieldErrors.sales_start_at?.[0]}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="Fim das vendas"
          type="datetime-local"
          value={form.sales_end_at}
          onChange={(event) => updateField('sales_end_at', event.target.value)}
          error={Boolean(fieldErrors.sales_end_at)}
          helperText={fieldErrors.sales_end_at?.[0]}
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      <LocalAutocomplete
        label="Status"
        options={TICKET_TYPE_STATUS_OPTIONS}
        value={TICKET_TYPE_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
        onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as TicketTypeStatus)}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        error={Boolean(fieldErrors.status)}
        helperText={fieldErrors.status?.[0]}
        sx={{ mb: 2, maxWidth: { sm: 260 } }}
      />

      <TextField
        label="Descrição"
        value={form.description}
        onChange={(event) => updateField('description', event.target.value)}
        error={Boolean(fieldErrors.description)}
        helperText={fieldErrors.description?.[0]}
        fullWidth
        multiline
        minRows={2}
        sx={{ mb: 2 }}
      />

      <ImageUploadField label="Imagem" existingImageUrl={existingImageUrl} onFileSelected={setImageFile} />

      <Accordion variant="outlined" sx={{ mt: 2, ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}>
        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
          <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>Avançado (estoque legado)</Typography>
        </AccordionSummary>
        <AccordionDetails>
          <Stack spacing={2}>
            <Box sx={{ ...FORM_GRID_3_SX }}>
              <TextField
                label="SKU"
                value={form.sku}
                onChange={(event) => updateField('sku', event.target.value)}
                error={Boolean(fieldErrors.sku)}
                helperText={fieldErrors.sku?.[0]}
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />
              <TextField
                label="Código de barras"
                value={form.barcode}
                onChange={(event) => updateField('barcode', event.target.value)}
                error={Boolean(fieldErrors.barcode)}
                helperText={fieldErrors.barcode?.[0]}
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />
              <TextField
                label="Marca"
                value={form.brand}
                onChange={(event) => updateField('brand', event.target.value)}
                error={Boolean(fieldErrors.brand)}
                helperText={fieldErrors.brand?.[0]}
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />
            </Box>

            <TextField
              label="Unidade"
              placeholder="un, lote…"
              value={form.unit}
              onChange={(event) => updateField('unit', event.target.value)}
              error={Boolean(fieldErrors.unit)}
              helperText={fieldErrors.unit?.[0]}
              sx={{ maxWidth: { sm: 240 } }}
              slotProps={{ htmlInput: { maxLength: 50 } }}
            />

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_lot_controlled}
                    onChange={(event) => updateField('is_lot_controlled', event.target.checked)}
                  />
                }
                label="Controla lote"
              />
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_expiry_controlled}
                    onChange={(event) => updateField('is_expiry_controlled', event.target.checked)}
                  />
                }
                label="Controla validade"
              />
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_serial_controlled}
                    onChange={(event) => updateField('is_serial_controlled', event.target.checked)}
                  />
                }
                label="Controla série"
              />
            </Stack>

            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(4, minmax(0, 1fr))' }, gap: 2 }}>
              <TextField
                label="Disponibilidade mínima"
                type="number"
                value={form.min_stock}
                onChange={(event) => updateField('min_stock', event.target.value)}
                error={Boolean(fieldErrors.min_stock)}
                helperText={fieldErrors.min_stock?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Disponibilidade máxima"
                type="number"
                value={form.max_stock}
                onChange={(event) => updateField('max_stock', event.target.value)}
                error={Boolean(fieldErrors.max_stock)}
                helperText={fieldErrors.max_stock?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Ponto de reposição"
                type="number"
                value={form.reorder_point}
                onChange={(event) => updateField('reorder_point', event.target.value)}
                error={Boolean(fieldErrors.reorder_point)}
                helperText={fieldErrors.reorder_point?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Qtd. de reposição"
                type="number"
                value={form.reorder_qty}
                onChange={(event) => updateField('reorder_qty', event.target.value)}
                error={Boolean(fieldErrors.reorder_qty)}
                helperText={fieldErrors.reorder_qty?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
            </Box>

            <TextField
              label="Último custo de compra"
              type="number"
              value={form.last_purchase_cost}
              onChange={(event) => updateField('last_purchase_cost', event.target.value)}
              error={Boolean(fieldErrors.last_purchase_cost)}
              helperText={fieldErrors.last_purchase_cost?.[0]}
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
              sx={{ maxWidth: { sm: 240 } }}
            />
          </Stack>
        </AccordionDetails>
      </Accordion>
    </CrudFormShell>
  )
}
