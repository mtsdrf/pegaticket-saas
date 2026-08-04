import { Alert, Autocomplete, Box, Button, Chip, IconButton, Paper, Stack, Switch, TextField, Tooltip, Typography } from '@mui/material'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import MeetingRoomOutlinedIcon from '@mui/icons-material/MeetingRoomOutlined'
import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import * as eventService from '../../services/eventService'
import * as eventGateService from '../../services/eventGateService'
import * as ticketTypeService from '../../services/ticketTypeService'
import type { EventGate } from '../../types/eventGate'
import type { TicketType } from '../../types/ticketType'
import { getApiErrorMessage } from '../../types/api'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

/**
 * Portarias/postos de acesso do evento — cada portaria pode restringir quais
 * tipos de ingresso ela aceita (lista vazia = aceita qualquer um, portão
 * "aberto"). Usada como sugestão na tela de check-in (`gateName`), mas o
 * cadastro aqui é opcional: o operador continua podendo digitar um valor
 * livre no check-in mesmo sem nenhuma portaria formal cadastrada.
 */
export function EventGateListPage() {
  const { eventUuid = '' } = useParams<{ eventUuid: string }>()
  const { hasPermission } = useAuth()
  const canCreate = hasPermission(ACCESS.eventGatesCreate)
  const canUpdate = hasPermission(ACCESS.eventGatesUpdate)
  const canDelete = hasPermission(ACCESS.eventGatesDelete)

  const [eventName, setEventName] = useState('Evento')
  const [gates, setGates] = useState<EventGate[]>([])
  const [ticketTypes, setTicketTypes] = useState<TicketType[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [showForm, setShowForm] = useState(false)
  const [editingUuid, setEditingUuid] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [allowedTicketTypes, setAllowedTicketTypes] = useState<TicketType[]>([])
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<EventGate | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const load = useCallback(() => {
    if (!eventUuid) return
    setIsLoading(true)
    setLoadError(null)

    Promise.all([
      eventService.getEvent(eventUuid),
      eventGateService.listEventGates(eventUuid),
      ticketTypeService.listTicketTypes({ event_uuid: eventUuid, per_page: 100, sort_by: 'name', sort_dir: 'asc' }),
    ])
      .then(([event, gateResult, ticketTypeResult]) => {
        setEventName(event.name)
        setGates(gateResult.items)
        setTicketTypes(ticketTypeResult.items)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as portarias agora.')))
      .finally(() => setIsLoading(false))
  }, [eventUuid])

  useEffect(load, [load])

  function openCreateForm() {
    setEditingUuid(null)
    setName('')
    setIsActive(true)
    setAllowedTicketTypes([])
    setFormError(null)
    setShowForm(true)
  }

  function openEditForm(gate: EventGate) {
    setEditingUuid(gate.uuid)
    setName(gate.name)
    setIsActive(gate.is_active)
    setAllowedTicketTypes(
      ticketTypes.filter((ticketType) => gate.allowed_ticket_types.some((allowed) => allowed.uuid === ticketType.uuid)),
    )
    setFormError(null)
    setShowForm(true)
  }

  async function handleSubmit() {
    if (!name.trim()) return
    setFormError(null)
    setIsSubmitting(true)

    const payload = {
      name: name.trim(),
      is_active: isActive,
      ticket_type_uuids: allowedTicketTypes.map((ticketType) => ticketType.uuid),
    }

    try {
      if (editingUuid) {
        await eventGateService.updateEventGate(eventUuid, editingUuid, payload)
      } else {
        await eventGateService.createEventGate(eventUuid, payload)
      }
      setShowForm(false)
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a portaria agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await eventGateService.deleteEventGate(eventUuid, deleteTarget.uuid)
      setDeleteTarget(null)
      load()
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível desativar a portaria agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <>
      <CrudListPage
        title={`Portarias de ${eventName}`}
        subtitle="Cadastre os pontos de acesso do evento e restrinja quais tipos de ingresso cada um aceita."
        isLoading={isLoading}
        error={loadError}
        onRetry={load}
        isEmpty={gates.length === 0 && !showForm}
        emptyIcon={<MeetingRoomOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
        emptyTitle="Nenhuma portaria cadastrada"
        emptyDescription="Sem portarias cadastradas, o check-in aceita qualquer valor digitado livremente no campo de portão."
        canCreate={canCreate}
        createLabel="Nova portaria"
        onCreate={openCreateForm}
        breadcrumbs={[{ label: 'Eventos', to: '/eventos' }, { label: eventName }]}
      >
        {showForm && (
          <Paper elevation={0} sx={{ p: 2.5, mb: 2.5, ...ELEVATED_SURFACE_SX }}>
            <Typography sx={{ fontWeight: 700, mb: 1.5 }}>{editingUuid ? 'Editar portaria' : 'Nova portaria'}</Typography>
            {formError && (
              <Alert severity="error" sx={{ mb: 1.5 }}>
                {formError}
              </Alert>
            )}
            <Stack spacing={2} sx={{ maxWidth: 520 }}>
              <TextField label="Nome da portaria" value={name} onChange={(event) => setName(event.target.value)} required />
              <Autocomplete
                multiple
                size="small"
                options={ticketTypes}
                value={allowedTicketTypes}
                onChange={(_event, value) => setAllowedTicketTypes(value)}
                getOptionLabel={(ticketType) => ticketType.name}
                isOptionEqualToValue={(option, val) => option.uuid === val.uuid}
                noOptionsText="Nenhum tipo de ingresso cadastrado"
                renderValue={(value, getItemProps) =>
                  value.map((ticketType, index) => {
                    const { key, ...itemProps } = getItemProps({ index })
                    return <Chip key={key} size="small" label={ticketType.name} {...itemProps} />
                  })
                }
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label="Tipos de ingresso permitidos"
                    placeholder={allowedTicketTypes.length === 0 ? 'Vazio = aceita qualquer tipo' : undefined}
                    helperText="Deixe vazio para a portaria aceitar qualquer tipo de ingresso."
                  />
                )}
              />
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />
                <Typography sx={{ fontSize: 14 }}>Portaria ativa</Typography>
              </Stack>
              <Stack direction="row" spacing={1.5}>
                <Button
                  variant="contained"
                  disabled={isSubmitting || !name.trim()}
                  onClick={() => void handleSubmit()}
                  sx={{ minHeight: 44 }}
                >
                  {isSubmitting ? 'Salvando…' : editingUuid ? 'Salvar alterações' : 'Criar portaria'}
                </Button>
                <Button color="inherit" disabled={isSubmitting} onClick={() => setShowForm(false)} sx={{ minHeight: 44 }}>
                  Cancelar
                </Button>
              </Stack>
            </Stack>
          </Paper>
        )}

        <Stack spacing={1.5}>
          {gates.map((gate) => (
            <Paper key={gate.uuid} elevation={0} sx={{ p: 2, ...SOFT_PANEL_SX }}>
              <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 1 }}>
                <Box sx={{ minWidth: 0 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                    <Typography sx={{ fontWeight: 700 }}>{gate.name}</Typography>
                    <Chip label={gate.is_active ? 'Ativa' : 'Inativa'} size="small" color={gate.is_active ? 'success' : 'default'} />
                  </Stack>
                  <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mt: 0.5 }}>
                    {gate.allowed_ticket_types.length === 0
                      ? 'Aceita qualquer tipo de ingresso'
                      : gate.allowed_ticket_types.map((ticketType) => ticketType.name).join(', ')}
                  </Typography>
                </Box>
                <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                  {canUpdate && (
                    <Tooltip title="Editar portaria" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Editar portaria ${gate.name}`}
                        onClick={() => openEditForm(gate)}
                        sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                      >
                        <EditOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                  {canDelete && (
                    <Tooltip title="Desativar portaria" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Desativar portaria ${gate.name}`}
                        onClick={() => {
                          setDeleteError(null)
                          setDeleteTarget(gate)
                        }}
                        sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
                      >
                        <DeleteOutlineIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                </Stack>
              </Stack>
            </Paper>
          ))}
        </Stack>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Desativar portaria"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
