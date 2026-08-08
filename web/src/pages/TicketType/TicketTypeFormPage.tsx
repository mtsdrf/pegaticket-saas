import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Box,
  Button,
  Checkbox,
  FormControlLabel,
  FormHelperText,
  InputAdornment,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import { DateTimePickerField } from '../../components/form/DateTimePickerField'
import { FormSection } from '../../components/form/FormSection'
import { ImageUploadField } from '../../components/shared/ImageUploadField'
import { TicketFeeSimulationDialog } from '../../components/ticketType/TicketFeeSimulationDialog'
import * as eventService from '../../services/eventService'
import * as ticketFeeService from '../../services/ticketFeeService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { UI_RADIUS, FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Event } from '../../types/event'
import { computeTicketFeePreview, type TicketFeePayer, type TicketFeeRule } from '../../types/ticketFee'
import { TICKET_TYPE_STATUS_OPTIONS, type TicketTypeStatus } from '../../types/ticketType'
import { formatCurrency } from '../../utils/format'

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
  reentry_enabled: 'inherit' | 'enabled' | 'disabled'
  max_reentries: string
  reentry_cooldown_minutes: string
  sku: string
  barcode: string
  brand: string
  unit: string
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
  reentry_enabled: 'inherit',
  max_reentries: '',
  reentry_cooldown_minutes: '',
  sku: '',
  barcode: '',
  brand: '',
  unit: '',
  last_purchase_cost: '',
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

