import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import RefreshIcon from '@mui/icons-material/Refresh'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import type { DragEvent } from 'react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { WorkflowActionDropZone } from '../../components/workflow/WorkflowActionDropZone'
import { WorkflowBoardColumn } from '../../components/workflow/WorkflowBoardColumn'
import { WorkflowReasonDialog } from '../../components/workflow/WorkflowReasonDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { useAccessControl } from '../../hooks/useAccessControl'
import * as balcaoService from '../../services/balcaoService'
import * as workflowService from '../../services/workflowService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { ComandaItem, PrepStatus, Station } from '../../types/balcao'
import { KDS_NEXT_STATUS, PREP_STATUS_META } from '../../constants/balcao'

/** Mesmo intervalo de polling usado no projeto (StorefrontOrderManagementPage). */
const POLL_INTERVAL_MS = 30000

/** Colunas da fila do KDS, na ordem do fluxo. */
const KDS_COLUMNS: Array<Extract<PrepStatus, 'sent_to_station' | 'preparing' | 'ready'>> = ['sent_to_station', 'preparing', 'ready']

type KdsDropTarget = Exclude<PrepStatus, 'queued'>

interface ResolvedKdsDropAction {
  itemUuid: string
  title: string
  description: string
  confirmLabel: string
  requiresReason: boolean
  execute: (reason: string) => Promise<ComandaItem>
}

function waitMinutes(iso: string | null): number | null {
  if (!iso) return null
  const diff = Date.now() - new Date(iso).getTime()
  return diff >= 0 ? Math.floor(diff / 60000) : null
}

/**
 * KDS (Kitchen Display System) — EXCEÇÃO DE DESIGN CONSCIENTE (ver
 * design-system.md): tela fixa de cozinha/bar, leitura à distância. Fonte
 * grande, alto contraste, botões grandes, colunas por status. Não segue o
 * mobile-first denso do resto do app.
 */
