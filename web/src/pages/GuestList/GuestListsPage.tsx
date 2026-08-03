import { Alert, Box, Button, Chip, Paper, Stack, TextField, Typography } from '@mui/material'
import CardGiftcardOutlinedIcon from '@mui/icons-material/CardGiftcardOutlined'
import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AsyncAutocomplete } from '../../components/crud/AsyncAutocomplete'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import * as eventService from '../../services/eventService'
import * as guestListService from '../../services/guestListService'
import * as ticketTypeService from '../../services/ticketTypeService'
import type { Event } from '../../types/event'
import type { TicketType } from '../../types/ticketType'
import type { GuestList } from '../../types/guestList'
import { getApiErrorMessage } from '../../types/api'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

/**
 * "Cortesias estruturadas" (roadmap Fase 4) — organizador cria uma lista
 * por evento + tipo de ingresso; cada convidado adicionado (na tela de
 * detalhe) recebe um link individual de autoatendimento (`/convite/:token`).
 */
export function GuestListsPage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const canCreate = hasPermission(ACCESS.eventsUpdate)

  const [guestLists, setGuestLists] = useState<GuestList[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [showForm, setShowForm] = useState(false)
  const [selectedEvent, setSelectedEvent] = useState<Event | null>(null)
  const [selectedTicketType, setSelectedTicketType] = useState<TicketType | null>(null)
  const [name, setName] = useState('')
  const [quantityPerEntry, setQuantityPerEntry] = useState('1')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const load = useCallback(() => {
    setIsLoading(true)
    setLoadError(null)
    guestListService
      .listGuestLists()
      .then((result) => setGuestLists(result.items))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as listas de convidados agora.')))
      .finally(() => setIsLoading(false))
  }, [])

  useEffect(load, [load])

  const fetchEvents = useCallback(
    (query: string) => eventService.listEvents({ name: query || undefined, per_page: 15, sort_by: 'starts_at', sort_dir: 'desc' }).then((r) => r.items),
    [],
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

  async function handleCreate() {
    if (!selectedEvent || !selectedTicketType || !name.trim()) return
    setFormError(null)
    setIsSubmitting(true)
    try {
      await guestListService.createGuestList({
        event_uuid: selectedEvent.uuid,
        ticket_type_uuid: selectedTicketType.uuid,
        name: name.trim(),
        quantity_per_entry: Number(quantityPerEntry) || 1,
      })
      setShowForm(false)
      setSelectedEvent(null)
      setSelectedTicketType(null)
      setName('')
      setQuantityPerEntry('1')
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível criar a lista agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudListPage
      title="Listas de convidados"
      subtitle="Cortesias estruturadas — cada convidado recebe um link individual pra resgatar o próprio ingresso."
      isLoading={isLoading}
      error={loadError}
      onRetry={load}
      isEmpty={guestLists.length === 0 && !showForm}
      emptyIcon={<CardGiftcardOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
      emptyTitle="Nenhuma lista de convidados"
      emptyDescription="Crie uma lista pra gerar links individuais de cortesia."
      canCreate={canCreate}
      createLabel="Nova lista"
      onCreate={() => setShowForm(true)}
    >
      {showForm && (
        <Paper elevation={0} sx={{ p: 2.5, mb: 2.5, ...ELEVATED_SURFACE_SX }}>
          <Typography sx={{ fontWeight: 700, mb: 1.5 }}>Nova lista de convidados</Typography>
          {formError && (
            <Alert severity="error" sx={{ mb: 1.5 }}>
              {formError}
            </Alert>
          )}
          <Stack spacing={2} sx={{ maxWidth: 480 }}>
            <AsyncAutocomplete
              label="Evento"
              value={selectedEvent}
              onChange={(value) => {
                setSelectedEvent(value)
                setSelectedTicketType(null)
              }}
              fetchOptions={fetchEvents}
              getOptionLabel={(event) => event.name}
              getOptionKey={(event) => event.uuid}
              required
            />
            <AsyncAutocomplete
              label="Tipo de ingresso"
              value={selectedTicketType}
              onChange={setSelectedTicketType}
              fetchOptions={fetchTicketTypes}
              getOptionLabel={(ticketType) => ticketType.name}
              getOptionKey={(ticketType) => ticketType.uuid}
              required
              disabled={!selectedEvent}
              helperText={!selectedEvent ? 'Escolha o evento primeiro' : undefined}
            />
            <TextField label="Nome da lista" value={name} onChange={(event) => setName(event.target.value)} required />
            <TextField
              label="Ingressos por convidado"
              type="number"
              value={quantityPerEntry}
              onChange={(event) => setQuantityPerEntry(event.target.value)}
              slotProps={{ htmlInput: { min: 1, max: 20 } }}
            />
            <Stack direction="row" spacing={1.5}>
              <Button
                variant="contained"
                disabled={isSubmitting || !selectedEvent || !selectedTicketType || !name.trim()}
                onClick={() => void handleCreate()}
                sx={{ minHeight: 44 }}
              >
                {isSubmitting ? 'Criando…' : 'Criar lista'}
              </Button>
              <Button color="inherit" disabled={isSubmitting} onClick={() => setShowForm(false)} sx={{ minHeight: 44 }}>
                Cancelar
              </Button>
            </Stack>
          </Stack>
        </Paper>
      )}

      <Stack spacing={1.5}>
        {guestLists.map((guestList) => (
          <Paper
            key={guestList.uuid}
            elevation={0}
            sx={{ p: 2, ...SOFT_PANEL_SX, cursor: 'pointer' }}
            onClick={() => navigate(`/listas-de-convidados/${guestList.uuid}`)}
          >
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
              <Box>
                <Typography sx={{ fontWeight: 700 }}>{guestList.name}</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  {guestList.event.name} · {guestList.ticket_type.name}
                </Typography>
              </Box>
              <Chip
                label={`${guestList.redeemed_entries_count ?? 0}/${guestList.entries_count ?? 0} resgatados`}
                size="small"
                color={guestList.entries_count && guestList.redeemed_entries_count === guestList.entries_count ? 'success' : 'default'}
              />
            </Stack>
          </Paper>
        ))}
      </Stack>
    </CrudListPage>
  )
}
