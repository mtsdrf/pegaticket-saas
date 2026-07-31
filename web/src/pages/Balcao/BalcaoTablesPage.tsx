import AddIcon from '@mui/icons-material/Add'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import HourglassBottomOutlinedIcon from '@mui/icons-material/HourglassBottomOutlined'
import KitchenOutlinedIcon from '@mui/icons-material/KitchenOutlined'
import TableRestaurantOutlinedIcon from '@mui/icons-material/TableRestaurantOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { TABLE_STATUS_META } from '../../constants/balcao'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { useBalcaoOffline } from '../../hooks/useBalcaoOffline'
import { OperatorSessionBadge } from '../../components/pdv/OperatorSessionBadge'
import { OfflineConflictResolutionPanel } from '../../components/shared/OfflineConflictResolutionPanel'
import { OfflineContingencyGuide } from '../../components/shared/OfflineContingencyGuide'
import { OfflineSyncActivityPanel, type OfflineActivityEntry } from '../../components/shared/OfflineSyncActivityPanel'
import * as balcaoService from '../../services/balcaoService'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type {
  BalcaoOfflineLocalItem,
  Comanda,
  CreateTableReservationPayload,
  CreateTableWaitlistPayload,
  Table,
  TableReservation,
  TableWaitlistEntry,
} from '../../types/balcao'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

const DEFAULT_RESERVATION_DURATION = 120

function toDateTimeLocalValue(date: Date) {
  const timezoneOffsetMs = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - timezoneOffsetMs).toISOString().slice(0, 16)
}

function buildInitialReservationDate() {
  const base = new Date()
  base.setMinutes(0, 0, 0)
  base.setHours(base.getHours() + 1)
  return toDateTimeLocalValue(base)
}