export function BalcaoKdsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const stationParam = searchParams.get('station')
  const { can } = useAccessControl()
  const canPrep = can(ACCESS.balcaoPrep)

  const [stations, setStations] = useState<Station[]>([])
  const [stationsLoaded, setStationsLoaded] = useState(false)
  const [tickets, setTickets] = useState<ComandaItem[]>([])
  const [isLoading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busyUuid, setBusyUuid] = useState<string | null>(null)
  const [draggedTicketUuid, setDraggedTicketUuid] = useState<string | null>(null)
  const [activeDropTarget, setActiveDropTarget] = useState<KdsDropTarget | null>(null)
  const [pendingDropAction, setPendingDropAction] = useState<ResolvedKdsDropAction | null>(null)
  const [dropSubmitting, setDropSubmitting] = useState(false)
  const [selectedTimeline, setSelectedTimeline] = useState<{ comandaUuid: string; itemUuid: string; itemName: string } | null>(null)
  const pollRef = useRef<number | null>(null)

  // Carrega estações uma vez (para o seletor).
  useEffect(() => {
    balcaoService
      .listStations()
      .then((result) => setStations(result.filter((station) => station.is_active)))
      .catch(() => setStations([]))
      .finally(() => setStationsLoaded(true))
  }, [])

  const poll = useCallback(
    async (showSpinner: boolean) => {
      if (!stationParam) return
      if (showSpinner) setLoading(true)
      try {
        const result = await balcaoService.listStationTickets(stationParam)
        setTickets(result)
        setError(null)
      } catch (err) {
        setError(getApiErrorMessage(err, 'Não foi possível carregar a fila da estação.'))
      } finally {
        if (showSpinner) setLoading(false)
      }
    },
    [stationParam],
  )

  useEffect(() => {
    if (!stationParam) {
      setTickets([])
      return
    }
    void poll(true)
    pollRef.current = window.setInterval(() => void poll(false), POLL_INTERVAL_MS)
    return () => {
      if (pollRef.current !== null) window.clearInterval(pollRef.current)
    }
  }, [stationParam, poll])

  const grouped = useMemo(() => {
    const map: Record<PrepStatus, ComandaItem[]> = {
      queued: [],
      sent_to_station: [],
      preparing: [],
      ready: [],
      delivered_to_table: [],
      cancelled: [],
    }
    for (const ticket of tickets) {
      if (map[ticket.prep_status]) map[ticket.prep_status].push(ticket)
    }
    return map
  }, [tickets])

  const ticketMap = useMemo(() => new Map(tickets.map((ticket) => [ticket.uuid, ticket])), [tickets])
  const draggedTicket = draggedTicketUuid ? (ticketMap.get(draggedTicketUuid) ?? null) : null

  async function advance(ticket: ComandaItem) {
    const step = KDS_NEXT_STATUS[ticket.prep_status]
    if (!step || !ticket.comanda?.uuid) return
    setBusyUuid(ticket.uuid)
    try {
      await balcaoService.updatePrepStatus(ticket.comanda.uuid, ticket.uuid, { prep_status: step.next })
      await poll(false)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível avançar o item.'))
    } finally {
      setBusyUuid(null)
    }
  }

  const resolveDropAction = useCallback(
    (ticket: ComandaItem, target: KdsDropTarget): ResolvedKdsDropAction | null => {
      if (!canPrep || !ticket.comanda?.uuid) return null
      if (ticket.prep_status === target) return null

      if (ticket.prep_status === 'sent_to_station' && target === 'preparing') {
        return {
          itemUuid: ticket.uuid,
          title: 'Iniciar preparo',
          description: 'O item será movido para a etapa de preparo.',
          confirmLabel: 'Iniciar preparo',
          requiresReason: false,
          execute: () => balcaoService.updatePrepStatus(ticket.comanda!.uuid, ticket.uuid, { prep_status: 'preparing' }),
        }
      }

      if (ticket.prep_status === 'preparing' && target === 'ready') {
        return {
          itemUuid: ticket.uuid,
          title: 'Marcar como pronto',
          description: 'O item será movido para a etapa de item pronto.',
          confirmLabel: 'Marcar como pronto',
          requiresReason: false,
          execute: () => balcaoService.updatePrepStatus(ticket.comanda!.uuid, ticket.uuid, { prep_status: 'ready' }),
        }
      }

      if (ticket.prep_status === 'ready' && target === 'delivered_to_table') {
        return {
          itemUuid: ticket.uuid,
          title: 'Marcar como entregue',
          description: 'O item sairá do KDS e será registrado como entregue na mesa.',
          confirmLabel: 'Marcar como entregue',
          requiresReason: false,
          execute: () =>
            balcaoService.updatePrepStatus(ticket.comanda!.uuid, ticket.uuid, { prep_status: 'delivered_to_table' }),
        }
      }

      if (target === 'cancelled' && ticket.prep_status !== 'delivered_to_table' && ticket.prep_status !== 'cancelled') {
        return {
          itemUuid: ticket.uuid,
          title: 'Cancelar item',
          description: 'Informe o motivo do cancelamento. Esse motivo ficará salvo no histórico operacional.',
          confirmLabel: 'Cancelar item',
          requiresReason: true,
          execute: (reason) =>
            balcaoService.updatePrepStatus(ticket.comanda!.uuid, ticket.uuid, {
              prep_status: 'cancelled',
              cancelled_reason: reason,
            }),
        }
      }

      return null
    },
    [canPrep],
  )

  const executeDropAction = useCallback(
    async (action: ResolvedKdsDropAction, reason = '') => {
      setBusyUuid(action.itemUuid)
      setDropSubmitting(true)
      setError(null)

      try {
        await action.execute(reason)
        await poll(false)
      } catch (err) {
        setError(getApiErrorMessage(err, 'Não foi possível mover o item agora.'))
      } finally {
        setBusyUuid(null)
        setDropSubmitting(false)
        setPendingDropAction(null)
        setDraggedTicketUuid(null)
        setActiveDropTarget(null)
      }
    },
    [poll],
  )

  const handleDragStart = useCallback((ticketUuid: string, event: DragEvent<HTMLDivElement>) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', ticketUuid)
    setDraggedTicketUuid(ticketUuid)
    setActiveDropTarget(null)
    setError(null)
  }, [])

  const handleDragEnd = useCallback(() => {
    setDraggedTicketUuid(null)
    setActiveDropTarget(null)
  }, [])

  const markDropTarget = useCallback(
    (event: DragEvent<HTMLDivElement>, target: KdsDropTarget) => {
      event.preventDefault()
      if (!draggedTicket) return
      if (!resolveDropAction(draggedTicket, target)) return
      setActiveDropTarget(target)
      event.dataTransfer.dropEffect = 'move'
    },
    [draggedTicket, resolveDropAction],
  )

  const clearDropTarget = useCallback(() => {
    setActiveDropTarget(null)
  }, [])

  const receiveDrop = useCallback(
    async (event: DragEvent<HTMLDivElement>, target: KdsDropTarget) => {
      event.preventDefault()
      const ticketUuid = event.dataTransfer.getData('text/plain') || draggedTicketUuid
      const ticket = ticketUuid ? ticketMap.get(ticketUuid) : null
      if (!ticket) {
        setDraggedTicketUuid(null)
        setActiveDropTarget(null)
        return
      }

      const action = resolveDropAction(ticket, target)
      if (!action) {
        setDraggedTicketUuid(null)
        setActiveDropTarget(null)
        return
      }

      if (action.requiresReason) {
        setPendingDropAction(action)
        return
      }

      await executeDropAction(action)
    },
    [draggedTicketUuid, executeDropAction, resolveDropAction, ticketMap],
  )

  const activeStation = stations.find((station) => station.uuid === stationParam) ?? null

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1400 }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1.5}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, mb: 2 }}
      >
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800 }}>
            Cozinha / Bar
          </Typography>
          <Typography variant="body1" sx={{ color: 'var(--mk-muted)' }}>
            {activeStation ? activeStation.name : 'Selecione uma estação para acompanhar a fila.'}
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <TextField
            select
            size="small"
            label="Estação"
            value={stationParam ?? ''}
            onChange={(event) => {
              const value = event.target.value
              setSearchParams(value ? { station: value } : {})
            }}
            sx={{ minWidth: 200 }}
          >
            <MenuItem value="">
              <em>Selecionar…</em>
            </MenuItem>
            {stations.map((station) => (
              <MenuItem key={station.uuid} value={station.uuid}>
                {station.name}
              </MenuItem>
            ))}
          </TextField>
          <Button
            variant="outlined"
            startIcon={<RefreshIcon />}
            onClick={() => void poll(true)}
            disabled={!stationParam || isLoading}
          >
            Atualizar
          </Button>
        </Stack>
      </Stack>

      {error ? (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      ) : null}

      {stationsLoaded && stations.length === 0 ? (
        <Alert severity="info">Nenhuma estação ativa cadastrada. Cadastre estações para usar o KDS.</Alert>
      ) : null}

      {!stationParam ? (
        <Alert severity="info">Escolha uma estação acima. Você pode fixá-la pela URL (?station=uuid).</Alert>
      ) : isLoading && tickets.length === 0 ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
          <CircularProgress size={32} />
        </Box>
      ) : (
        <Box
          sx={{
            display: 'grid',
            gap: 2,
            gridTemplateColumns: { xs: '1fr', xl: 'repeat(3, minmax(0, 1fr)) 280px' },
            alignItems: 'start',
          }}
        >
          {KDS_COLUMNS.map((status) => {
            const meta = PREP_STATUS_META[status]
            const columnTickets = grouped[status]
            return (
              <WorkflowBoardColumn
                key={status}
                title={meta.label}
                caption={`Itens na etapa ${meta.label.toLowerCase()} do KDS.`}
                accent={meta.color}
                countLabel={`${columnTickets.length} item(ns)`}
                isActiveDrop={activeDropTarget === status}
                onDragOver={(event) => markDropTarget(event, status)}
                onDragLeave={clearDropTarget}
                onDrop={(event) => void receiveDrop(event, status)}
                emptyMessage="Arraste itens compatíveis para esta coluna."
              >
                {columnTickets.length === 0 ? (
                  <Typography sx={{ fontSize: 15, color: 'var(--mk-muted)' }}>Sem itens</Typography>
                ) : (
                  columnTickets.map((ticket) => (
                    <KdsTicketCard
                      key={ticket.uuid}
                      ticket={ticket}
                      canPrep={canPrep}
                      busy={busyUuid === ticket.uuid}
                      onAdvance={() => void advance(ticket)}
                      onOpenTimeline={() => {
                        if (!ticket.comanda?.uuid) return
                        setSelectedTimeline({
                          comandaUuid: ticket.comanda.uuid,
                          itemUuid: ticket.uuid,
                          itemName: ticket.product?.name ?? 'item',
                        })
                      }}
                      draggable={canPrep}
                      isDragging={draggedTicketUuid === ticket.uuid}
                      onDragStart={(event) => handleDragStart(ticket.uuid, event)}
                      onDragEnd={handleDragEnd}
                    />
                  ))
                )}
              </WorkflowBoardColumn>
            )
          })}

          <Stack spacing={1.5}>
            <WorkflowActionDropZone
              title="Entregar na mesa"
              description="Solte aqui itens prontos para concluir o fluxo no KDS. O card sai da fila após a confirmação."
              accent="var(--mk-success)"
              isActiveDrop={activeDropTarget === 'delivered_to_table'}
              isDisabled={!draggedTicket || !resolveDropAction(draggedTicket, 'delivered_to_table')}
              onDragOver={(event) => markDropTarget(event, 'delivered_to_table')}
              onDragLeave={clearDropTarget}
              onDrop={(event) => void receiveDrop(event, 'delivered_to_table')}
            />
            <WorkflowActionDropZone
              title="Cancelar item"
              description="Use esta área para cancelamentos operacionais. O motivo é obrigatório e fica salvo no histórico."
              accent="var(--mk-danger)"
              icon={<CancelOutlinedIcon fontSize="small" />}
              isActiveDrop={activeDropTarget === 'cancelled'}
              isDisabled={!draggedTicket || !resolveDropAction(draggedTicket, 'cancelled')}
              onDragOver={(event) => markDropTarget(event, 'cancelled')}
              onDragLeave={clearDropTarget}
              onDrop={(event) => void receiveDrop(event, 'cancelled')}
            />
            <Box sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
              <Typography sx={{ fontSize: 14, fontWeight: 800, mb: 0.75 }}>Como usar</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                Arraste os itens entre as colunas para avançar o preparo. Para concluir, solte em "Entregar na mesa". Para
                cancelar, solte em "Cancelar item" e informe o motivo.
              </Typography>
            </Box>
          </Stack>
        </Box>
      )}

      <WorkflowReasonDialog
        open={pendingDropAction !== null}
        title={pendingDropAction?.title ?? 'Cancelar item'}
        description={pendingDropAction?.description ?? ''}
        confirmLabel={pendingDropAction?.confirmLabel ?? 'Confirmar'}
        loading={dropSubmitting}
        onClose={() => {
          if (dropSubmitting) return
          setPendingDropAction(null)
          setDraggedTicketUuid(null)
          setActiveDropTarget(null)
        }}
        onConfirm={(reason) => {
          if (!pendingDropAction) return
          void executeDropAction(pendingDropAction, reason)
        }}
      />

      <WorkflowTimelineDialog
        open={selectedTimeline !== null}
        title="Histórico operacional do item"
        subjectLabel={selectedTimeline?.itemName ?? 'item'}
        loader={() =>
          selectedTimeline
            ? workflowService.getComandaItemWorkflowTimeline(selectedTimeline.comandaUuid, selectedTimeline.itemUuid)
            : Promise.resolve([])
        }
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return PREP_STATUS_META[stage as PrepStatus]?.label ?? stage
        }}
        onClose={() => setSelectedTimeline(null)}
      />
    </Box>
  )
}

