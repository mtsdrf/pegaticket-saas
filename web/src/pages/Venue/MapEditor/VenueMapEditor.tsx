import AddIcon from '@mui/icons-material/Add'
import CenterFocusStrongOutlinedIcon from '@mui/icons-material/CenterFocusStrongOutlined'
import CloseIcon from '@mui/icons-material/Close'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import PublishOutlinedIcon from '@mui/icons-material/PublishOutlined'
import TuneOutlinedIcon from '@mui/icons-material/TuneOutlined'
import ZoomInOutlinedIcon from '@mui/icons-material/ZoomInOutlined'
import ZoomOutOutlinedIcon from '@mui/icons-material/ZoomOutOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
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
  Tooltip,
} from '@mui/material'
import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../../access/requirements'
import { ConfirmDeleteDialog } from '../../../components/crud/ConfirmDeleteDialog'
import { EmptyState } from '../../../components/layout/EmptyState'
import { PageHeader } from '../../../components/layout/PageHeader'
import { useAccessControl } from '../../../hooks/useAccessControl'
import * as seatService from '../../../services/seatService'
import * as venueService from '../../../services/venueService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { getApiErrorMessage } from '../../../types/api'
import { SEAT_KIND_OPTIONS, type Seat, type SeatKind, type SeatPayload, type Venue } from '../../../types/venue'
import { BatchEditDialog } from './BatchEditDialog'
import { MapCanvas } from './MapCanvas'
import { KIND_LABEL } from './mapVisuals'
import { SeatInspector } from './SeatInspector'
import { clampViewBox, zoomViewBox, type ViewBox } from './viewBox'

const DEFAULT_PLAN = { width: 800, height: 600 }
const SEATS_PAGE_SIZE = 200
const DEBOUNCE_MS = 350

function buildBaseViewBox(venue: Venue | null, seats: Seat[]): ViewBox {
  if (venue?.width && venue?.height) {
    return { x: 0, y: 0, w: venue.width, h: venue.height }
  }

  if (seats.length > 0) {
    const xs = seats.map((s) => s.pos_x)
    const ys = seats.map((s) => s.pos_y)
    const pad = 80
    const minX = Math.min(...xs) - pad
    const minY = Math.min(...ys) - pad
    const maxX = Math.max(...xs) + pad
    const maxY = Math.max(...ys) + pad
    return { x: minX, y: minY, w: Math.max(maxX - minX, 200), h: Math.max(maxY - minY, 200) }
  }

  return { x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height }
}

