import AddIcon from '@mui/icons-material/Add'
import CenterFocusStrongOutlinedIcon from '@mui/icons-material/CenterFocusStrongOutlined'
import CloseIcon from '@mui/icons-material/Close'
import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import ContentPasteOutlinedIcon from '@mui/icons-material/ContentPasteOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import PublishOutlinedIcon from '@mui/icons-material/PublishOutlined'
import TuneOutlinedIcon from '@mui/icons-material/TuneOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import ZoomInOutlinedIcon from '@mui/icons-material/ZoomInOutlined'
import ZoomOutOutlinedIcon from '@mui/icons-material/ZoomOutOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  IconButton,
  Menu,
  MenuItem,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Tooltip,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../../access/requirements'
import { ConfirmDeleteDialog } from '../../../components/crud/ConfirmDeleteDialog'
import { EmptyState } from '../../../components/layout/EmptyState'
import { PageHeader } from '../../../components/layout/PageHeader'
import { SeatMapViewer, type SeatMapViewerSeat } from '../../../components/storefront/SeatMapViewer'
import { useAccessControl } from '../../../hooks/useAccessControl'
import * as seatService from '../../../services/seatService'
import * as venueService from '../../../services/venueService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { getApiErrorMessage } from '../../../types/api'
import { SEAT_KIND_OPTIONS, type Seat, type SeatKind, type SeatPayload, type Venue } from '../../../types/venue'
import { BatchEditDialog } from './BatchEditDialog'
import { MapCanvas } from './MapCanvas'
import { KIND_LABEL, polygonBounds, seatVisualBounds } from './mapVisuals'
import { SeatInspector } from './SeatInspector'
import { clampViewBox, zoomViewBox, type ViewBox } from './viewBox'

const DEFAULT_PLAN = { width: 800, height: 600 }
const SEATS_PAGE_SIZE = 200
const DEBOUNCE_MS = 350
const PASTE_OFFSET = 36

interface SeatClipboardItem {
  label: string
  sector_name: string | null
  kind: SeatKind
  capacity: number
  pos_x: number
  pos_y: number
  width: number | null
  height: number | null
  geometry_points: Array<{ x: number; y: number }>
  is_accessible: boolean
  status: Seat['status']
}

interface DeleteRequest {
  seats: Seat[]
}

interface ContextMenuState {
  seatUuid: string
  mouseX: number
  mouseY: number
}

interface RenameDialogState {
  seatUuid: string
  value: string
}

function buildAreaPayload(points: Array<{ x: number; y: number }>, seats: Seat[]): SeatPayload {
  const bounds = polygonBounds(points)
  if (!bounds) {
    throw new Error('Área inválida sem pontos suficientes.')
  }

  return {
    label: defaultLabelFor('area', seats),
    kind: 'area',
    pos_x: Math.round((bounds.left + bounds.right) / 2),
    pos_y: Math.round((bounds.top + bounds.bottom) / 2),
    width: Math.round(bounds.right - bounds.left),
    height: Math.round(bounds.bottom - bounds.top),
    geometry_points: points,
    status: 'disponivel',
    capacity: 1,
    is_accessible: false,
  }
}

function shiftGeometryPoints(points: Array<{ x: number; y: number }>, dx: number, dy: number) {
  return points.map((point) => ({ x: point.x + dx, y: point.y + dy }))
}

function buildBaseViewBox(venue: Venue | null, seats: Seat[]): ViewBox {
  if (venue?.width && venue?.height) {
    return { x: 0, y: 0, w: venue.width, h: venue.height }
  }

  if (seats.length > 0) {
    const bounds = seats.map((seat) => seatVisualBounds(seat.kind, seat.pos_x, seat.pos_y, seat.width, seat.height, seat.geometry_points))
    const pad = 80
    const minX = Math.min(...bounds.map((item) => item.left)) - pad
    const minY = Math.min(...bounds.map((item) => item.top)) - pad
    const maxX = Math.max(...bounds.map((item) => item.right)) + pad
    const maxY = Math.max(...bounds.map((item) => item.bottom)) + pad
    return { x: minX, y: minY, w: Math.max(maxX - minX, 200), h: Math.max(maxY - minY, 200) }
  }

  return { x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height }
}

function defaultLabelFor(kind: SeatKind, seats: Seat[]): string {
  const countOfKind = seats.filter((s) => s.kind === kind).length
  return `${KIND_LABEL[kind]} ${countOfKind + 1}`
}

