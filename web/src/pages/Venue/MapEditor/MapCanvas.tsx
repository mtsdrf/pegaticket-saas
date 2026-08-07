import { useCallback, useEffect, useRef, useState } from 'react'
import type { PointerEvent as ReactPointerEvent } from 'react'
import type { Seat, SeatKind } from '../../../types/venue'
import { KIND_GEOMETRY, STATUS_COLOR_VAR, normalizeGeometryPoints, polygonBounds, resolveSeatGeometry, seatVisualBounds } from './mapVisuals'
import { clientToSvgPoint, zoomViewBox, type ViewBox } from './viewBox'

const CLICK_THRESHOLD_PX = 4
const AREA_CLOSE_DISTANCE = 14

interface MapCanvasProps {
  seats: Seat[]
  selectedUuids: Set<string>
  pendingCreateKind: SeatKind | null
  pendingAreaPoints: Array<{ x: number; y: number }>
  pendingAreaPointer: { x: number; y: number } | null
  disabled?: boolean
  viewBox: ViewBox
  baseViewBox: ViewBox
  backgroundImageUrl?: string | null
  onViewBoxChange: (vb: ViewBox) => void
  onSelectReplace: (uuids: string[]) => void
  onMoveSeat: (uuid: string, x: number, y: number) => void
  onCommitMove: (uuid: string, x: number, y: number) => void
  onMoveSelection: (updates: Array<{ uuid: string; x: number; y: number }>) => void
  onCommitSelectionMove: (updates: Array<{ uuid: string; x: number; y: number }>) => void
  onCreateAt: (kind: SeatKind, x: number, y: number) => void
  onAreaDraftPointAdd: (point: { x: number; y: number }) => void
  onAreaDraftPointMove: (point: { x: number; y: number } | null) => void
  onAreaDraftComplete: () => void
  onSeatContextMenu: (uuid: string, clientX: number, clientY: number) => void
}

type DragState =
  | { mode: 'idle' }
  | { mode: 'pan'; startClientX: number; startClientY: number; startViewBox: ViewBox }
  | { mode: 'seat'; uuid: string; startSeatX: number; startSeatY: number; startClientX: number; startClientY: number; moved: boolean }
  | {
      mode: 'selection'
      startClientX: number
      startClientY: number
      moved: boolean
      seats: Array<{ uuid: string; startX: number; startY: number }>
    }
  | { mode: 'rubber'; startX: number; startY: number; currentX: number; currentY: number }
  | { mode: 'clickCheck'; startClientX: number; startClientY: number }