interface KdsTicketCardProps {
  ticket: ComandaItem
  canPrep: boolean
  busy: boolean
  onAdvance: () => void
  onOpenTimeline: () => void
  draggable?: boolean
  isDragging?: boolean
  onDragStart?: (event: DragEvent<HTMLDivElement>) => void
  onDragEnd?: (event: DragEvent<HTMLDivElement>) => void
}

function KdsTicketCard({
  ticket,
  canPrep,
  busy,
  onAdvance,
  onOpenTimeline,
  draggable = false,
  isDragging = false,
  onDragStart,
  onDragEnd,
}: KdsTicketCardProps) {
  const meta = PREP_STATUS_META[ticket.prep_status]
  const step = KDS_NEXT_STATUS[ticket.prep_status]
  const minutes = waitMinutes(ticket.sent_to_station_at)
  const table = ticket.comanda?.table_label ?? ticket.comanda?.label ?? 'Balcão'

  return (
    <Box
      draggable={draggable}
      onDragStart={onDragStart}
      onDragEnd={onDragEnd}
      sx={{
        ...ELEVATED_SURFACE_SX,
        p: 2,
        borderLeft: `6px solid ${meta.color}`,
        boxShadow: 'var(--mk-shadow-sm)',
        opacity: isDragging ? 0.46 : 1,
        cursor: draggable ? 'grab' : 'default',
        transition: 'opacity 140ms ease',
      }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'baseline', gap: 1, mb: 0.5 }}>
        <Typography sx={{ fontWeight: 800, fontSize: 18 }} noWrap>
          {table}
        </Typography>
        {minutes !== null ? (
          <Typography
            sx={{ fontWeight: 800, fontSize: 16, color: minutes >= 15 ? 'var(--mk-danger)' : 'var(--mk-muted)' }}
          >
            {minutes} min
          </Typography>
        ) : null}
      </Stack>

      <Typography sx={{ fontWeight: 700, fontSize: 22, lineHeight: 1.2 }}>
        {ticket.qty}× {ticket.product?.name ?? 'Item'}
      </Typography>
      {ticket.notes ? (
        <Typography sx={{ fontSize: 16, color: 'var(--mk-warning)', fontWeight: 600, mt: 0.5 }}>
          {ticket.notes}
        </Typography>
      ) : null}

      <Button
        fullWidth
        variant="text"
        startIcon={<HistoryOutlinedIcon />}
        onClick={onOpenTimeline}
        sx={{ mt: 1, fontSize: 14, fontWeight: 700 }}
      >
        Ver histórico
      </Button>

      {canPrep && step ? (
        <Button
          fullWidth
          variant="contained"
          size="large"
          disabled={busy}
          onClick={onAdvance}
          sx={{ mt: 1.5, fontSize: 16, fontWeight: 700, py: 1.25 }}
        >
          {busy ? '…' : step.actionLabel}
        </Button>
      ) : null}
    </Box>
  )
}