function buildCopyLabel(baseLabel: string, usedLabels: Set<string>): string {
  const normalized = baseLabel.trim() || 'Componente'
  const firstCandidate = `${normalized} (cópia)`
  if (!usedLabels.has(firstCandidate)) {
    usedLabels.add(firstCandidate)
    return firstCandidate
  }

  let suffix = 2
  while (usedLabels.has(`${normalized} (cópia ${suffix})`)) {
    suffix += 1
  }

  const candidate = `${normalized} (cópia ${suffix})`
  usedLabels.add(candidate)
  return candidate
}

function parseLabelSequence(label: string): { prefix: string; value: number; padding: number } | null {
  const match = label.trim().match(/^(.*?)(\d+)$/)
  if (!match) return null

  return {
    prefix: match[1],
    value: Number(match[2]),
    padding: match[2].length,
  }
}

function buildSequenceState(seats: Seat[]): Map<string, { next: number; padding: number }> {
  const sequenceState = new Map<string, { next: number; padding: number }>()

  for (const seat of seats) {
    const parsed = parseLabelSequence(seat.label)
    if (!parsed) continue

    const current = sequenceState.get(parsed.prefix)
    if (!current || parsed.value >= current.next) {
      sequenceState.set(parsed.prefix, {
        next: parsed.value + 1,
        padding: Math.max(parsed.padding, current?.padding ?? 0),
      })
      continue
    }

    current.padding = Math.max(current.padding, parsed.padding)
  }

  return sequenceState
}

function buildPastedLabel(
  baseLabel: string,
  usedLabels: Set<string>,
  sequenceState: Map<string, { next: number; padding: number }>,
): string {
  const parsed = parseLabelSequence(baseLabel)
  if (!parsed) {
    return buildCopyLabel(baseLabel, usedLabels)
  }

  const current = sequenceState.get(parsed.prefix) ?? { next: parsed.value + 1, padding: parsed.padding }
  current.padding = Math.max(current.padding, parsed.padding)

  let candidate = ''
  do {
    candidate = `${parsed.prefix}${String(current.next).padStart(current.padding, '0')}`
    current.next += 1
  } while (usedLabels.has(candidate))

  sequenceState.set(parsed.prefix, current)
  usedLabels.add(candidate)

  return candidate
}