export function BalcaoTablesPage() {
  const navigate = useNavigate()
  const { activeTenantUuid } = useAuth()
  const { can } = useAccessControl()
  const canCreate = can(ACCESS.balcaoCreate)
  const canUpdate = can(ACCESS.balcaoUpdate)
  const canOpen = can(ACCESS.balcaoOpen)
  const [offlineFeedback, setOfflineFeedback] = useState<string | null>(null)
  const {
    isOffline,
    snapshot,
    snapshotSyncedAt,
    effectiveTables,
    effectiveComandas,
    localComandas,
    pendingCount,
    conflictCount,
    conflictingComandas,
    snapshotError,
    isRefreshingSnapshot,
    isSyncing,
    refreshSnapshot,
    syncComandas,
    openLocalComanda,
    discardLocalComanda,
    discardConflictingComandas,
  } = useBalcaoOffline(activeTenantUuid)

  const [tables, setTables] = useState<Table[]>([])
  const [comandas, setComandas] = useState<Comanda[]>([])
  const [reservations, setReservations] = useState<TableReservation[]>([])
  const [waitlist, setWaitlist] = useState<TableWaitlistEntry[]>([])
  const [isLoading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [openFor, setOpenFor] = useState<{ table: Table | null } | null>(null)
  const [reservationDialogOpen, setReservationDialogOpen] = useState(false)
  const [waitlistDialogOpen, setWaitlistDialogOpen] = useState(false)
  const [reservationCancelTarget, setReservationCancelTarget] = useState<TableReservation | null>(null)
  const [waitlistCancelTarget, setWaitlistCancelTarget] = useState<TableWaitlistEntry | null>(null)
  const [waitlistSeatTarget, setWaitlistSeatTarget] = useState<TableWaitlistEntry | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [tablesResult, comandasResult, reservationsResult, waitlistResult] = await Promise.all([
        balcaoService.listTables(),
        balcaoService.listOpenComandas(),
        balcaoService.listTableReservations(),
        balcaoService.listTableWaitlist(),
      ])
      setTables(tablesResult)
      setComandas(comandasResult)
      setReservations(reservationsResult)
      setWaitlist(waitlistResult)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar o balcão agora.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (isOffline || !activeTenantUuid) return
    if (!snapshot) {
      void refreshSnapshot()
    }
  }, [activeTenantUuid, isOffline, refreshSnapshot, snapshot])

  const displayedTables = isOffline ? effectiveTables : tables
  const displayedComandas = isOffline ? effectiveComandas : comandas

  const comandaByTable = useMemo(() => {
    const map = new Map<string, Comanda>()
    for (const comanda of displayedComandas) {
      if (comanda.table?.uuid) map.set(comanda.table.uuid, comanda)
    }
    return map
  }, [displayedComandas])

  const walkInComandas = useMemo(() => displayedComandas.filter((comanda) => !comanda.table), [displayedComandas])
  const confirmedReservations = reservations.filter((reservation) => reservation.status === 'confirmed')
  const waitingWaitlist = waitlist.filter((entry) => entry.status === 'waiting' || entry.status === 'called')

  const offlineActivityEntries = useMemo<OfflineActivityEntry[]>(
    () =>
      [...localComandas]
        .sort((a, b) => b.opened_at.localeCompare(a.opened_at))
        .slice(0, 8)
        .map((comanda) => {
          const activeItems = comanda.items.filter((item: BalcaoOfflineLocalItem) => item.prep_status !== 'cancelled')
          const subtotal = activeItems.reduce((sum: number, item: BalcaoOfflineLocalItem) => sum + item.line_total, 0)
          const itemErrors = comanda.items.filter((item: BalcaoOfflineLocalItem) => item.sync_status === 'error').length
          const pendingItems = comanda.items.filter(
            (item: BalcaoOfflineLocalItem) => item.sync_status === 'pending' || item.sync_status === 'syncing',
          ).length

          return {
            id: comanda.local_comanda_uuid,
            title: comanda.table?.label ?? comanda.label ?? 'Comanda local',
            subtitle: comanda.server_comanda_uuid ? 'Comanda vinculada ao servidor' : 'Comanda criada localmente',
            description: `${activeItems.length} item${activeItems.length === 1 ? '' : 's'} · ${formatCurrency(subtotal)}`,
            status: comanda.sync_status,
            meta:
              comanda.sync_status === 'conflict'
                ? (comanda.conflict_reason ?? 'Conflito de sincronização identificado.')
                : comanda.last_error
                  ? comanda.last_error
                  : `${pendingItems} item${pendingItems === 1 ? '' : 's'} pendente${pendingItems === 1 ? '' : 's'}${itemErrors > 0 ? ` · ${itemErrors} com falha` : ''}.`,
          }
        }),
    [localComandas],
  )

  const handleTableClick = useCallback(
    (table: Table) => {
      const comanda = comandaByTable.get(table.uuid)
      if (comanda) {
        navigate(`/balcao/comandas/${comanda.uuid}`)
        return
      }
      if (canOpen) setOpenFor({ table })
    },
    [comandaByTable, navigate, canOpen],
  )

  async function reloadWithMessage(message?: string) {
    await load()
    if (message) {
      setOfflineFeedback(message)
    }
  }

  async function handleRefreshSnapshot() {
    setOfflineFeedback(null)
    const refreshed = await refreshSnapshot()
    if (refreshed) {
      setOfflineFeedback('Base offline do balcão atualizada com sucesso.')
    }
  }

  async function handleSync() {
    setOfflineFeedback(null)
    const result = await syncComandas()
    if (result.syncedComandas > 0 || result.syncedItems > 0 || result.failed > 0 || result.conflicts > 0) {
      setOfflineFeedback(
        `Sincronização concluída: ${result.syncedComandas} comandas e ${result.syncedItems} itens enviados, ${result.failed} falhas e ${result.conflicts} conflitos para revisão.`,
      )
    }
  }

  async function handleDiscardConflict(localComandaUuid: string) {
    setOfflineFeedback(null)
    await discardLocalComanda(localComandaUuid)
    setOfflineFeedback('Lançamento local em conflito descartado neste dispositivo.')
  }

  async function handleDiscardAllConflicts() {
    setOfflineFeedback(null)
    const discarded = await discardConflictingComandas()
    if (discarded > 0) {
      setOfflineFeedback(
        `${discarded} conflito${discarded === 1 ? '' : 's'} local${discarded === 1 ? '' : 'is'} descartado${discarded === 1 ? '' : 's'} neste dispositivo.`,
      )
    }
  }

  async function handleSeatReservation(reservation: TableReservation) {
    try {
      const result = await balcaoService.seatTableReservation(reservation.uuid, { label: reservation.customer_name })
      await load()
      setOfflineFeedback('Reserva acomodada e comanda aberta com sucesso.')
      if (result.seated_comanda_uuid) {
        navigate(`/balcao/comandas/${result.seated_comanda_uuid}`)
      }
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível acomodar esta reserva agora.'))
    }
  }

  async function handleNoShowReservation(reservation: TableReservation) {
    try {
      await balcaoService.noShowTableReservation(reservation.uuid)
      await reloadWithMessage('Reserva marcada como não compareceu.')
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível atualizar esta reserva agora.'))
    }
  }

  async function handleCallWaitlist(entry: TableWaitlistEntry) {
    try {
      await balcaoService.callTableWaitlist(entry.uuid)
      await reloadWithMessage('Cliente chamado com sucesso.')
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível chamar este cliente agora.'))
    }
  }

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1360 }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1.5}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, mb: 2 }}
      >
        <Box>
          <Typography variant="h5" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <TableRestaurantOutlinedIcon /> Balcão
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            Mesas, reservas, fila de espera e comandas operando no mesmo fluxo.
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
          <OperatorSessionBadge />
          <Button variant="outlined" startIcon={<KitchenOutlinedIcon />} onClick={() => navigate('/balcao/kds')} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
            Cozinha / Bar
          </Button>
          {canCreate ? (
            <Button variant="outlined" startIcon={<EventSeatOutlinedIcon />} onClick={() => setReservationDialogOpen(true)} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
              Nova reserva
            </Button>
          ) : null}
          {canCreate ? (
            <Button variant="outlined" startIcon={<HourglassBottomOutlinedIcon />} onClick={() => setWaitlistDialogOpen(true)} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
              Fila de espera
            </Button>
          ) : null}
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            disabled={!canOpen}
            onClick={() => setOpenFor({ table: null })}
            sx={{ minHeight: UI_SIZE.controlLarge, borderRadius: UI_RADIUS.md }}
          >
            Comanda avulsa
          </Button>
        </Stack>
      </Stack>

      {error ? (
        <Alert
          severity="error"
          sx={{ mb: 2 }}
          action={
            <Button color="inherit" size="small" onClick={() => void load()}>
              Tentar de novo
            </Button>
          }
        >
          {error}
        </Alert>
      ) : null}

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}>
        <Stack spacing={1.25}>
          <Stack
            direction={{ xs: 'column', md: 'row' }}
            spacing={1}
            sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', md: 'center' } }}
          >
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <Chip color={isOffline ? 'warning' : 'success'} label={isOffline ? 'Modo offline' : 'Modo online'} />
              <Chip variant="outlined" color={snapshot ? 'success' : 'warning'} label={snapshot ? 'Base offline pronta' : 'Base offline indisponível'} />
              <Chip variant="outlined" label={`${pendingCount} pendência${pendingCount === 1 ? '' : 's'}`} />
              <Chip variant="outlined" color="info" label={`${confirmedReservations.length} reserva${confirmedReservations.length === 1 ? '' : 's'} ativa${confirmedReservations.length === 1 ? '' : 's'}`} />
              <Chip variant="outlined" color="secondary" label={`${waitingWaitlist.length} na fila`} />
              {conflictCount > 0 ? <Chip color="error" variant="outlined" label={`${conflictCount} conflito${conflictCount === 1 ? '' : 's'}`} /> : null}
            </Stack>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
              <Button variant="outlined" onClick={() => void handleRefreshSnapshot()} disabled={isOffline || isRefreshingSnapshot}>
                {isRefreshingSnapshot ? 'Atualizando base…' : 'Atualizar base offline'}
              </Button>
              <Button variant="contained" onClick={() => void handleSync()} disabled={isOffline || pendingCount === 0 || isSyncing}>
                {isSyncing ? 'Sincronizando…' : 'Sincronizar pendências'}
              </Button>
            </Stack>
          </Stack>

          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {snapshotSyncedAt
              ? `Última base offline salva em ${formatDateTimeBR(snapshotSyncedAt)}.`
              : 'Nenhuma base offline salva ainda para o balcão.'}
            {' '}Offline, você pode abrir comandas e lançar itens localmente; reservas e fila continuam apenas em leitura do último estado online.
          </Typography>

          {isOffline && !snapshot ? (
            <Alert severity="warning">
              O balcão está sem conexão e ainda não possui uma base offline salva. Conecte-se e atualize a base antes de operar sem internet.
            </Alert>
          ) : null}

          {snapshotError ? <Alert severity="error">{snapshotError}</Alert> : null}
          {conflictCount > 0 ? (
            <Alert severity="warning">
              {`Há ${conflictCount} comanda${conflictCount === 1 ? '' : 's'} com conflito de sincronização entre dispositivos. Revise a mesa/comanda indicada antes de continuar operando.`}
            </Alert>
          ) : null}
          {conflictingComandas.slice(0, 2).map((comanda) => (
            <Alert key={comanda.local_comanda_uuid} severity="warning" variant="outlined">
              {(comanda.table?.label ?? comanda.label ?? 'Comanda local')}:
              {' '}
              {comanda.conflict_reason ?? 'Conflito de sincronização identificado. Atualize a base e revise manualmente.'}
            </Alert>
          ))}
          {offlineFeedback ? <Alert severity="success">{offlineFeedback}</Alert> : null}
        </Stack>
      </Paper>

      <Box
        sx={{
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', xl: '1.15fr 0.85fr' },
          alignItems: 'start',
          mb: 2,
        }}
      >
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}>
          <SectionHeader
            icon={<EventSeatOutlinedIcon fontSize="small" />}
            title={`Reservas de hoje (${reservations.length})`}
            subtitle="Reservas confirmadas, online ou internas, sempre com mesa validada."
          />

          {isOffline ? (
            <Alert severity="info">As reservas ficam indisponíveis para edição no modo offline.</Alert>
          ) : reservations.length === 0 ? (
            <Alert severity="info">Nenhuma reserva cadastrada para hoje.</Alert>
          ) : (
            <Stack spacing={1.25}>
              {reservations.map((reservation) => (
                <ReservationCard
                  key={reservation.uuid}
                  reservation={reservation}
                  canUpdate={canUpdate}
                  canOpen={canOpen}
                  onSeat={() => void handleSeatReservation(reservation)}
                  onCancel={() => setReservationCancelTarget(reservation)}
                  onNoShow={() => void handleNoShowReservation(reservation)}
                  onOpenComanda={() => reservation.seated_comanda_uuid && navigate(`/balcao/comandas/${reservation.seated_comanda_uuid}`)}
                />
              ))}
            </Stack>
          )}
        </Paper>

        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}>
          <SectionHeader
            icon={<HourglassBottomOutlinedIcon fontSize="small" />}
            title={`Fila de espera (${waitlist.length})`}
            subtitle="Entradas rápidas para salão lotado, com acomodação e abertura de comanda no mesmo passo."
          />

          {isOffline ? (
            <Alert severity="info">A fila de espera fica indisponível para edição no modo offline.</Alert>
          ) : waitlist.length === 0 ? (
            <Alert severity="info">Nenhum cliente aguardando mesa neste momento.</Alert>
          ) : (
            <Stack spacing={1.25}>
              {waitlist.map((entry) => (
                <WaitlistCard
                  key={entry.uuid}
                  entry={entry}
                  canUpdate={canUpdate}
                  canOpen={canOpen}
                  onCall={() => void handleCallWaitlist(entry)}
                  onSeat={() => setWaitlistSeatTarget(entry)}
                  onCancel={() => setWaitlistCancelTarget(entry)}
                  onOpenComanda={() => entry.seated_comanda_uuid && navigate(`/balcao/comandas/${entry.seated_comanda_uuid}`)}
                />
              ))}
            </Stack>
          )}
        </Paper>
      </Box>

      {isOffline && !snapshot ? (
        <Box sx={{ mb: 2 }}>
          <OfflineContingencyGuide context="balcao-no-snapshot" />
        </Box>
      ) : null}

      {conflictCount > 0 ? (
        <Box sx={{ mb: 2 }}>
          <OfflineContingencyGuide context="balcao-conflict" />
        </Box>
      ) : null}

      {conflictCount > 0 ? (
        <OfflineConflictResolutionPanel
          conflicts={conflictingComandas}
          isBusy={isSyncing || isRefreshingSnapshot}
          onRefreshSnapshot={() => void handleRefreshSnapshot()}
          onSync={() => void handleSync()}
          onReviewConflict={(localComandaUuid) => navigate(`/balcao/comandas/${localComandaUuid}`)}
          onDiscardConflict={(localComandaUuid) => void handleDiscardConflict(localComandaUuid)}
          onDiscardAllConflicts={() => void handleDiscardAllConflicts()}
        />
      ) : null}

      <OfflineSyncActivityPanel
        title="Atividade offline do Balcão"
        description="Esta visão resume as comandas locais deste dispositivo, incluindo pendências, falhas e conflitos antes da reconciliação."
        entries={offlineActivityEntries}
        emptyMessage="Nenhuma comanda local pendente ou sincronizada neste dispositivo até agora."
      />

      {!canOpen ? (
        <Alert severity="info" sx={{ mb: 2 }}>
          Você pode visualizar as comandas, mas não tem permissão para abrir novas.
        </Alert>
      ) : null}

      {walkInComandas.length > 0 ? (
        <Box sx={{ mb: 3 }}>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
            Comandas avulsas ({walkInComandas.length})
          </Typography>
          <Box sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))' }}>
            {walkInComandas.map((comanda) => (
              <ComandaCard
                key={comanda.uuid}
                title={comanda.label || 'Balcão'}
                subtitle={`${comanda.items?.filter((i) => i.prep_status !== 'cancelled').length ?? 0} itens`}
                amount={comanda.items_subtotal ?? 0}
                color="var(--mk-primary)"
                onClick={() => navigate(`/balcao/comandas/${comanda.uuid}`)}
              />
            ))}
          </Box>
        </Box>
      ) : null}

      <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
        Mesas ({displayedTables.length})
      </Typography>
      {displayedTables.length === 0 ? (
        <Alert severity="info">
          Nenhuma mesa cadastrada ainda. Você ainda pode usar comandas avulsas de balcão.
        </Alert>
      ) : (
        <Box sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))' }}>
          {displayedTables.map((table) => {
            const comanda = comandaByTable.get(table.uuid)
            const meta = TABLE_STATUS_META[comanda ? 'occupied' : table.status]
            return (
              <ComandaCard
                key={table.uuid}
                title={table.label}
                subtitle={
                  comanda
                    ? `${comanda.items?.filter((i) => i.prep_status !== 'cancelled').length ?? 0} itens · ${meta.label}`
                    : meta.label
                }
                amount={comanda ? comanda.items_subtotal ?? 0 : null}
                color={meta.color}
                onClick={() => handleTableClick(table)}
              />
            )
          })}
        </Box>
      )}

      {openFor ? (
        <OpenComandaDialog
          table={openFor.table}
          isOfflineMode={isOffline}
          hasOfflineSnapshot={Boolean(snapshot)}
          onClose={() => setOpenFor(null)}
          onOpened={async (comanda) => {
            if ('uuid' in comanda) {
              navigate(`/balcao/comandas/${comanda.uuid}`)
              return
            }

            const local = await openLocalComanda({
              table: comanda.table,
              label: comanda.label,
            })
            navigate(`/balcao/comandas/${local.local_comanda_uuid}`)
          }}
        />
      ) : null}

      {reservationDialogOpen ? (
        <ReservationDialog
          onClose={() => setReservationDialogOpen(false)}
          onCreated={async () => {
            setReservationDialogOpen(false)
            await reloadWithMessage('Reserva criada com sucesso.')
          }}
        />
      ) : null}

      {waitlistDialogOpen ? (
        <WaitlistDialog
          onClose={() => setWaitlistDialogOpen(false)}
          onCreated={async () => {
            setWaitlistDialogOpen(false)
            await reloadWithMessage('Cliente adicionado à fila de espera.')
          }}
        />
      ) : null}

      {reservationCancelTarget ? (
        <ReasonDialog
          title={`Cancelar reserva de ${reservationCancelTarget.customer_name}`}
          confirmLabel="Cancelar reserva"
          onClose={() => setReservationCancelTarget(null)}
          onConfirm={async (reason) => {
            await balcaoService.cancelTableReservation(reservationCancelTarget.uuid, reason)
            setReservationCancelTarget(null)
            await reloadWithMessage('Reserva cancelada com sucesso.')
          }}
        />
      ) : null}

      {waitlistCancelTarget ? (
        <ReasonDialog
          title={`Cancelar fila de ${waitlistCancelTarget.customer_name}`}
          confirmLabel="Cancelar entrada"
          onClose={() => setWaitlistCancelTarget(null)}
          onConfirm={async (reason) => {
            await balcaoService.cancelTableWaitlist(waitlistCancelTarget.uuid, reason)
            setWaitlistCancelTarget(null)
            await reloadWithMessage('Entrada da fila cancelada com sucesso.')
          }}
        />
      ) : null}

      {waitlistSeatTarget ? (
        <SeatWaitlistDialog
          entry={waitlistSeatTarget}
          onClose={() => setWaitlistSeatTarget(null)}
          onSeated={async (comandaUuid) => {
            setWaitlistSeatTarget(null)
            await load()
            setOfflineFeedback('Cliente acomodado e comanda aberta com sucesso.')
            navigate(`/balcao/comandas/${comandaUuid}`)
          }}
        />
      ) : null}
    </Box>
  )
}

