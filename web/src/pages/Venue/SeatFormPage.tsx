import { Box, FormControlLabel, Switch, TextField } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { FormSection } from '../../components/form/FormSection'
import { sanitizePositiveIntegerInput } from '../../components/form/fieldHelpers'
import * as seatService from '../../services/seatService'
import * as venueService from '../../services/venueService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { SEAT_KIND_OPTIONS, SEAT_STATUS_OPTIONS, type SeatKind, type SeatStatus } from '../../types/venue'

interface SeatFormState {
  sector_name: string
  label: string
  kind: SeatKind
  capacity: string
  pos_x: string
  pos_y: string
  width: string
  height: string
  is_accessible: boolean
  status: SeatStatus
}

const EMPTY_FORM: SeatFormState = {
  sector_name: '',
  label: '',
  kind: 'assento',
  capacity: '1',
  pos_x: '',
  pos_y: '',
  width: '',
  height: '',
  is_accessible: false,
  status: 'disponivel',
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

export function SeatFormPage() {
  const navigate = useNavigate()
  const { venueUuid = '', seatUuid } = useParams<{ venueUuid: string; seatUuid?: string }>()
  const isEditMode = Boolean(seatUuid)
  const [venueName, setVenueName] = useState('Local')
  const [form, setForm] = useState<SeatFormState>(EMPTY_FORM)
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    const venuePromise = venueService.getVenue(venueUuid)
    const seatPromise = seatUuid ? seatService.getSeat(venueUuid, seatUuid) : Promise.resolve(null)

    Promise.all([venuePromise, seatPromise])
      .then(([venue, seat]) => {
        setVenueName(venue.name)

        if (seat) {
          setForm({
            sector_name: seat.sector_name ?? '',
            label: seat.label,
            kind: seat.kind,
            capacity: String(seat.capacity),
            pos_x: String(seat.pos_x),
            pos_y: String(seat.pos_y),
            width: seat.width !== null ? String(seat.width) : '',
            height: seat.height !== null ? String(seat.height) : '',
            is_accessible: seat.is_accessible,
            status: seat.status,
          })
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do assento agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [seatUuid, venueUuid])

  function updateField<K extends keyof SeatFormState>(key: K, value: SeatFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      sector_name: form.sector_name.trim() || null,
      label: form.label.trim(),
      kind: form.kind,
      capacity: toOptionalNumber(form.capacity),
      pos_x: toOptionalNumber(form.pos_x),
      pos_y: toOptionalNumber(form.pos_y),
      width: toOptionalNumber(form.width),
      height: toOptionalNumber(form.height),
      is_accessible: form.is_accessible,
      status: form.status,
    }

    try {
      if (seatUuid) {
        await seatService.updateSeat(venueUuid, seatUuid, payload)
      } else {
        await seatService.createSeat(venueUuid, payload)
      }

      navigate(`/locais/${venueUuid}/assentos`)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o assento agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Mapa"
      backTo={`/locais/${venueUuid}/assentos`}
      title={isEditMode ? 'Editar assento' : 'Novo assento'}
      subtitle={`${isEditMode ? 'Atualize' : 'Cadastre'} um ponto do mapa de ${venueName}.`}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <FormSection title="Identificação do ponto" description="Defina código, setor e o tipo estrutural do item dentro do mapa.">
        <Box sx={FORM_GRID_3_SX}>
          <TextField
            label="Código"
            value={form.label}
            onChange={(event) => updateField('label', event.target.value)}
            error={Boolean(fieldErrors.label)}
            helperText={fieldErrors.label?.[0]}
            required
          />
          <TextField
            label="Setor"
            value={form.sector_name}
            onChange={(event) => updateField('sector_name', event.target.value)}
            error={Boolean(fieldErrors.sector_name)}
            helperText={fieldErrors.sector_name?.[0]}
          />
          <LocalAutocomplete
            label="Tipo"
            options={SEAT_KIND_OPTIONS}
            value={SEAT_KIND_OPTIONS.find((option) => option.value === form.kind) ?? null}
            onChange={(option) => updateField('kind', (option?.value ?? 'assento') as SeatKind)}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            error={Boolean(fieldErrors.kind)}
            helperText={fieldErrors.kind?.[0]}
          />
        </Box>
      </FormSection>

      <FormSection title="Geometria e operação" description="Ajuste capacidade, posição no plano e disponibilidade operacional do ponto.">
        <Box sx={FORM_GRID_3_SX}>
          <TextField
            label="Capacidade"
            type="number"
            value={form.capacity}
            onChange={(event) => updateField('capacity', sanitizePositiveIntegerInput(event.target.value))}
            error={Boolean(fieldErrors.capacity)}
            helperText={fieldErrors.capacity?.[0]}
            slotProps={{ htmlInput: { min: 1, step: '1' } }}
          />
          <TextField
            label="Posição X"
            type="number"
            value={form.pos_x}
            onChange={(event) => updateField('pos_x', event.target.value)}
            error={Boolean(fieldErrors.pos_x)}
            helperText={fieldErrors.pos_x?.[0]}
            slotProps={{ htmlInput: { step: '0.01' } }}
          />
          <TextField
            label="Posição Y"
            type="number"
            value={form.pos_y}
            onChange={(event) => updateField('pos_y', event.target.value)}
            error={Boolean(fieldErrors.pos_y)}
            helperText={fieldErrors.pos_y?.[0]}
            slotProps={{ htmlInput: { step: '0.01' } }}
          />
        </Box>

        <Box sx={FORM_GRID_3_SX}>
          <TextField
            label="Largura personalizada"
            type="number"
            value={form.width}
            onChange={(event) => updateField('width', event.target.value)}
            error={Boolean(fieldErrors.width)}
            helperText={fieldErrors.width?.[0] ?? 'Deixe em branco para usar o padrão do tipo.'}
            slotProps={{ htmlInput: { min: 0.01, step: '0.01' } }}
          />
          <TextField
            label="Altura personalizada"
            type="number"
            value={form.height}
            onChange={(event) => updateField('height', event.target.value)}
            error={Boolean(fieldErrors.height)}
            helperText={fieldErrors.height?.[0] ?? 'Deixe em branco para usar o padrão do tipo.'}
            slotProps={{ htmlInput: { min: 0.01, step: '0.01' } }}
          />
          <Box />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <LocalAutocomplete
            label="Status"
            options={SEAT_STATUS_OPTIONS}
            value={SEAT_STATUS_OPTIONS.find((option) => option.value === form.status) ?? null}
            onChange={(option) => updateField('status', (option?.value ?? 'disponivel') as SeatStatus)}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            error={Boolean(fieldErrors.status)}
            helperText={fieldErrors.status?.[0]}
          />
          <FormControlLabel
            control={<Switch checked={form.is_accessible} onChange={(event) => updateField('is_accessible', event.target.checked)} />}
            label="Acessível"
            sx={{ minHeight: 56, alignItems: 'center' }}
          />
        </Box>
      </FormSection>
    </CrudFormShell>
  )
}
