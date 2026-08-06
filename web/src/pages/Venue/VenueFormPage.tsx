import { Box, FormControlLabel, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { FormSection } from '../../components/form/FormSection'
import { sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import { ImageUploadField } from '../../components/shared/ImageUploadField'
import * as venueService from '../../services/venueService'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

interface VenueFormState {
  name: string
  width: string
  height: string
  is_active: boolean
}

const EMPTY_FORM: VenueFormState = {
  name: '',
  width: '',
  height: '',
  is_active: true,
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

export function VenueFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const [form, setForm] = useState<VenueFormState>(EMPTY_FORM)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null)
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

    venueService
      .getVenue(uuid)
      .then((record) => {
        setForm({
          name: record.name,
          width: record.width === null ? '' : String(record.width),
          height: record.height === null ? '' : String(record.height),
          is_active: record.is_active,
        })
        setExistingImageUrl(record.background_image_url)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do local agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof VenueFormState>(key: K, value: VenueFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      width: toOptionalNumber(form.width),
      height: toOptionalNumber(form.height),
      is_active: form.is_active,
    }

    try {
      if (uuid) {
        await venueService.updateVenue(uuid, payload, imageFile)
      } else {
        await venueService.createVenue(payload, imageFile)
      }

      navigate('/locais')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o local agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Locais"
      backTo="/locais"
      title={isEditMode ? 'Editar local' : 'Novo local'}
      subtitle={isEditMode ? 'Atualize o local e o mapa base do evento.' : 'Cadastre um novo local e prepare o mapa base do evento.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Dados principais" description="Defina o nome do local e o status geral de uso na operação.">
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Nome"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0]}
            required
          />
          <FormControlLabel
            control={<Switch checked={form.is_active} onChange={(event) => updateField('is_active', event.target.checked)} />}
            label="Local ativo"
            sx={{ minHeight: 56, alignItems: 'center' }}
          />
        </Box>
      </FormSection>

      <FormSection title="Base do mapa" description="Informe as dimensões iniciais do mapa e, se desejar, envie uma imagem de fundo.">
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Largura do mapa"
            type="number"
            value={form.width}
            onChange={(event) => updateField('width', sanitizePositiveIntegerInput(event.target.value))}
            error={Boolean(fieldErrors.width)}
            helperText={fieldErrors.width?.[0] ?? 'Opcional. Base em pixels para o mapa.'}
            slotProps={{ htmlInput: { min: 1, step: '1' } }}
          />
          <TextField
            label="Altura do mapa"
            type="number"
            value={form.height}
            onChange={(event) => updateField('height', sanitizePositiveIntegerInput(event.target.value))}
            error={Boolean(fieldErrors.height)}
            helperText={fieldErrors.height?.[0] ?? 'Opcional. Base em pixels para o mapa.'}
            slotProps={{ htmlInput: { min: 1, step: '1' } }}
          />
        </Box>

        <ImageUploadField
          label="Imagem de fundo do mapa"
          existingImageUrl={existingImageUrl}
          onFileSelected={setImageFile}
        />
      </FormSection>
    </CrudFormShell>
  )
}