/** Backend guarda `datetime` (`YYYY-MM-DD HH:mm:ss`) — o picker trabalha em `YYYY-MM-DDTHH:mm`. */
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
  const [events, setEvents] = useState<Event[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [feeRule, setFeeRule] = useState<TicketFeeRule | null>(null)
  const [isSimulationOpen, setIsSimulationOpen] = useState(false)

  const eventOptions = useMemo(() => events.map((event) => ({ value: event.uuid, label: event.name })), [events])
  const selectedEvent = useMemo(() => events.find((event) => event.uuid === form.event_uuid) ?? null, [events, form.event_uuid])
  /** Se o evento selecionado ainda não foi carregado na lista (ex.: fora dos primeiros 100), assume `buyer` com nota explícita — nunca inventa um seletor aqui. */
  const feePayer: TicketFeePayer = selectedEvent?.service_fee_payer ?? 'buyer'
  const feePayerIsAssumed = selectedEvent === null

  const feePreview = useMemo(() => {
    if (!feeRule) return null
    const priceReais = Number(form.price)
    if (!Number.isFinite(priceReais) || priceReais <= 0) return null
    return { priceReais, ...computeTicketFeePreview(priceReais, feeRule) }
  }, [feeRule, form.price])

  useEffect(() => {
    ticketFeeService.getTicketFeeRule().then(setFeeRule).catch(() => setFeeRule(null))
  }, [])

  useEffect(() => {
    setIsLoadingForm(true)
    setLoadError(null)

    const eventsPromise = eventService.listEvents({ per_page: 100 })
    const recordPromise = uuid ? ticketTypeService.getTicketType(uuid) : Promise.resolve(null)

    Promise.all([eventsPromise, recordPromise])
      .then(([eventsResult, record]) => {
        setEvents(eventsResult.items)

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
            reentry_enabled: record.reentry_enabled === null ? 'inherit' : record.reentry_enabled ? 'enabled' : 'disabled',
            max_reentries: record.max_reentries === null ? '' : String(record.max_reentries),
            reentry_cooldown_minutes:
              record.reentry_cooldown_minutes === null ? '' : String(record.reentry_cooldown_minutes),
            sku: record.sku ?? '',
            barcode: record.barcode ?? '',
            brand: record.brand ?? '',
            unit: record.unit ?? '',
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
      reentry_enabled: form.reentry_enabled === 'inherit' ? null : form.reentry_enabled === 'enabled',
      max_reentries: form.max_reentries.trim() ? Number(form.max_reentries) : null,
      reentry_cooldown_minutes: form.reentry_cooldown_minutes.trim() ? Number(form.reentry_cooldown_minutes) : null,
      sku: form.sku.trim() || undefined,
      barcode: form.barcode.trim() || undefined,
      brand: form.brand.trim() || undefined,
      unit: form.unit.trim() || undefined,
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
      <FormSection title="Dados principais" description="Associe o ingresso ao evento e defina os campos comerciais centrais.">
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

      <Box
        sx={{
          border: '1px solid var(--pt-divider)',
          borderRadius: UI_RADIUS.md,
          p: 2,
          background: 'var(--pt-form-section-bg)',
          display: 'flex',
          flexDirection: 'column',
          gap: 1,
        }}
      >
        {!feeRule ? (
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Carregando taxa de serviço vigente…
          </Typography>
        ) : !feePreview ? (
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Informe o preço para ver o resumo da taxa PegaTicket ({feeRule.percentage}%, mínimo{' '}
            {formatCurrency(feeRule.minimum_amount)}).
          </Typography>
        ) : (
          <>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5 }}>
              <span>Preço do ingresso</span>
              <strong>{formatCurrency(feePreview.priceReais)}</strong>
            </Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5 }}>
              <span>Taxa PegaTicket ({feeRule.percentage}%)</span>
              <strong>{formatCurrency(feePreview.feeAmount)}</strong>
            </Box>
            <Box sx={{ borderTop: '1px dashed var(--pt-divider)', my: 0.5 }} />
            <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5 }}>
              <span>Comprador pagará</span>
              <strong>
                {formatCurrency(feePayer === 'buyer' ? feePreview.priceReais + feePreview.feeAmount : feePreview.priceReais)}
              </strong>
            </Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5 }}>
              <span>Você receberá aproximadamente</span>
              <strong>
                {formatCurrency(feePayer === 'buyer' ? feePreview.priceReais : feePreview.priceReais - feePreview.feeAmount)}
              </strong>
            </Box>
            {feePreview.isMinimumApplied && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Taxa mínima de {formatCurrency(feeRule.minimum_amount)} aplicada.
              </Typography>
            )}
            <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
              {feePayerIsAssumed
                ? 'Quem paga a taxa é definido no evento — exibindo cenário padrão (comprador paga) até o evento ser identificado.'
                : `Conforme configuração do evento: ${feePayer === 'buyer' ? 'comprador paga a taxa' : 'produtor paga a taxa'}.`}
            </Typography>
          </>
        )}

        <Button
          onClick={() => setIsSimulationOpen(true)}
          size="small"
          variant="outlined"
          disabled={!feeRule}
          sx={{ alignSelf: 'flex-start', mt: 0.5 }}
        >
          Simular recebimento
        </Button>
      </Box>
      </FormSection>

      <TicketFeeSimulationDialog
        open={isSimulationOpen}
        onClose={() => setIsSimulationOpen(false)}
        rule={feeRule}
        initialPriceReais={Number(form.price) || 0}
        initialFeePayer={feePayer}
        onUsePrice={(priceReais) => updateField('price', String(priceReais))}
      />

      <FormSection title="Regras de venda" description="Controle capacidade, limites por pedido, agenda de venda e status operacional.">
      <Box sx={FORM_GRID_3_SX}>
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
          label="Mín. por compra"
          type="number"
          value={form.min_per_order}
          onChange={(event) => updateField('min_per_order', sanitizePositiveIntegerInput(event.target.value))}
          error={Boolean(fieldErrors.min_per_order)}
          helperText={fieldErrors.min_per_order?.[0]}
          slotProps={{ htmlInput: { min: 1, step: '1' } }}
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

      <LocalAutocomplete
        label="Status"
        options={TICKET_TYPE_STATUS_OPTIONS}
        value={TICKET_TYPE_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
        onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as TicketTypeStatus)}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        error={Boolean(fieldErrors.status)}
        helperText={fieldErrors.status?.[0]}
      />
      </FormSection>

      <FormSection title="Override de reentrada" description="O tipo de ingresso pode herdar a política do evento ou sobrescrevê-la.">
        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
          Este tipo de ingresso pode herdar a regra do evento ou sobrescreve-la.
        </Typography>

        <Stack spacing={1.5}>
          <FormControlLabel
            control={
              <Checkbox
                checked={form.reentry_enabled === 'inherit'}
                onChange={(event) => {
                  if (event.target.checked) updateField('reentry_enabled', 'inherit')
                }}
              />
            }
            label="Herdar politica de reentrada do evento"
          />
          <FormControlLabel
            control={
              <Checkbox
                checked={form.reentry_enabled === 'enabled'}
                onChange={(event) => {
                  updateField('reentry_enabled', event.target.checked ? 'enabled' : 'inherit')
                }}
              />
            }
            label="Permitir reentrada neste tipo de ingresso"
          />
          <FormControlLabel
            control={
              <Checkbox
                checked={form.reentry_enabled === 'disabled'}
                onChange={(event) => {
                  updateField('reentry_enabled', event.target.checked ? 'disabled' : 'inherit')
                }}
              />
            }
            label="Bloquear reentrada neste tipo de ingresso"
          />
        </Stack>

        {fieldErrors.reentry_enabled?.[0] && <FormHelperText error sx={{ mt: 1 }}>{fieldErrors.reentry_enabled[0]}</FormHelperText>}

        {form.reentry_enabled === 'enabled' && (
          <Box sx={{ ...FORM_GRID_2_SX, mt: 1.5 }}>
            <TextField
              label="Limite de reentradas"
              type="number"
              placeholder="Sem limite"
              value={form.max_reentries}
              onChange={(event) => updateField('max_reentries', sanitizePositiveIntegerInput(event.target.value))}
              error={Boolean(fieldErrors.max_reentries)}
              helperText={fieldErrors.max_reentries?.[0] ?? 'Se vazio, usa sem limite dentro desta regra.'}
              slotProps={{ htmlInput: { min: 0, step: '1' } }}
            />
            <TextField
              label="Intervalo minimo (min)"
              type="number"
              placeholder="Sem intervalo"
              value={form.reentry_cooldown_minutes}
              onChange={(event) => updateField('reentry_cooldown_minutes', sanitizePositiveIntegerInput(event.target.value))}
              error={Boolean(fieldErrors.reentry_cooldown_minutes)}
              helperText={fieldErrors.reentry_cooldown_minutes?.[0] ?? 'Tempo minimo entre acessos autorizados.'}
              slotProps={{ htmlInput: { min: 0, step: '1' } }}
            />
          </Box>
        )}
      </FormSection>

      <FormSection title="Descrição e mídia" description="Adicione contexto comercial para facilitar a venda e a comunicação.">
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

      <ImageUploadField label="Imagem" existingImageUrl={existingImageUrl} onFileSelected={setImageFile} />
      </FormSection>

      <Accordion variant="outlined" sx={{ ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}>
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
              slotProps={{ htmlInput: { maxLength: 50 } }}
            />

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
            />
          </Stack>
        </AccordionDetails>
      </Accordion>
    </CrudFormShell>
  )
}