function SectionHeader({ icon, title, subtitle }: { icon: React.ReactNode; title: string; subtitle: string }) {
  return (
    <Box sx={{ mb: 1.5 }}>
      <Typography sx={{ display: 'flex', alignItems: 'center', gap: 0.75, fontWeight: 700, mb: 0.35 }}>
        {icon}
        {title}
      </Typography>
      <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
        {subtitle}
      </Typography>
    </Box>
  )
}

function ReservationCard({
  reservation,
  canUpdate,
  canOpen,
  onSeat,
  onCancel,
  onNoShow,
  onOpenComanda,
}: {
  reservation: TableReservation
  canUpdate: boolean
  canOpen: boolean
  onSeat: () => void
  onCancel: () => void
  onNoShow: () => void
  onOpenComanda: () => void
}) {
  const statusColor =
    reservation.status === 'confirmed'
      ? 'info'
      : reservation.status === 'seated'
        ? 'success'
        : reservation.status === 'no_show'
          ? 'warning'
          : 'default'

  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 1.5 }}>
      <Stack spacing={1}>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' } }}>
          <Box>
            <Typography sx={{ fontWeight: 700 }}>{reservation.customer_name}</Typography>
            <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
              {formatDateTimeBR(reservation.scheduled_for)} · {reservation.party_size} pessoa{reservation.party_size === 1 ? '' : 's'}
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
            <Chip size="small" color={statusColor} label={reservation.status === 'confirmed' ? 'Confirmada' : reservation.status === 'seated' ? 'Acomodada' : reservation.status === 'no_show' ? 'Não compareceu' : 'Cancelada'} />
            <Chip size="small" variant="outlined" label={reservation.source === 'online' ? 'Online' : 'Interna'} />
            {reservation.table ? <Chip size="small" variant="outlined" label={reservation.table.label} /> : null}
          </Stack>
        </Stack>

        {reservation.notes ? (
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {reservation.notes}
          </Typography>
        ) : null}

        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
          {reservation.status === 'confirmed' && canOpen ? (
            <Button size="small" variant="contained" onClick={onSeat}>
              Acomodar e abrir comanda
            </Button>
          ) : null}
          {reservation.seated_comanda_uuid ? (
            <Button size="small" variant="outlined" onClick={onOpenComanda}>
              Abrir comanda
            </Button>
          ) : null}
          {reservation.status === 'confirmed' && canUpdate ? (
            <Button size="small" variant="outlined" color="warning" onClick={onNoShow}>
              Não compareceu
            </Button>
          ) : null}
          {reservation.status === 'confirmed' && canUpdate ? (
            <Button size="small" variant="outlined" color="error" onClick={onCancel}>
              Cancelar
            </Button>
          ) : null}
        </Stack>
      </Stack>
    </Paper>
  )
}