export function MapCanvas({
  seats,
  selectedUuids,
  pendingCreateKind,
  pendingAreaPoints,
  pendingAreaPointer,
  disabled = false,
  viewBox,
  baseViewBox,
  backgroundImageUrl,
  onViewBoxChange,
  onSelectReplace,
  onMoveSeat,
  onCommitMove,
  onMoveSelection,
  onCommitSelectionMove,
  onCreateAt,
  onAreaDraftPointAdd,
  onAreaDraftPointMove,
  onAreaDraftComplete,
  onSeatContextMenu,
}: MapCanvasProps) {
  const svgRef = useRef<SVGSVGElement | null>(null)
  const dragRef = useRef<DragState>({ mode: 'idle' })
  const [rubberRect, setRubberRect] = useState<{ x: number; y: number; w: number; h: number } | null>(null)

  const findSeatUuid = useCallback((target: EventTarget | null): string | null => {
    if (!(target instanceof Element)) return null
    const el = target.closest('[data-seat-uuid]')
    return el ? el.getAttribute('data-seat-uuid') : null
  }, [])

  const handlePointerDown = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      if (!svg || disabled) return

      if (event.button === 1) {
        event.preventDefault()
        event.currentTarget.setPointerCapture(event.pointerId)
        dragRef.current = { mode: 'pan', startClientX: event.clientX, startClientY: event.clientY, startViewBox: viewBox }
        return
      }

      if (event.button !== 0) return
      event.currentTarget.setPointerCapture(event.pointerId)

      if (pendingCreateKind === 'area') {
        dragRef.current = { mode: 'clickCheck', startClientX: event.clientX, startClientY: event.clientY }
        return
      }

      const seatUuid = findSeatUuid(event.target)

      if (seatUuid) {
        const seat = seats.find((item) => item.uuid === seatUuid)
        if (!seat) return

        if (selectedUuids.has(seatUuid) && selectedUuids.size > 1) {
          dragRef.current = {
            mode: 'selection',
            startClientX: event.clientX,
            startClientY: event.clientY,
            moved: false,
            seats: seats
              .filter((item) => selectedUuids.has(item.uuid))
              .map((item) => ({ uuid: item.uuid, startX: item.pos_x, startY: item.pos_y })),
          }
          return
        }

        if (!selectedUuids.has(seatUuid)) {
          onSelectReplace([seatUuid])
        }

        dragRef.current = {
          mode: 'seat',
          uuid: seatUuid,
          startSeatX: seat.pos_x,
          startSeatY: seat.pos_y,
          startClientX: event.clientX,
          startClientY: event.clientY,
          moved: false,
        }
        return
      }

      if (pendingCreateKind) {
        dragRef.current = { mode: 'clickCheck', startClientX: event.clientX, startClientY: event.clientY }
        return
      }

      const point = clientToSvgPoint(svg, event.clientX, event.clientY)
      dragRef.current = { mode: 'rubber', startX: point.x, startY: point.y, currentX: point.x, currentY: point.y }
      setRubberRect({ x: point.x, y: point.y, w: 0, h: 0 })
    },
    [disabled, findSeatUuid, onSelectReplace, pendingCreateKind, seats, selectedUuids, viewBox],
  )

  const handleContextMenu = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      if (disabled) return
      const seatUuid = findSeatUuid(event.target)
      if (!seatUuid) return
      event.preventDefault()
      onSeatContextMenu(seatUuid, event.clientX, event.clientY)
    },
    [disabled, findSeatUuid, onSeatContextMenu],
  )

  const handlePointerMove = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      const state = dragRef.current
      if (!svg || state.mode === 'idle') return

      if (pendingCreateKind === 'area') {
        onAreaDraftPointMove(clientToSvgPoint(svg, event.clientX, event.clientY))
      }

      if (state.mode === 'pan') {
        const scale = viewBox.w / svg.clientWidth
        const dx = (event.clientX - state.startClientX) * scale
        const dy = (event.clientY - state.startClientY) * scale
        onViewBoxChange({ ...state.startViewBox, x: state.startViewBox.x - dx, y: state.startViewBox.y - dy })
        return
      }

      if (state.mode === 'seat') {
        const scale = viewBox.w / svg.clientWidth
        const dx = (event.clientX - state.startClientX) * scale
        const dy = (event.clientY - state.startClientY) * scale
        if (Math.abs(event.clientX - state.startClientX) > CLICK_THRESHOLD_PX || Math.abs(event.clientY - state.startClientY) > CLICK_THRESHOLD_PX) {
          state.moved = true
        }
        onMoveSeat(state.uuid, state.startSeatX + dx, state.startSeatY + dy)
        return
      }

      if (state.mode === 'selection') {
        const scale = viewBox.w / svg.clientWidth
        const dx = (event.clientX - state.startClientX) * scale
        const dy = (event.clientY - state.startClientY) * scale
        if (Math.abs(event.clientX - state.startClientX) > CLICK_THRESHOLD_PX || Math.abs(event.clientY - state.startClientY) > CLICK_THRESHOLD_PX) {
          state.moved = true
        }
        onMoveSelection(
          state.seats.map((seat) => ({
            uuid: seat.uuid,
            x: seat.startX + dx,
            y: seat.startY + dy,
          })),
        )
        return
      }

      if (state.mode === 'rubber') {
        const point = clientToSvgPoint(svg, event.clientX, event.clientY)
        state.currentX = point.x
        state.currentY = point.y
        setRubberRect({
          x: Math.min(state.startX, point.x),
          y: Math.min(state.startY, point.y),
          w: Math.abs(point.x - state.startX),
          h: Math.abs(point.y - state.startY),
        })
      }
    },
    [onMoveSeat, onMoveSelection, onViewBoxChange, viewBox.w],
  )

  const handlePointerUp = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      const state = dragRef.current
      dragRef.current = { mode: 'idle' }
      if (event.currentTarget.hasPointerCapture(event.pointerId)) {
        event.currentTarget.releasePointerCapture(event.pointerId)
      }
      if (!svg) return

      if (state.mode === 'seat') {
        const seat = seats.find((item) => item.uuid === state.uuid)
        if (seat && state.moved) {
          onCommitMove(state.uuid, seat.pos_x, seat.pos_y)
        }
        return
      }

      if (state.mode === 'selection') {
        if (state.moved) {
          onCommitSelectionMove(
            state.seats
              .map((item) => {
                const seat = seats.find((current) => current.uuid === item.uuid)
                if (!seat) return null
                return { uuid: item.uuid, x: seat.pos_x, y: seat.pos_y }
              })
              .filter((item): item is { uuid: string; x: number; y: number } => item !== null),
          )
        }
        return
      }

      if (state.mode === 'rubber') {
        const scale = viewBox.w / Math.max(svg.clientWidth, 1)
        const movedEnough =
          Math.abs(state.currentX - state.startX) > CLICK_THRESHOLD_PX * scale ||
          Math.abs(state.currentY - state.startY) > CLICK_THRESHOLD_PX * scale

        if (!movedEnough) {
          onSelectReplace([])
          setRubberRect(null)
          return
        }

        const rect = { x1: Math.min(state.startX, state.currentX), y1: Math.min(state.startY, state.currentY), x2: Math.max(state.startX, state.currentX), y2: Math.max(state.startY, state.currentY) }
        const hits = seats
          .filter((seat) => {
            const bounds = seatVisualBounds(seat.kind, seat.pos_x, seat.pos_y, seat.width, seat.height, seat.geometry_points)
            return bounds.left >= rect.x1 && bounds.right <= rect.x2 && bounds.top >= rect.y1 && bounds.bottom <= rect.y2
          })
          .map((seat) => seat.uuid)
        onSelectReplace(hits)
        setRubberRect(null)
        return
      }

      if (state.mode === 'clickCheck') {
        const movedPx = Math.hypot(event.clientX - state.startClientX, event.clientY - state.startClientY)
        if (movedPx <= CLICK_THRESHOLD_PX && pendingCreateKind === 'area') {
          const point = clientToSvgPoint(svg, event.clientX, event.clientY)
          const firstPoint = pendingAreaPoints[0]
          if (
            firstPoint &&
            pendingAreaPoints.length >= 3 &&
            Math.hypot(firstPoint.x - point.x, firstPoint.y - point.y) <= AREA_CLOSE_DISTANCE
          ) {
            onAreaDraftComplete()
          } else {
            onAreaDraftPointAdd({ x: Math.round(point.x), y: Math.round(point.y) })
          }
        } else if (movedPx <= CLICK_THRESHOLD_PX && pendingCreateKind) {
          const point = clientToSvgPoint(svg, event.clientX, event.clientY)
          onCreateAt(pendingCreateKind, Math.round(point.x), Math.round(point.y))
        }
        return
      }

      if (state.mode === 'pan') {
        return
      }
    },
    [onAreaDraftComplete, onAreaDraftPointAdd, onCommitMove, onCommitSelectionMove, onCreateAt, onSelectReplace, pendingAreaPoints, pendingCreateKind, seats, viewBox.w],
  )

  const handlePointerCancel = useCallback((event: ReactPointerEvent<SVGSVGElement>) => {
    dragRef.current = { mode: 'idle' }
    setRubberRect(null)
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId)
    }
  }, [])

  // React anexa `onWheel` como listener passivo por padrão — preventDefault() não
  // funciona nele (zoom rolaria a página junto). Listener nativo não-passivo é o
  // único jeito de bloquear o scroll da página enquanto o zoom do canvas acontece.
  useEffect(() => {
    const svg = svgRef.current
    if (!svg) return
    const listener = (event: WheelEvent) => {
      event.preventDefault()
      const point = clientToSvgPoint(svg, event.clientX, event.clientY)
      const factor = event.deltaY > 0 ? 1.15 : 1 / 1.15
      onViewBoxChange(zoomViewBox(viewBox, baseViewBox, factor, point))
    }
    svg.addEventListener('wheel', listener, { passive: false })
    return () => svg.removeEventListener('wheel', listener)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [baseViewBox, onViewBoxChange, viewBox])

  return (
    <svg
      ref={svgRef}
      viewBox={`${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onPointerUp={handlePointerUp}
      onPointerCancel={handlePointerCancel}
      onContextMenu={handleContextMenu}
      role="application"
      aria-label="Mapa visual do local"
      style={{
        width: '100%',
        height: '100%',
        display: 'block',
        background: 'var(--pt-surface-soft)',
        cursor: disabled ? 'progress' : pendingCreateKind ? 'crosshair' : 'default',
        touchAction: 'none',
        borderRadius: 'var(--pt-radius-lg)',
        pointerEvents: disabled ? 'none' : 'auto',
      }}
    >
      {backgroundImageUrl ? (
        <image href={backgroundImageUrl} x={baseViewBox.x} y={baseViewBox.y} width={baseViewBox.w} height={baseViewBox.h} opacity={0.5} preserveAspectRatio="xMidYMid slice" />
      ) : null}

      {seats.map((seat) => {
        const baseGeo = KIND_GEOMETRY[seat.kind]
        const geo = resolveSeatGeometry(seat.kind, seat.width, seat.height)
        const geometryPoints = normalizeGeometryPoints(seat.geometry_points)
        const areaBounds = seat.kind === 'area' ? polygonBounds(geometryPoints) : null
        const isSelected = selectedUuids.has(seat.uuid)
        const color = STATUS_COLOR_VAR[seat.status]

        return (
          <g key={seat.uuid} data-seat-uuid={seat.uuid} style={{ cursor: 'pointer' }}>
            {isSelected ? (
              seat.kind === 'area' && geometryPoints.length >= 3 ? (
                <polygon
                  points={geometryPoints.map((point) => `${point.x},${point.y}`).join(' ')}
                  fill="none"
                  stroke="var(--pt-primary)"
                  strokeWidth={2}
                  strokeDasharray="3 2"
                />
              ) : geo.shape === 'circle' ? (
                <circle cx={seat.pos_x} cy={seat.pos_y} r={geo.width / 2 + 5} fill="none" stroke="var(--pt-primary)" strokeWidth={2} strokeDasharray="3 2" />
              ) : (
                <rect
                  x={seat.pos_x - geo.width / 2 - 5}
                  y={seat.pos_y - geo.height / 2 - 5}
                  width={geo.width + 10}
                  height={geo.height + 10}
                  rx={baseGeo.rx + 4}
                  fill="none"
                  stroke="var(--pt-primary)"
                  strokeWidth={2}
                  strokeDasharray="3 2"
                />
              )
            ) : null}

            {seat.kind === 'area' && geometryPoints.length >= 3 ? (
              <polygon
                points={geometryPoints.map((point) => `${point.x},${point.y}`).join(' ')}
                fill={color}
                fillOpacity={geo.fillOpacity}
                stroke="var(--pt-surface)"
                strokeWidth={1.5}
              />
            ) : geo.shape === 'circle' ? (
              <circle cx={seat.pos_x} cy={seat.pos_y} r={geo.width / 2} fill={color} fillOpacity={geo.fillOpacity} stroke="var(--pt-surface)" strokeWidth={1.5} />
            ) : (
              <rect
                x={seat.pos_x - geo.width / 2}
                y={seat.pos_y - geo.height / 2}
                width={geo.width}
                height={geo.height}
                rx={baseGeo.rx}
                fill={color}
                fillOpacity={geo.fillOpacity}
                stroke="var(--pt-surface)"
                strokeWidth={1.5}
              />
            )}

            {seat.kind === 'camarote' ? (
              <circle cx={seat.pos_x + geo.width / 2 - 6} cy={seat.pos_y - geo.height / 2 + 6} r={5} fill="var(--pt-accent)" stroke="var(--pt-surface)" strokeWidth={1} />
            ) : null}

            {seat.is_accessible ? (
              <circle cx={seat.pos_x - geo.width / 2 + 2} cy={seat.pos_y + geo.height / 2 - 2} r={5.5} fill="var(--pt-info)" stroke="var(--pt-surface)" strokeWidth={1} />
            ) : null}

            <text
              x={seat.pos_x}
              y={seat.kind === 'area' && areaBounds ? areaBounds.bottom + 11 : seat.pos_y + geo.height / 2 + 11}
              textAnchor="middle"
              fontSize={9}
              fill="var(--pt-muted)"
              style={{ pointerEvents: 'none', userSelect: 'none' }}
            >
              {seat.label}
            </text>
          </g>
        )
      })}

      {pendingCreateKind === 'area' && pendingAreaPoints.length > 0 ? (
        <>
          <polyline
            points={
              [...pendingAreaPoints, ...(pendingAreaPointer ? [pendingAreaPointer] : [])]
                .map((point) => `${point.x},${point.y}`)
                .join(' ')
            }
            fill="none"
            stroke="var(--pt-primary)"
            strokeWidth={2}
            strokeDasharray="4 3"
          />
          {pendingAreaPoints.length >= 3 ? (
            <polygon
              points={pendingAreaPoints.map((point) => `${point.x},${point.y}`).join(' ')}
              fill="var(--pt-primary)"
              fillOpacity={0.12}
              stroke="none"
            />
          ) : null}
          {pendingAreaPoints.map((point, index) => (
            <circle
              key={`draft-area-${index}`}
              cx={point.x}
              cy={point.y}
              r={index === 0 ? 6 : 4.5}
              fill={index === 0 ? 'var(--pt-accent)' : 'var(--pt-primary)'}
              stroke="var(--pt-surface)"
              strokeWidth={1.5}
            />
          ))}
        </>
      ) : null}

      {rubberRect ? (
        <rect
          x={rubberRect.x}
          y={rubberRect.y}
          width={rubberRect.w}
          height={rubberRect.h}
          fill="var(--pt-primary)"
          fillOpacity={0.12}
          stroke="var(--pt-primary)"
          strokeDasharray="4 3"
        />
      ) : null}
    </svg>
  )
}
