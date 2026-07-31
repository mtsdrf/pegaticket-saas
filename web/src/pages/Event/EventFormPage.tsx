import { Box, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { ImageUploadField } from '../../components/shared/ImageUploadField'
import * as eventCategoryService from '../../services/eventCategoryService'
import * as eventService from '../../services/eventService'
import * as venueService from '../../services/venueService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { EVENT_STATUS_OPTIONS, EVENT_TYPE_OPTIONS, EVENT_VISIBILITY_OPTIONS, type EventStatus, type EventType, type EventVisibility } from '../../types/event'

interface EventFormState {
  name: string
  slug: string
  event_category_uuid: string
  venue_uuid: string
  description_short: string
  description_full: string
  type: EventType
  location_name: string
  location_address: string
  starts_at: string
  ends_at: string
  visibility: EventVisibility
  status: EventStatus
}

const EMPTY_FORM: EventFormState = {
  name: '',
  slug: '',
  event_category_uuid: '',
  venue_uuid: '',
  description_short: '',
  description_full: '',
  type: 'ingresso',
  location_name: '',
  location_address: '',
  starts_at: '',
  ends_at: '',
  visibility: 'public',
  status: 'rascunho',
}

/** Backend guarda `datetime` (`YYYY-MM-DD HH:mm:ss`) — `<input type="datetime-local">` usa `YYYY-MM-DDTHH:mm`. */
function toDateTimeLocal(value: string | null | undefined): string {
  if (!value) return ''
  return value.replace(' ', 'T').slice(0, 16)
}

function slugify(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
}

export function EventFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [form, setForm] = useState<EventFormState>(EMPTY_FORM)
  const [slugTouched, setSlugTouched] = useState(false)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null)
  const [categoryOptions, setCategoryOptions] = useState<{ value: string; label: string }[]>([])
  const [venueOptions, setVenueOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingForm(true)
    setLoadError(null)

    const categoriesPromise = eventCategoryService.listEventCategories({ per_page: 100 })
    const venuesPromise = venueService.listVenues({ per_page: 100, is_active: true })
    const recordPromise = uuid ? eventService.getEvent(uuid) : Promise.resolve(null)

    Promise.all([categoriesPromise, venuesPromise, recordPromise])
      .then(([categories, venues, record]) => {
        setCategoryOptions(categories.items.map((category) => ({ value: category.uuid, label: category.name })))
        setVenueOptions(
          venues.items
            .filter((venue) => venue.published_map_version)
            .map((venue) => ({
              value: venue.uuid,
              label: `${venue.name} (mapa v${venue.published_map_version?.version_number})`,
            })),
        )

        if (record) {
          setSlugTouched(true)
          setForm({
            name: record.name,
            slug: record.slug,
            event_category_uuid: record.category?.uuid ?? '',
            venue_uuid: record.venue?.uuid ?? '',
            description_short: record.description_short ?? '',
            description_full: record.description_full ?? '',
            type: record.type,
            location_name: record.location_name ?? '',
            location_address: record.location_address ?? '',
            starts_at: toDateTimeLocal(record.starts_at),
            ends_at: toDateTimeLocal(record.ends_at),
            visibility: record.visibility,
            status: record.status,
          })
          setExistingImageUrl(record.cover_image_url)
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do evento agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof EventFormState>(key: K, value: EventFormState[K]) {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'name' && !slugTouched) {
        next.slug = slugify(String(value))
      }
      return next
    })
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      event_category_uuid: form.event_category_uuid || null,
      venue_uuid: form.venue_uuid || null,
      description_short: form.description_short.trim() || undefined,
      description_full: form.description_full.trim() || undefined,
      type: form.type,
      location_name: form.location_name.trim() || undefined,
      location_address: form.location_address.trim() || undefined,
      starts_at: form.starts_at ? form.starts_at.replace('T', ' ') : '',
      ends_at: form.ends_at ? form.ends_at.replace('T', ' ') : '',
      visibility: form.visibility,
      status: form.status,
    }

    try {
      if (uuid) {
        await eventService.updateEvent(uuid, payload, imageFile)
      } else {
        await eventService.createEvent(payload, imageFile)
      }
      navigate('/eventos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o evento agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Eventos"
      backTo="/eventos"
      title={isEditMode ? 'Editar evento' : 'Novo evento'}
      subtitle={isEditMode ? 'Atualize os dados do evento.' : 'Cadastre um novo evento.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
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
          label="Slug"
          value={form.slug}
          onChange={(event) => {
            setSlugTouched(true)
            updateField('slug', slugify(event.target.value))
          }}
          error={Boolean(fieldErrors.slug)}
          helperText={fieldErrors.slug?.[0] ?? 'Usado na URL pública do evento.'}
          required
        />
      </Box>

      <LocalAutocomplete
        label="Categoria"
        options={categoryOptions}
        value={categoryOptions.find((option) => option.value === form.event_category_uuid) ?? null}
        onChange={(option) => updateField('event_category_uuid', option?.value ?? '')}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        fullWidth
        error={Boolean(fieldErrors.event_category_uuid)}
        helperText={fieldErrors.event_category_uuid?.[0]}
        sx={{ mb: 2, maxWidth: { sm: 400 } }}
      />

      <LocalAutocomplete
        label="Local com mapa publicado"
        options={venueOptions}
        value={venueOptions.find((option) => option.value === form.venue_uuid) ?? null}
        onChange={(option) => updateField('venue_uuid', option?.value ?? '')}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        fullWidth
        error={Boolean(fieldErrors.venue_uuid)}
        helperText={
          fieldErrors.venue_uuid?.[0] ??
          (venueOptions.length === 0
            ? 'Nenhum local ativo com mapa publicado disponível ainda.'
            : 'Opcional. O evento usará a última versão publicada do mapa deste local.')
        }
        sx={{ mb: 2, maxWidth: { sm: 520 } }}
      />

      <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
        <LocalAutocomplete
          label="Tipo"
          options={EVENT_TYPE_OPTIONS}
          value={EVENT_TYPE_OPTIONS.find((option) => option.value === form.type) ?? null}
          onChange={(option) => updateField('type', (option?.value ?? 'ingresso') as EventType)}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          error={Boolean(fieldErrors.type)}
          helperText={fieldErrors.type?.[0]}
        />
        <LocalAutocomplete
          label="Visibilidade"
          options={EVENT_VISIBILITY_OPTIONS}
          value={EVENT_VISIBILITY_OPTIONS.find((option) => option.value === form.visibility) ?? null}
          onChange={(option) => updateField('visibility', (option?.value ?? 'public') as EventVisibility)}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          error={Boolean(fieldErrors.visibility)}
          helperText={fieldErrors.visibility?.[0]}
        />
        <LocalAutocomplete
          label="Status"
          options={EVENT_STATUS_OPTIONS}
          value={EVENT_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
          onChange={(option) => updateField('status', (option?.value ?? 'rascunho') as EventStatus)}
          getOptionLabel={(option) => option.label}
          getOptionKey={(option) => option.value}
          error={Boolean(fieldErrors.status)}
          helperText={fieldErrors.status?.[0]}
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
          required
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="Término"
          type="datetime-local"
          value={form.ends_at}
          onChange={(event) => updateField('ends_at', event.target.value)}
          error={Boolean(fieldErrors.ends_at)}
          helperText={fieldErrors.ends_at?.[0]}
          required
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
        <TextField
          label="Local"
          value={form.location_name}
          onChange={(event) => updateField('location_name', event.target.value)}
          error={Boolean(fieldErrors.location_name)}
          helperText={fieldErrors.location_name?.[0]}
        />
        <TextField
          label="Endereço do local"
          value={form.location_address}
          onChange={(event) => updateField('location_address', event.target.value)}
          error={Boolean(fieldErrors.location_address)}
          helperText={fieldErrors.location_address?.[0]}
        />
      </Box>

      <TextField
        label="Descrição curta"
        value={form.description_short}
        onChange={(event) => updateField('description_short', event.target.value)}
        error={Boolean(fieldErrors.description_short)}
        helperText={fieldErrors.description_short?.[0]}
        fullWidth
        sx={{ mb: 2 }}
        slotProps={{ htmlInput: { maxLength: 255 } }}
      />

      <TextField
        label="Descrição completa"
        value={form.description_full}
        onChange={(event) => updateField('description_full', event.target.value)}
        error={Boolean(fieldErrors.description_full)}
        helperText={fieldErrors.description_full?.[0]}
        fullWidth
        multiline
        minRows={3}
        sx={{ mb: 2 }}
      />

      <ImageUploadField label="Imagem de capa" existingImageUrl={existingImageUrl} onFileSelected={setImageFile} />
    </CrudFormShell>
  )
}