function WaitlistCard({
  entry,
  canUpdate,
  canOpen,
  onCall,
  onSeat,
  onCancel,
  onOpenComanda,
}: {
  entry: TableWaitlistEntry
  canUpdate: boolean
  canOpen: boolean
  onCall: () => void
  onSeat: () => void
  onCancel: () => void
  onOpenComanda: () => void
}) {
  const statusLabel =
    entry.status === 'waiting' ? 'Aguardando' : entry.status === 'called' ? 'Chamado' : entry.status === 'seated' ? 'Acomodado' : 'Cancelado'

  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 1.5 }}>
      <Stack spacing={1}>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' } }}>
          <Box>
            <Typography sx={{ fontWeight: 700 }}>{entry.customer_name}</Typography>
            <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
              {entry.party_size} pessoa{entry.party_size === 1 ? '' : 's'}
              {entry.quoted_wait_minutes ? ` · previsão ${entry.quoted_wait_minutes} min` : ''}
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
            <Chip size="small" color={entry.status === 'seated' ? 'success' : entry.status === 'cancelled' ? 'default' : 'secondary'} label={statusLabel} />
            {entry.table ? <Chip size="small" variant="outlined" label={entry.table.label} /> : null}
          </Stack>
        </Stack>

        {entry.notes ? (
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {entry.notes}
          </Typography>
        ) : null}

        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
          {entry.status === 'waiting' && canUpdate ? (
            <Button size="small" variant="outlined" onClick={onCall}>
              Chamar
            </Button>
          ) : null}
          {(entry.status === 'waiting' || entry.status === 'called') && canOpen ? (
            <Button size="small" variant="contained" onClick={onSeat}>
              Acomodar
            </Button>
          ) : null}
          {entry.seated_comanda_uuid ? (
            <Button size="small" variant="outlined" onClick={onOpenComanda}>
              Abrir comanda
            </Button>
          ) : null}
          {(entry.status === 'waiting' || entry.status === 'called') && canUpdate ? (
            <Button size="small" variant="outlined" color="error" onClick={onCancel}>
              Cancelar
            </Button>
          ) : null}
        </Stack>
      </Stack>
    </Paper>
  )
}