function defaultLabelFor(kind: SeatKind, seats: Seat[]): string {
  const countOfKind = seats.filter((s) => s.kind === kind).length
  return `${KIND_LABEL[kind]} ${countOfKind + 1}`
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
  const [addMenuAnchor, setAddMenuAnchor] = useState<HTMLElement | null>(null)
  const [panelOpen, setPanelOpen] = useState(true)

  const [viewBox, setViewBox] = useState<ViewBox>({ x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height })
  const baseViewBoxRef = useRef<ViewBox>({ x: 0, y: 0, w: DEFAULT_PLAN.width, h: DEFAULT_PLAN.height })

  const [deleteTarget, setDeleteTarget] = useState<Seat | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const [batchOpen, setBatchOpen] = useState(false)
  const [batchSaving, setBatchSaving] = useState(false)
  const [batchError, setBatchError] = useState<string | null>(null)

  const [publishConfirmOpen, setPublishConfirmOpen] = useState(false)
  const [isPublishing, setIsPublishing] = useState(false)
  const [publishError, setPublishError] = useState<string | null>(null)

  const debounceRef = useRef<Map<string, { payload: Partial<SeatPayload>; timer: ReturnType<typeof setTimeout> }>>(new Map())

  useEffect(() => {
    if (!venueUuid) return
    let cancelled = false
    setIsLoading(true)
    setLoadError(null)

    Promise.all([venueService.getVenue(venueUuid), seatService.listSeats(venueUuid, { per_page: SEATS_PAGE_SIZE })])
      .then(([venueData, seatsResult]) => {
        if (cancelled) return
        setVenue(venueData)
        setSeats(seatsResult.items)
        setSeatsTotal(seatsResult.pagination.total)
        const base = buildBaseViewBox(venueData, seatsResult.items)
        baseViewBoxRef.current = base
        setViewBox(base)
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
  }, [venueUuid])

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
    setSeats((prev) => prev.map((seat) => (seat.uuid === uuid ? { ...seat, pos_x: x, pos_y: y } : seat)))
  }, [])

  const handleCommitMove = useCallback(
    (uuid: string, x: number, y: number) => {
      commitSeat(uuid, { pos_x: x, pos_y: y })
    },
    [commitSeat],
  )

  const handleSelectReplace = useCallback((uuids: string[]) => {
    setSelectedUuids(new Set(uuids))
  }, [])

  const handleSelectToggle = useCallback((uuid: string) => {
    setSelectedUuids((prev) => {
      const next = new Set(prev)
      if (next.has(uuid)) next.delete(uuid)
      else next.add(uuid)
      return next
    })
  }, [])

  const handleCreateAt = useCallback(
    (kind: SeatKind, x: number, y: number) => {
      setPendingCreateKind(null)
      const payload: SeatPayload = {
        label: defaultLabelFor(kind, seats),
        kind,
        pos_x: x,
        pos_y: y,
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
    [seats, venueUuid],
  )

  const handleDelete = useCallback((seat: Seat) => {
    setDeleteError(null)
    setDeleteTarget(seat)
  }, [])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await seatService.deleteSeat(venueUuid, deleteTarget.uuid)
      setSeats((prev) => prev.filter((seat) => seat.uuid !== deleteTarget.uuid))
      setSelectedUuids((prev) => {
        const next = new Set(prev)
        next.delete(deleteTarget.uuid)
        return next
      })
      setDeleteTarget(null)
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível excluir o assento agora.'))
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

    try {
      await venueService.publishVenueMap(venueUuid)
      setPublishConfirmOpen(false)
      const venueData = await venueService.getVenue(venueUuid)
      setVenue(venueData)
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
      if (selectedUuids.size !== 1) return

      const step = event.shiftKey ? 10 : 1
      let dx = 0
      let dy = 0
      if (event.key === 'ArrowUp') dy = -step
      else if (event.key === 'ArrowDown') dy = step
      else if (event.key === 'ArrowLeft') dx = -step
      else if (event.key === 'ArrowRight') dx = step
      else if (event.key === 'Escape') {
        setPendingCreateKind(null)
        return
      } else return

      event.preventDefault()
      const uuid = Array.from(selectedUuids)[0]
      const seat = seats.find((item) => item.uuid === uuid)
      if (!seat) return
      commitSeatDebounced(uuid, { pos_x: seat.pos_x + dx, pos_y: seat.pos_y + dy })
    }

    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [commitSeatDebounced, seats, selectedUuids])

  function handleZoom(factor: number) {
    setViewBox((current) => zoomViewBox(current, baseViewBoxRef.current, factor))
  }

  function handleZoomReset() {
    setViewBox(clampViewBox(baseViewBoxRef.current, baseViewBoxRef.current))
  }

  const publishedInfo = venue?.published_map_version
  const canManageSeats = can(ACCESS.seatsCreate) || can(ACCESS.seatsUpdate)

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
        breadcrumbs={[{ label: 'Locais', to: '/locais' }, { label: venue?.name ?? 'Local' }]}
        action={
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <Chip
              size="small"
              label={publishedInfo ? `Publicado v${publishedInfo.version_number}` : 'Nunca publicado'}
              color={publishedInfo ? 'success' : 'default'}
              variant={publishedInfo ? 'filled' : 'outlined'}
            />
            <Chip size="small" label="Editando rascunho" variant="outlined" />
            {can(ACCESS.venuesUpdate) ? (
              <Button variant="outlined" startIcon={<PublishOutlinedIcon />} onClick={() => setPublishConfirmOpen(true)} disabled={isPublishing}>
                Publicar versão
              </Button>
            ) : null}
            {canManageSeats ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={(event) => setAddMenuAnchor(event.currentTarget)}>
                Adicionar
              </Button>
            ) : null}
          </Stack>
        }
      />

      <Menu anchorEl={addMenuAnchor} open={Boolean(addMenuAnchor)} onClose={() => setAddMenuAnchor(null)}>
        {SEAT_KIND_OPTIONS.map((option) => (
          <MenuItem
            key={option.value}
            onClick={() => {
              setPendingCreateKind(option.value)
              setAddMenuAnchor(null)
            }}
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
            <IconButton size="small" onClick={() => setPendingCreateKind(null)} aria-label="Cancelar posicionamento">
              <CloseIcon fontSize="small" />
            </IconButton>
          }
        >
          Clique no mapa para posicionar um novo <strong>{KIND_LABEL[pendingCreateKind]}</strong>. Pressione Esc para cancelar.
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
            viewBox={viewBox}
            baseViewBox={baseViewBoxRef.current}
            backgroundImageUrl={venue?.background_image_url}
            onViewBoxChange={setViewBox}
            onSelectReplace={handleSelectReplace}
            onSelectToggle={handleSelectToggle}
            onMoveSeat={handleMoveSeat}
            onCommitMove={handleCommitMove}
            onCreateAt={handleCreateAt}
          />

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
                setBatchError(null)
                setBatchOpen(true)
              }}
            />
          </Paper>
        ) : null}
      </Box>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir assento"
        itemLabel={deleteTarget?.label ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />

      <BatchEditDialog
        open={batchOpen}
        count={selectedUuids.size}
        isSaving={batchSaving}
        error={batchError}
        onCancel={() => setBatchOpen(false)}
        onApply={(payload) => void handleBatchApply(payload)}
      />

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