export function VenueMapEditor() {
  const navigate = useNavigate()
  const { venueUuid = '' } = useParams<{ venueUuid: string }>()
  const { can } = useAccessControl()

  const [venue, setVenue] = useState<Venue | null>(null)
  const [seats, setSeats] = useState<Seat[]>([])
  const [seatsTotal, setSeatsTotal] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const [selectedUuids, setSelectedUuids] = useState<Set<string>>(new Set())
  const [searchTerm, setSearchTerm] = useState('')
  const [pendingCreateKind, setPendingCreateKind] = useState<SeatKind | null>(null)
  const [pendingAreaPoints, setPendingAreaPoints] = useState<Array<{ x: number; y: number }>>([])
  const [pendingAreaPointer, setPendingAreaPointer] = useState<{ x: number; y: number } | null>(null)
  const [addMenuAnchor, setAddMenuAnchor] = useState<HTMLElement | null>(null)
  const [panelOpen, setPanelOpen] = useState(true)
  const [clipboard, setClipboard] = useState<SeatClipboardItem[]>([])
  const [isPasting, setIsPasting] = useState(false)
  const [pasteCount, setPasteCount] = useState(0)
  const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(null)
  const [renameDialog, setRenameDialog] = useState<RenameDialogState | null>(null)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [publishSuccess, setPublishSuccess] = useState<string | null>(null)

  const [viewBox, setViewBox] = useState<ViewBox>({ x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height })
  const baseViewBoxRef = useRef<ViewBox>({ x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height })

  const [deleteRequest, setDeleteRequest] = useState<DeleteRequest | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const [batchOpen, setBatchOpen] = useState(false)
  const [batchSaving, setBatchSaving] = useState(false)
  const [batchError, setBatchError] = useState<string | null>(null)

  const [publishConfirmOpen, setPublishConfirmOpen] = useState(false)
  const [isPublishing, setIsPublishing] = useState(false)
  const [publishError, setPublishError] = useState<string | null>(null)

  const debounceRef = useRef<Map<string, { payload: Partial<SeatPayload>; timer: ReturnType<typeof setTimeout> }>>(new Map())
  const canManageSeats = can(ACCESS.seatsCreate) || can(ACCESS.seatsUpdate)

  const reloadEditorData = useCallback(async () => {
    const [venueData, seatsResult] = await Promise.all([venueService.getVenue(venueUuid), seatService.listSeats(venueUuid, { per_page: SEATS_PAGE_SIZE })])
    setVenue(venueData)
    setSeats(seatsResult.items)
    setSeatsTotal(seatsResult.pagination.total)
    const base = buildBaseViewBox(venueData, seatsResult.items)
    baseViewBoxRef.current = base
    setViewBox(base)
  }, [venueUuid])

  useEffect(() => {
    if (!venueUuid) return
    let cancelled = false
    setIsLoading(true)
    setLoadError(null)

    reloadEditorData()
      .then(() => {
        if (cancelled) return
      })
      .catch((error) => {
        if (!cancelled) setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o mapa deste local agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [reloadEditorData, venueUuid])

  useEffect(() => {
    const pending = debounceRef.current
    return () => {
      pending.forEach((entry) => clearTimeout(entry.timer))
    }
  }, [])

  const applyLocal = useCallback((uuid: string, payload: Partial<SeatPayload>) => {
    setSeats((prev) => prev.map((seat) => (seat.uuid === uuid ? ({ ...seat, ...payload } as Seat) : seat)))
  }, [])

  const persistNow = useCallback(
    (uuid: string, payload: Partial<SeatPayload>) => {
      seatService.updateSeat(venueUuid, uuid, payload).catch((error) => {
        setActionError(getApiErrorMessage(error, 'Não foi possível salvar a alteração do assento agora.'))
      })
    },
    [venueUuid],
  )

  const commitSeat = useCallback(
    (uuid: string, payload: Partial<SeatPayload>) => {
      applyLocal(uuid, payload)
      persistNow(uuid, payload)
    },
    [applyLocal, persistNow],
  )

  const commitSeatDebounced = useCallback(
    (uuid: string, payload: Partial<SeatPayload>) => {
      applyLocal(uuid, payload)
      const existing = debounceRef.current.get(uuid)
      if (existing) clearTimeout(existing.timer)
      const merged = { ...(existing?.payload ?? {}), ...payload }
      const timer = setTimeout(() => {
        debounceRef.current.delete(uuid)
        persistNow(uuid, merged)
      }, DEBOUNCE_MS)
      debounceRef.current.set(uuid, { payload: merged, timer })
    },
    [applyLocal, persistNow],
  )

  const handleInspectorCommit = useCallback(
    (uuid: string, payload: Partial<SeatPayload>) => {
      if ('pos_x' in payload || 'pos_y' in payload) {
        commitSeatDebounced(uuid, payload)
      } else {
        commitSeat(uuid, payload)
      }
    },
    [commitSeat, commitSeatDebounced],
  )

  const handleMoveSeat = useCallback((uuid: string, x: number, y: number) => {
    setSeats((prev) =>
      prev.map((seat) => {
        if (seat.uuid !== uuid) return seat
        const dx = x - seat.pos_x
        const dy = y - seat.pos_y
        return {
          ...seat,
          pos_x: x,
          pos_y: y,
          geometry_points: seat.geometry_points.length > 0 ? shiftGeometryPoints(seat.geometry_points, dx, dy) : seat.geometry_points,
        }
      }),
    )
  }, [])

  const handleMoveSelection = useCallback((updates: Array<{ uuid: string; x: number; y: number }>) => {
    const positions = new Map(updates.map((item) => [item.uuid, item]))
    setSeats((prev) =>
      prev.map((seat) => {
        const next = positions.get(seat.uuid)
        if (!next) return seat
        const dx = next.x - seat.pos_x
        const dy = next.y - seat.pos_y
        return {
          ...seat,
          pos_x: next.x,
          pos_y: next.y,
          geometry_points: seat.geometry_points.length > 0 ? shiftGeometryPoints(seat.geometry_points, dx, dy) : seat.geometry_points,
        }
      }),
    )
  }, [])

  const handleCommitMove = useCallback(
    (uuid: string, x: number, y: number) => {
      const seat = seats.find((item) => item.uuid === uuid)
      if (!seat) return
      const dx = x - seat.pos_x
      const dy = y - seat.pos_y
      commitSeat(uuid, {
        pos_x: x,
        pos_y: y,
        geometry_points: seat.geometry_points.length > 0 ? shiftGeometryPoints(seat.geometry_points, dx, dy) : seat.geometry_points,
      })
    },
    [commitSeat, seats],
  )

  const handleCommitSelectionMove = useCallback(
    (updates: Array<{ uuid: string; x: number; y: number }>) => {
      for (const item of updates) {
        const seat = seats.find((current) => current.uuid === item.uuid)
        if (!seat) continue
        const dx = item.x - seat.pos_x
        const dy = item.y - seat.pos_y
        persistNow(item.uuid, {
          pos_x: item.x,
          pos_y: item.y,
          geometry_points: seat.geometry_points.length > 0 ? shiftGeometryPoints(seat.geometry_points, dx, dy) : seat.geometry_points,
        })
      }
    },
    [persistNow, seats],
  )

  const handleCancelCreate = useCallback(() => {
    setPendingCreateKind(null)
    setPendingAreaPoints([])
    setPendingAreaPointer(null)
  }, [])

  const handleStartCreateKind = useCallback((kind: SeatKind) => {
    setPendingCreateKind(kind)
    setPendingAreaPoints([])
    setPendingAreaPointer(null)
    setAddMenuAnchor(null)
  }, [])

  const handleSelectReplace = useCallback((uuids: string[]) => {
    setSelectedUuids(new Set(uuids))
  }, [])

  const handleCreateAt = useCallback(
    (kind: SeatKind, x: number, y: number) => {
      handleCancelCreate()
      const payload: SeatPayload = {
        label: defaultLabelFor(kind, seats),
        kind,
        pos_x: x,
        pos_y: y,
        width: null,
        height: null,
        status: 'disponivel',
        capacity: 1,
        is_accessible: false,
      }

      seatService
        .createSeat(venueUuid, payload)
        .then((seat) => {
          setSeats((prev) => [...prev, seat])
          setSelectedUuids(new Set([seat.uuid]))
          setPanelOpen(true)
        })
        .catch((error) => {
          setActionError(getApiErrorMessage(error, 'Não foi possível criar o assento agora.'))
        })
    },
    [handleCancelCreate, seats, venueUuid],
  )

  const handleAreaDraftPointAdd = useCallback((point: { x: number; y: number }) => {
    setPendingAreaPoints((prev) => [...prev, point])
  }, [])

  const handleAreaDraftPointMove = useCallback((point: { x: number; y: number } | null) => {
    setPendingAreaPointer(point)
  }, [])

  const handleAreaDraftComplete = useCallback(async () => {
    if (pendingAreaPoints.length < 3) return

    try {
      const payload = buildAreaPayload(pendingAreaPoints, seats)
      const seat = await seatService.createSeat(venueUuid, payload)
      setSeats((prev) => [...prev, seat])
      setSelectedUuids(new Set([seat.uuid]))
      setPanelOpen(true)
      handleCancelCreate()
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível criar a área demarcada agora.'))
    }
  }, [handleCancelCreate, pendingAreaPoints, seats, venueUuid])

  const handleDelete = useCallback((seat: Seat) => {
    setDeleteError(null)
    setDeleteRequest({ seats: [seat] })
  }, [])

  const handleDeleteSelection = useCallback(
    (uuids: Iterable<string>) => {
      const targetUuids = new Set(uuids)
      const targets = seats.filter((seat) => targetUuids.has(seat.uuid))
      if (targets.length === 0) return
      setDeleteError(null)
      setDeleteRequest({ seats: targets })
    },
    [seats],
  )

  const handleCopySelection = useCallback(() => {
    const selected = seats
      .filter((seat) => selectedUuids.has(seat.uuid))
      .sort((a, b) => (a.pos_y - b.pos_y) || (a.pos_x - b.pos_x) || a.label.localeCompare(b.label))

    if (selected.length === 0) return

    setClipboard(
      selected.map((seat) => ({
        label: seat.label,
        sector_name: seat.sector_name,
        kind: seat.kind,
        capacity: seat.capacity,
        pos_x: seat.pos_x,
        pos_y: seat.pos_y,
        width: seat.width,
        height: seat.height,
        geometry_points: seat.geometry_points,
        is_accessible: seat.is_accessible,
        status: seat.status,
      })),
    )
    setPasteCount(0)
  }, [seats, selectedUuids])

  const handlePasteSelection = useCallback(async () => {
    if (!canManageSeats || clipboard.length === 0 || isPasting) return

    const offsetStep = pasteCount + 1
    const delta = PASTE_OFFSET * offsetStep
    const usedLabels = new Set(seats.map((seat) => seat.label))
    const sequenceState = buildSequenceState(seats)

    setIsPasting(true)
    setActionError(null)

    try {
      const createdSeats: Seat[] = []

      for (const item of clipboard) {
        const created = await seatService.createSeat(venueUuid, {
          label: buildPastedLabel(item.label, usedLabels, sequenceState),
          sector_name: item.sector_name,
          kind: item.kind,
          capacity: item.capacity,
          pos_x: item.pos_x + delta,
          pos_y: item.pos_y + delta,
          width: item.width,
          height: item.height,
          geometry_points: item.geometry_points.map((point) => ({ x: point.x + delta, y: point.y + delta })),
          is_accessible: item.is_accessible,
          status: item.status,
        })

        createdSeats.push(created)
      }

      setSeats((prev) => [...prev, ...createdSeats])
      setSelectedUuids(new Set(createdSeats.map((seat) => seat.uuid)))
      setPanelOpen(true)
      setPasteCount((prev) => prev + 1)
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível colar os componentes do mapa agora.'))
    } finally {
      setIsPasting(false)
    }
  }, [canManageSeats, clipboard, isPasting, pasteCount, seats, venueUuid])

  const handleSeatContextMenu = useCallback(
    (uuid: string, mouseX: number, mouseY: number) => {
      const isSelected = selectedUuids.has(uuid)
      if (!isSelected) {
        setSelectedUuids(new Set([uuid]))
      }

      setContextMenu({
        seatUuid: uuid,
        mouseX,
        mouseY,
      })
    },
    [selectedUuids],
  )

  const handleRenameFromContextMenu = useCallback(() => {
    if (!contextMenu) return
    const seat = seats.find((item) => item.uuid === contextMenu.seatUuid)
    if (!seat) return

    setRenameDialog({ seatUuid: seat.uuid, value: seat.label })
    setContextMenu(null)
  }, [contextMenu, seats])

  const handleCopyFromContextMenu = useCallback(() => {
    if (!contextMenu) return
    if (!selectedUuids.has(contextMenu.seatUuid)) {
      const seat = seats.find((item) => item.uuid === contextMenu.seatUuid)
      if (!seat) return
      setClipboard([
        {
          label: seat.label,
          sector_name: seat.sector_name,
          kind: seat.kind,
          capacity: seat.capacity,
          pos_x: seat.pos_x,
          pos_y: seat.pos_y,
          width: seat.width,
          height: seat.height,
          geometry_points: seat.geometry_points,
          is_accessible: seat.is_accessible,
          status: seat.status,
        },
      ])
      setPasteCount(0)
      setSelectedUuids(new Set([seat.uuid]))
      setContextMenu(null)
      return
    }

    handleCopySelection()
    setContextMenu(null)
  }, [contextMenu, handleCopySelection, seats, selectedUuids])

  const handleDeleteFromContextMenu = useCallback(() => {
    if (!contextMenu) return
    const seat = seats.find((item) => item.uuid === contextMenu.seatUuid)
    if (!seat) return

    if (selectedUuids.has(seat.uuid) && selectedUuids.size > 1) {
      handleDeleteSelection(selectedUuids)
    } else {
      handleDelete(seat)
    }
    setContextMenu(null)
  }, [contextMenu, handleDelete, handleDeleteSelection, seats, selectedUuids])

  async function handleConfirmDelete() {
    if (!deleteRequest) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      const targetUuids = new Set(deleteRequest.seats.map((seat) => seat.uuid))

      for (const seat of deleteRequest.seats) {
        await seatService.deleteSeat(venueUuid, seat.uuid)
      }

      setSeats((prev) => prev.filter((seat) => !targetUuids.has(seat.uuid)))
      setSelectedUuids((prev) => {
        const next = new Set(prev)
        for (const uuid of targetUuids) {
          next.delete(uuid)
        }
        return next
      })
      setDeleteRequest(null)
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, deleteRequest.seats.length > 1 ? 'Não foi possível excluir os componentes agora.' : 'Não foi possível excluir o assento agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  async function handleBatchApply(payload: Partial<SeatPayload>) {
    setBatchSaving(true)
    setBatchError(null)
    const targets = Array.from(selectedUuids)

    try {
      for (const uuid of targets) {
        await seatService.updateSeat(venueUuid, uuid, payload)
      }
      setSeats((prev) => prev.map((seat) => (selectedUuids.has(seat.uuid) ? ({ ...seat, ...payload } as Seat) : seat)))
      setBatchOpen(false)
    } catch (error) {
      setBatchError(getApiErrorMessage(error, 'Não foi possível aplicar a edição em lote a todos os assentos.'))
    } finally {
      setBatchSaving(false)
    }
  }

  async function handlePublish() {
    setIsPublishing(true)
    setPublishError(null)
    setPublishSuccess(null)

    try {
      await venueService.publishVenueMap(venueUuid)
      setPublishConfirmOpen(false)
      await reloadEditorData()
      setSelectedUuids(new Set())
      setPublishSuccess('Mapa publicado com sucesso. O editor já está trabalhando sobre o novo rascunho.')
    } catch (error) {
      setPublishError(getApiErrorMessage(error, 'Não foi possível publicar o mapa agora.'))
    } finally {
      setIsPublishing(false)
    }
  }

  // Setas do teclado movem o assento único selecionado (1 unidade; Shift+seta, 10) —
  // fallback de acessibilidade sem mouse. Ignorado quando o foco está num campo de texto.
  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      const target = event.target as HTMLElement | null
      if (target && ['INPUT', 'TEXTAREA'].includes(target.tagName)) return

      const isCopyShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'c'
      if (isCopyShortcut) {
        if (isPasting) return
        if (selectedUuids.size === 0) return
        event.preventDefault()
        handleCopySelection()
        return
      }

      const isPasteShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'v'
      if (isPasteShortcut) {
        if (clipboard.length === 0 || !canManageSeats) return
        event.preventDefault()
        void handlePasteSelection()
        return
      }

      if (isPasting) return

      if (event.key === 'Delete' && selectedUuids.size > 0) {
        event.preventDefault()
        handleDeleteSelection(selectedUuids)
        return
      }

      if (event.key === 'Escape') {
        handleCancelCreate()
        setContextMenu(null)
        return
      }

      if (selectedUuids.size !== 1) return

      const step = event.shiftKey ? 10 : 1
      let dx = 0
      let dy = 0
      if (event.key === 'ArrowUp') dy = -step
      else if (event.key === 'ArrowDown') dy = step
      else if (event.key === 'ArrowLeft') dx = -step
      else if (event.key === 'ArrowRight') dx = step
      else return

      event.preventDefault()
      const uuid = Array.from(selectedUuids)[0]
      const seat = seats.find((item) => item.uuid === uuid)
      if (!seat) return
      commitSeatDebounced(uuid, { pos_x: seat.pos_x + dx, pos_y: seat.pos_y + dy })
    }

    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [canManageSeats, clipboard.length, commitSeatDebounced, handleCancelCreate, handleCopySelection, handleDeleteSelection, handlePasteSelection, isPasting, seats, selectedUuids])

  const previewSeats = useMemo<SeatMapViewerSeat[]>(
    () =>
      seats.map((seat) => ({
        uuid: seat.uuid,
        pos_x: seat.pos_x,
        pos_y: seat.pos_y,
        kind: seat.kind,
        width: seat.width,
        height: seat.height,
        geometryPoints: seat.geometry_points,
        label: seat.label,
        isAccessible: seat.is_accessible,
        visualState: seat.status === 'disponivel' ? 'available' : 'unavailable',
        disabled: seat.status !== 'disponivel',
        ariaLabel: `${seat.label}${seat.sector_name ? `, ${seat.sector_name}` : ''}`,
      })),
    [seats],
  )

  function handleZoom(factor: number) {
    setViewBox((current) => zoomViewBox(current, baseViewBoxRef.current, factor))
  }

  function handleZoomReset() {
    setViewBox(clampViewBox(baseViewBoxRef.current, baseViewBoxRef.current))
  }

  if (isLoading) {
    return (
      <Box sx={{ p: 2 }}>
        <Skeleton variant="text" width={260} height={40} />
        <Skeleton variant="rounded" height={560} sx={{ mt: 2, borderRadius: 'var(--pt-radius-lg)' }} />
      </Box>
    )
  }

  if (loadError) {
    return (
      <Box sx={{ p: 2 }}>
        <Alert severity="error" action={<Button color="inherit" size="small" onClick={() => navigate(0)}>Tentar novamente</Button>}>
          {loadError}
        </Alert>
      </Box>
    )
  }

  return (
    <Box sx={{ width: '100%', maxWidth: 1600, mx: 'auto' }}>
      <PageHeader
        title={`Mapa de ${venue?.name ?? 'local'}`}
        subtitle="Arraste, posicione e edite assentos, mesas, áreas e camarotes do rascunho atual."
        backLink={{ label: 'Locais', to: '/locais' }}
        action={
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <Button variant="outlined" startIcon={<VisibilityOutlinedIcon />} onClick={() => setPreviewOpen(true)}>
              Visualizar mapa final
            </Button>
            {can(ACCESS.venuesUpdate) ? (
              <Button variant="outlined" startIcon={<PublishOutlinedIcon />} onClick={() => setPublishConfirmOpen(true)} disabled={isPublishing}>
                Publicar versão
              </Button>
            ) : null}
            {canManageSeats ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={(event) => setAddMenuAnchor(event.currentTarget)} disabled={isPasting}>
                Adicionar
              </Button>
            ) : null}
          </Stack>
        }
      />

      <Menu anchorEl={addMenuAnchor} open={Boolean(addMenuAnchor) && !isPasting} onClose={() => setAddMenuAnchor(null)}>
        {SEAT_KIND_OPTIONS.map((option) => (
          <MenuItem
            key={option.value}
            onClick={() => handleStartCreateKind(option.value)}
          >
            {option.label}
          </MenuItem>
        ))}
      </Menu>

      {actionError ? (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setActionError(null)}>
          {actionError}
        </Alert>
      ) : null}

      {publishSuccess ? (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setPublishSuccess(null)}>
          {publishSuccess}
        </Alert>
      ) : null}

      {seatsTotal > seats.length ? (
        <Alert severity="warning" sx={{ mb: 2 }}>
          Mostrando os primeiros {seats.length} de {seatsTotal} pontos deste mapa. Use a busca ou a lista/grid para localizar os demais.
        </Alert>
      ) : null}

      {pendingCreateKind ? (
        <Alert
          severity="info"
          sx={{ mb: 2 }}
          action={
            <IconButton size="small" onClick={handleCancelCreate} aria-label="Cancelar posicionamento">
              <CloseIcon fontSize="small" />
            </IconButton>
          }
        >
          {pendingCreateKind === 'area'
            ? 'Clique ponto a ponto para demarcar a área no mapa. Feche a geometria clicando novamente sobre o primeiro ponto. Pressione Esc para cancelar.'
            : <>Clique no mapa para posicionar um novo <strong>{KIND_LABEL[pendingCreateKind]}</strong>. Pressione Esc para cancelar.</>}
        </Alert>
      ) : null}

      {clipboard.length > 0 ? (
        <Alert severity="success" sx={{ mb: 2 }}>
          {clipboard.length} componente{clipboard.length > 1 ? 's copiados' : ' copiado'} no mapa. Use <strong>Ctrl/Cmd+V</strong> ou o botão <strong>Colar</strong> para duplicar a seleção.
        </Alert>
      ) : null}

      <Box sx={{ display: 'flex', gap: 2, alignItems: 'stretch' }}>
        <Paper
          variant="outlined"
          sx={{ ...ELEVATED_SURFACE_SX, flex: 1, minWidth: 0, position: 'relative', height: { md: 560, lg: 640 }, overflow: 'hidden' }}
        >
          {seats.length === 0 && !pendingCreateKind ? (
            <Box sx={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1, pointerEvents: 'none' }}>
              <EmptyState
                icon={<EventSeatOutlinedIcon sx={{ fontSize: 32 }} />}
                title="Nenhum ponto no mapa ainda"
                description='Clique em "Adicionar" e escolha um tipo para posicionar o primeiro ponto no mapa.'
              />
            </Box>
          ) : null}

          <MapCanvas
            seats={seats}
            selectedUuids={selectedUuids}
            pendingCreateKind={pendingCreateKind}
            pendingAreaPoints={pendingAreaPoints}
            pendingAreaPointer={pendingAreaPointer}
            viewBox={viewBox}
            baseViewBox={baseViewBoxRef.current}
            backgroundImageUrl={venue?.background_image_url}
            disabled={isPasting}
            onViewBoxChange={setViewBox}
            onSelectReplace={handleSelectReplace}
            onMoveSeat={handleMoveSeat}
            onCommitMove={handleCommitMove}
            onMoveSelection={handleMoveSelection}
            onCommitSelectionMove={handleCommitSelectionMove}
            onCreateAt={handleCreateAt}
            onAreaDraftPointAdd={handleAreaDraftPointAdd}
            onAreaDraftPointMove={handleAreaDraftPointMove}
            onAreaDraftComplete={handleAreaDraftComplete}
            onSeatContextMenu={handleSeatContextMenu}
          />

          {isPasting ? (
            <Box
              sx={{
                position: 'absolute',
                inset: 0,
                zIndex: 4,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexDirection: 'column',
                gap: 1.5,
                background: 'color-mix(in srgb, var(--pt-surface) 76%, transparent)',
                backdropFilter: 'blur(2px)',
              }}
            >
              <CircularProgress size={32} />
              <Box sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)' }}>Colando componentes no mapa…</Box>
            </Box>
          ) : null}

          <Stack direction="row" spacing={0.5} sx={{ position: 'absolute', bottom: 12, left: 12, background: 'var(--pt-surface)', border: '1px solid var(--pt-border)', borderRadius: 'var(--pt-radius-md)', p: 0.5 }}>
            <Tooltip title="Aumentar zoom" arrow>
              <IconButton size="small" onClick={() => handleZoom(1 / 1.25)} sx={{ minWidth: 40, minHeight: 40 }} aria-label="Aumentar zoom">
                <ZoomInOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Diminuir zoom" arrow>
              <IconButton size="small" onClick={() => handleZoom(1.25)} sx={{ minWidth: 40, minHeight: 40 }} aria-label="Diminuir zoom">
                <ZoomOutOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Redefinir zoom" arrow>
              <IconButton size="small" onClick={handleZoomReset} sx={{ minWidth: 40, minHeight: 40 }} aria-label="Redefinir zoom">
                <CenterFocusStrongOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>

          <Tooltip title={panelOpen ? 'Ocultar painel lateral' : 'Mostrar painel lateral'} arrow>
            <IconButton
              onClick={() => setPanelOpen((prev) => !prev)}
              sx={{ position: 'absolute', top: 12, right: 12, minWidth: 40, minHeight: 40, background: 'var(--pt-surface)', border: '1px solid var(--pt-border)', display: { xs: 'flex', lg: 'none' } }}
              aria-label="Alternar painel lateral"
            >
              <TuneOutlinedIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </Paper>

        {panelOpen ? (
          <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, width: { md: 300, lg: 340 }, flexShrink: 0, height: { md: 560, lg: 640 }, overflow: 'hidden' }}>
            <SeatInspector
              seats={seats}
              selectedUuids={selectedUuids}
              searchTerm={searchTerm}
              onSearchTermChange={setSearchTerm}
              onSelectSingle={(uuid) => setSelectedUuids(new Set([uuid]))}
              onCommit={handleInspectorCommit}
              onDelete={handleDelete}
              onOpenBatchEdit={() => {
                if (isPasting) return
                setBatchError(null)
                setBatchOpen(true)
              }}
            />
          </Paper>
        ) : null}
      </Box>

      <ConfirmDeleteDialog
        open={deleteRequest !== null}
        title={deleteRequest && deleteRequest.seats.length > 1 ? 'Excluir componentes' : 'Excluir assento'}
        itemLabel={deleteRequest ? (deleteRequest.seats.length > 1 ? `${deleteRequest.seats.length} componentes selecionados` : deleteRequest.seats[0]?.label ?? null) : null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteRequest(null)}
        onConfirm={() => void handleConfirmDelete()}
      />

      <Menu
        open={contextMenu !== null && !isPasting}
        onClose={() => setContextMenu(null)}
        anchorReference="anchorPosition"
        anchorPosition={contextMenu ? { top: contextMenu.mouseY, left: contextMenu.mouseX } : undefined}
      >
        <MenuItem onClick={handleCopyFromContextMenu}>Copiar</MenuItem>
        <MenuItem
          onClick={() => {
            setContextMenu(null)
            void handlePasteSelection()
          }}
          disabled={!canManageSeats || clipboard.length === 0 || isPasting}
        >
          Colar
        </MenuItem>
        <MenuItem onClick={handleRenameFromContextMenu}>Editar código</MenuItem>
        <MenuItem onClick={handleDeleteFromContextMenu} sx={{ color: 'var(--pt-danger)' }}>
          Remover item
        </MenuItem>
      </Menu>

      <BatchEditDialog
        open={batchOpen}
        count={selectedUuids.size}
        isSaving={batchSaving}
        error={batchError}
        onCancel={() => setBatchOpen(false)}
        onApply={(payload) => void handleBatchApply(payload)}
      />

      <Dialog open={renameDialog !== null} onClose={() => setRenameDialog(null)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 600 }}>Editar código</DialogTitle>
        <DialogContent>
          <TextField
            autoFocus
            fullWidth
            label="Código"
            size="small"
            value={renameDialog?.value ?? ''}
            onChange={(event) =>
              setRenameDialog((current) => (current ? { ...current, value: event.target.value } : current))
            }
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={() => setRenameDialog(null)} color="inherit">
            Cancelar
          </Button>
          <Button
            variant="contained"
            onClick={() => {
              if (!renameDialog) return
              const value = renameDialog.value.trim()
              if (!value) return
              commitSeat(renameDialog.seatUuid, { label: value })
              setRenameDialog(null)
            }}
          >
            Salvar
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={previewOpen} onClose={() => setPreviewOpen(false)} maxWidth="lg" fullWidth>
        <DialogTitle sx={{ fontWeight: 600 }}>Visualização final do mapa</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ color: 'var(--pt-text)', mb: 2 }}>
            Prévia do que o cliente verá no momento da compra. O mapa abaixo é renderizado com a mesma geometria atual do rascunho.
          </DialogContentText>
          <SeatMapViewer
            seats={previewSeats}
            mapWidth={venue?.width ?? DEFAULT_PLAN.width}
            mapHeight={venue?.height ?? DEFAULT_PLAN.height}
            backgroundImageUrl={venue?.background_image_url}
            onSelectSeat={() => undefined}
            readOnly
          />
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={() => setPreviewOpen(false)} variant="contained">
            Fechar
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={publishConfirmOpen} onClose={isPublishing ? undefined : () => setPublishConfirmOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 600 }}>Publicar versão do mapa</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ color: 'var(--pt-text)' }}>
            O rascunho atual será publicado e passa a valer para vendas e check-in. Você poderá continuar editando um novo rascunho depois.
          </DialogContentText>
          {publishError ? <Alert severity="error" sx={{ mt: 2 }}>{publishError}</Alert> : null}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={() => setPublishConfirmOpen(false)} disabled={isPublishing} color="inherit">
            Cancelar
          </Button>
          <Button onClick={() => void handlePublish()} disabled={isPublishing} variant="contained">
            {isPublishing ? 'Publicando…' : 'Publicar versão'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  )
}