interface ComandaCardProps {
  title: string
  subtitle: string
  amount: number | null
  color: string
  onClick: () => void
}

function ComandaCard({ title, subtitle, amount, color, onClick }: ComandaCardProps) {
  return (
    <Box
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          onClick()
        }
      }}
      sx={{
        ...ELEVATED_SURFACE_SX,
        cursor: 'pointer',
        minHeight: 96,
        p: 1.75,
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        gap: 0.5,
        borderLeft: `4px solid ${color}`,
        transition: 'box-shadow 0.15s, transform 0.05s',
        '&:hover, &:focus-visible': { boxShadow: 'var(--mk-shadow-md)', transform: 'translateY(-1px)', outline: 'none' },
        '&:active': { transform: 'scale(0.99)' },
      }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1 }}>
        <Typography sx={{ fontWeight: 700, fontSize: 16, lineHeight: 1.2 }} noWrap>
          {title}
        </Typography>
        <Chip
          size="small"
          label=""
          sx={{ width: 12, height: 12, borderRadius: '50%', bgcolor: color, '& .MuiChip-label': { p: 0 } }}
        />
      </Stack>
      <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }}>
        {subtitle}
      </Typography>
      {amount !== null ? <Typography sx={{ fontWeight: 700, fontSize: 15 }}>{formatCurrency(amount)}</Typography> : null}
    </Box>
  )
}

interface OpenComandaDialogProps {
  table: Table | null
  isOfflineMode: boolean
  hasOfflineSnapshot: boolean
  onClose: () => void
  onOpened: (comanda: Comanda | { table: { uuid: string; label: string } | null; label: string | null }) => void | Promise<void>
}

function OpenComandaDialog({ table, isOfflineMode, hasOfflineSnapshot, onClose, onOpened }: OpenComandaDialogProps) {
  const [label, setLabel] = useState('')
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleConfirm() {
    setSubmitting(true)
    setError(null)
    if (isOfflineMode) {
      if (!hasOfflineSnapshot) {
        setError('Sem uma base offline salva, não é possível abrir uma comanda local agora.')
        setSubmitting(false)
        return
      }

      await onOpened({
        table: table ? { uuid: table.uuid, label: table.label } : null,
        label: label.trim() || null,
      })
      return
    }

    try {
      const comanda = await balcaoService.openComanda({
        table_uuid: table?.uuid ?? null,
        label: label.trim() || null,
      })
      onOpened(comanda)
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'COMANDA_ERROR') {
        setError(err.message)
      } else {
        setError(getApiErrorMessage(err, 'Não foi possível abrir a comanda agora.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontWeight: 700 }}>
        {table ? `Abrir comanda — ${table.label}` : 'Nova comanda avulsa'}
      </DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {table ? 'Identifique a pessoa/pedido, se quiser (opcional).' : 'Comanda de balcão, sem mesa vinculada.'}
          </Typography>
          {isOfflineMode ? (
            <Alert severity="info" variant="outlined">
              Esta comanda será salva localmente e sincronizada quando a conexão voltar.
            </Alert>
          ) : null}
          <TextField
            autoFocus
            fullWidth
            label="Identificação (opcional)"
            placeholder={table ? 'Ex.: João, aniversário…' : 'Ex.: Balcão 1'}
            value={label}
            onChange={(event) => setLabel(event.target.value)}
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={handleConfirm} disabled={isSubmitting}>
          {isSubmitting ? 'Abrindo…' : 'Abrir comanda'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function ReservationDialog({ onClose, onCreated }: { onClose: () => void; onCreated: () => Promise<void> }) {
  const [form, setForm] = useState<CreateTableReservationPayload>({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    party_size: 2,
    scheduled_for: buildInitialReservationDate(),
    duration_minutes: DEFAULT_RESERVATION_DURATION,
    notes: '',
    table_uuid: '',
  })
  const [availableTables, setAvailableTables] = useState<Table[]>([])
  const [availabilityError, setAvailabilityError] = useState<string | null>(null)
  const [isAvailabilityLoading, setAvailabilityLoading] = useState(false)
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    async function loadAvailability() {
      if (!form.scheduled_for || !form.party_size) return
      setAvailabilityLoading(true)
      setAvailabilityError(null)
      try {
        const tables = await balcaoService.listReservationAvailability(
          form.scheduled_for,
          Number(form.party_size),
          Number(form.duration_minutes ?? DEFAULT_RESERVATION_DURATION),
        )
        if (!cancelled) setAvailableTables(tables)
      } catch (err) {
        if (!cancelled) {
          setAvailableTables([])
          setAvailabilityError(getApiErrorMessage(err, 'Não foi possível consultar as mesas disponíveis agora.'))
        }
      } finally {
        if (!cancelled) setAvailabilityLoading(false)
      }
    }

    void loadAvailability()
    return () => {
      cancelled = true
    }
  }, [form.scheduled_for, form.party_size, form.duration_minutes])

  async function handleSubmit() {
    setSubmitting(true)
    setError(null)
    try {
      await balcaoService.createTableReservation({
        ...form,
        table_uuid: form.table_uuid || null,
        customer_phone: form.customer_phone?.trim() || null,
        customer_email: form.customer_email?.trim() || null,
        notes: form.notes?.trim() || null,
      })
      await onCreated()
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível criar a reserva agora.'))
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>Nova reserva</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <TextField label="Nome do cliente" value={form.customer_name} onChange={(event) => setForm((current) => ({ ...current, customer_name: event.target.value }))} fullWidth autoFocus />
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField label="Telefone" value={form.customer_phone ?? ''} onChange={(event) => setForm((current) => ({ ...current, customer_phone: event.target.value }))} fullWidth />
            <TextField label="E-mail" type="email" value={form.customer_email ?? ''} onChange={(event) => setForm((current) => ({ ...current, customer_email: event.target.value }))} fullWidth />
          </Stack>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField label="Quantidade de pessoas" type="number" value={form.party_size} onChange={(event) => setForm((current) => ({ ...current, party_size: Number(event.target.value || 1) }))} fullWidth />
            <TextField label="Duração estimada (min)" type="number" value={form.duration_minutes} onChange={(event) => setForm((current) => ({ ...current, duration_minutes: Number(event.target.value || DEFAULT_RESERVATION_DURATION) }))} fullWidth />
          </Stack>
          <TextField
            label="Data e horário"
            type="datetime-local"
            value={form.scheduled_for}
            onChange={(event) => setForm((current) => ({ ...current, scheduled_for: event.target.value }))}
            fullWidth
            slotProps={{ inputLabel: { shrink: true } }}
          />
          <TextField
            select
            label="Mesa"
            value={form.table_uuid ?? ''}
            onChange={(event) => setForm((current) => ({ ...current, table_uuid: event.target.value }))}
            fullWidth
            helperText={availabilityError ?? (isAvailabilityLoading ? 'Buscando mesas disponíveis…' : 'Deixe em branco para a melhor mesa ser escolhida automaticamente.')}
          >
            <MenuItem value="">Escolher automaticamente</MenuItem>
            {availableTables.map((table) => (
              <MenuItem key={table.uuid} value={table.uuid}>
                {table.label}
                {table.seats ? ` · ${table.seats} lugares` : ''}
              </MenuItem>
            ))}
          </TextField>
          <TextField label="Observações" value={form.notes ?? ''} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} fullWidth multiline minRows={3} />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Fechar
        </Button>
        <Button variant="contained" onClick={handleSubmit} disabled={isSubmitting || !form.customer_name.trim()}>
          {isSubmitting ? 'Salvando…' : 'Criar reserva'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function WaitlistDialog({ onClose, onCreated }: { onClose: () => void; onCreated: () => Promise<void> }) {
  const [form, setForm] = useState<CreateTableWaitlistPayload>({
    customer_name: '',
    customer_phone: '',
    party_size: 2,
    quoted_wait_minutes: 20,
    notes: '',
  })
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit() {
    setSubmitting(true)
    setError(null)
    try {
      await balcaoService.createTableWaitlist({
        ...form,
        customer_phone: form.customer_phone?.trim() || null,
        quoted_wait_minutes: form.quoted_wait_minutes ?? null,
        notes: form.notes?.trim() || null,
      })
      await onCreated()
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível adicionar este cliente à fila agora.'))
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>Adicionar à fila de espera</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <TextField label="Nome do cliente" value={form.customer_name} onChange={(event) => setForm((current) => ({ ...current, customer_name: event.target.value }))} fullWidth autoFocus />
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField label="Telefone" value={form.customer_phone ?? ''} onChange={(event) => setForm((current) => ({ ...current, customer_phone: event.target.value }))} fullWidth />
            <TextField label="Quantidade de pessoas" type="number" value={form.party_size} onChange={(event) => setForm((current) => ({ ...current, party_size: Number(event.target.value || 1) }))} fullWidth />
          </Stack>
          <TextField label="Previsão de espera (min)" type="number" value={form.quoted_wait_minutes ?? ''} onChange={(event) => setForm((current) => ({ ...current, quoted_wait_minutes: Number(event.target.value || 0) }))} fullWidth />
          <TextField label="Observações" value={form.notes ?? ''} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} fullWidth multiline minRows={3} />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Fechar
        </Button>
        <Button variant="contained" onClick={handleSubmit} disabled={isSubmitting || !form.customer_name.trim()}>
          {isSubmitting ? 'Salvando…' : 'Adicionar à fila'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function ReasonDialog({
  title,
  confirmLabel,
  onClose,
  onConfirm,
}: {
  title: string
  confirmLabel: string
  onClose: () => void
  onConfirm: (reason: string) => Promise<void>
}) {
  const [reason, setReason] = useState('')
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleConfirm() {
    setSubmitting(true)
    setError(null)
    try {
      await onConfirm(reason)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível concluir a ação agora.'))
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontWeight: 700 }}>{title}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <TextField
            label="Motivo"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            fullWidth
            multiline
            minRows={3}
            autoFocus
          />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Fechar
        </Button>
        <Button variant="contained" color="error" onClick={handleConfirm} disabled={isSubmitting || !reason.trim()}>
          {isSubmitting ? 'Salvando…' : confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function SeatWaitlistDialog({
  entry,
  onClose,
  onSeated,
}: {
  entry: TableWaitlistEntry
  onClose: () => void
  onSeated: (comandaUuid: string) => Promise<void>
}) {
  const [tableUuid, setTableUuid] = useState('')
  const [label, setLabel] = useState(entry.customer_name)
  const [availableTables, setAvailableTables] = useState<Table[]>([])
  const [error, setError] = useState<string | null>(null)
  const [isLoadingTables, setLoadingTables] = useState(true)
  const [isSubmitting, setSubmitting] = useState(false)

  useEffect(() => {
    let cancelled = false
    async function loadTables() {
      setLoadingTables(true)
      setError(null)
      try {
        const result = await balcaoService.listReservationAvailability(
          new Date().toISOString(),
          entry.party_size,
          DEFAULT_RESERVATION_DURATION,
        )
        if (!cancelled) {
          setAvailableTables(result)
          setTableUuid(result[0]?.uuid ?? '')
        }
      } catch (err) {
        if (!cancelled) setError(getApiErrorMessage(err, 'Não foi possível carregar as mesas disponíveis agora.'))
      } finally {
        if (!cancelled) setLoadingTables(false)
      }
    }

    void loadTables()
    return () => {
      cancelled = true
    }
  }, [entry.party_size])

  async function handleSubmit() {
    setSubmitting(true)
    setError(null)
    try {
      const result = await balcaoService.seatTableWaitlist(entry.uuid, {
        table_uuid: tableUuid,
        label: label.trim() || entry.customer_name,
      })
      if (!result.seated_comanda_uuid) {
        throw new Error('Comanda não retornada.')
      }
      await onSeated(result.seated_comanda_uuid)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível acomodar este cliente agora.'))
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontWeight: 700 }}>Acomodar {entry.customer_name}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <TextField
            select
            label="Mesa"
            value={tableUuid}
            onChange={(event) => setTableUuid(event.target.value)}
            fullWidth
            disabled={isLoadingTables}
            helperText={isLoadingTables ? 'Buscando mesas disponíveis…' : undefined}
          >
            {availableTables.map((table) => (
              <MenuItem key={table.uuid} value={table.uuid}>
                {table.label}
                {table.seats ? ` · ${table.seats} lugares` : ''}
              </MenuItem>
            ))}
          </TextField>
          <TextField label="Identificação da comanda" value={label} onChange={(event) => setLabel(event.target.value)} fullWidth />
          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Fechar
        </Button>
        <Button variant="contained" onClick={handleSubmit} disabled={isSubmitting || !tableUuid}>
          {isSubmitting ? 'Acomodando…' : 'Acomodar e abrir comanda'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
