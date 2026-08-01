import { useCallback, useEffect, useRef, useState } from 'react'
import type { PointerEvent as ReactPointerEvent } from 'react'
import type { Seat, SeatKind } from '../../../types/venue'
import { KIND_GEOMETRY, STATUS_COLOR_VAR, seatBounds } from './mapVisuals'
import { clientToSvgPoint, zoomViewBox, type ViewBox } from './viewBox'

const CLICK_THRESHOLD_PX = 4

interface MapCanvasProps {
  seats: Seat[]
  selectedUuids: Set<string>
  pendingCreateKind: SeatKind | null
  viewBox: ViewBox
  baseViewBox: ViewBox
  backgroundImageUrl?: string | null
  onViewBoxChange: (vb: ViewBox) => void
  onSelectReplace: (uuids: string[]) => void
  onSelectToggle: (uuid: string) => void
  onMoveSeat: (uuid: string, x: number, y: number) => void
  onCommitMove: (uuid: string, x: number, y: number) => void
  onCreateAt: (kind: SeatKind, x: number, y: number) => void
}

type DragState =
  | { mode: 'idle' }
  | { mode: 'pan'; startClientX: number; startClientY: number; startViewBox: ViewBox }
  | { mode: 'seat'; uuid: string; startSeatX: number; startSeatY: number; startClientX: number; startClientY: number; moved: boolean }
  | { mode: 'rubber'; startX: number; startY: number; currentX: number; currentY: number }
  | { mode: 'clickCheck'; startClientX: number; startClientY: number }

export function MapCanvas({
  seats,
  selectedUuids,
  pendingCreateKind,
  viewBox,
  baseViewBox,
  backgroundImageUrl,
  onViewBoxChange,
  onSelectReplace,
  onSelectToggle,
  onMoveSeat,
  onCommitMove,
  onCreateAt,
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
      if (!svg) return
      event.currentTarget.setPointerCapture(event.pointerId)

      const seatUuid = findSeatUuid(event.target)

      if (seatUuid) {
        const seat = seats.find((item) => item.uuid === seatUuid)
        if (!seat) return

        if (event.shiftKey) {
          onSelectToggle(seatUuid)
        } else if (!selectedUuids.has(seatUuid)) {
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

      if (event.shiftKey) {
        const point = clientToSvgPoint(svg, event.clientX, event.clientY)
        dragRef.current = { mode: 'rubber', startX: point.x, startY: point.y, currentX: point.x, currentY: point.y }
        setRubberRect({ x: point.x, y: point.y, w: 0, h: 0 })
        return
      }

      if (pendingCreateKind) {
        dragRef.current = { mode: 'clickCheck', startClientX: event.clientX, startClientY: event.clientY }
        return
      }

      dragRef.current = { mode: 'pan', startClientX: event.clientX, startClientY: event.clientY, startViewBox: viewBox }
    },
    [findSeatUuid, onSelectReplace, onSelectToggle, pendingCreateKind, seats, selectedUuids, viewBox],
  )

  const handlePointerMove = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      const state = dragRef.current
      if (!svg || state.mode === 'idle') return

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
    [onMoveSeat, onViewBoxChange, viewBox.w],
  )

  const handlePointerUp = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      const state = dragRef.current
      dragRef.current = { mode: 'idle' }
      if (!svg) return

      if (state.mode === 'seat') {
        const seat = seats.find((item) => item.uuid === state.uuid)
        if (seat && state.moved) {
          onCommitMove(state.uuid, seat.pos_x, seat.pos_y)
        }
        return
      }

      if (state.mode === 'rubber') {
        const rect = { x1: Math.min(state.startX, state.currentX), y1: Math.min(state.startY, state.currentY), x2: Math.max(state.startX, state.currentX), y2: Math.max(state.startY, state.currentY) }
        const hits = seats
          .filter((seat) => {
            const bounds = seatBounds(seat.kind, seat.pos_x, seat.pos_y)
            return bounds.left >= rect.x1 && bounds.right <= rect.x2 && bounds.top >= rect.y1 && bounds.bottom <= rect.y2
          })
          .map((seat) => seat.uuid)
        onSelectReplace(hits)
        setRubberRect(null)
        return
      }

      if (state.mode === 'clickCheck') {
        const movedPx = Math.hypot(event.clientX - state.startClientX, event.clientY - state.startClientY)
        if (movedPx <= CLICK_THRESHOLD_PX && pendingCreateKind) {
          const point = clientToSvgPoint(svg, event.clientX, event.clientY)
          onCreateAt(pendingCreateKind, Math.round(point.x), Math.round(point.y))
        }
        return
      }

      if (state.mode === 'pan') {
        const movedPx = Math.hypot(event.clientX - state.startClientX, event.clientY - state.startClientY)
        if (movedPx <= CLICK_THRESHOLD_PX) {
          onSelectReplace([])
        }
      }
    },
    [onCommitMove, onCreateAt, onSelectReplace, pendingCreateKind, seats],
  )

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
      role="application"
      aria-label="Mapa visual do local"
      style={{
        width: '100%',
        height: '100%',
        display: 'block',
        background: 'var(--pt-surface-soft)',
        cursor: pendingCreateKind ? 'crosshair' : 'grab',
        touchAction: 'none',
        borderRadius: 'var(--pt-radius-lg)',
      }}
    >
      {backgroundImageUrl ? (
        <image href={backgroundImageUrl} x={baseViewBox.x} y={baseViewBox.y} width={baseViewBox.w} height={baseViewBox.h} opacity={0.5} preserveAspectRatio="xMidYMid slice" />
      ) : null}

      {seats.map((seat) => {
        const geo = KIND_GEOMETRY[seat.kind]
        const isSelected = selectedUuids.has(seat.uuid)
        const color = STATUS_COLOR_VAR[seat.status]

        return (
          <g key={seat.uuid} data-seat-uuid={seat.uuid} style={{ cursor: 'grab' }}>
            {isSelected ? (
              geo.shape === 'circle' ? (
                <circle cx={seat.pos_x} cy={seat.pos_y} r={geo.width / 2 + 5} fill="none" stroke="var(--pt-primary)" strokeWidth={2} strokeDasharray="3 2" />
              ) : (
                <rect
                  x={seat.pos_x - geo.width / 2 - 5}
                  y={seat.pos_y - geo.height / 2 - 5}
                  width={geo.width + 10}
                  height={geo.height + 10}
                  rx={geo.rx + 4}
                  fill="none"
                  stroke="var(--pt-primary)"
                  strokeWidth={2}
                  strokeDasharray="3 2"
                />
              )
            ) : null}

            {geo.shape === 'circle' ? (
              <circle cx={seat.pos_x} cy={seat.pos_y} r={geo.width / 2} fill={color} fillOpacity={geo.fillOpacity} stroke="var(--pt-surface)" strokeWidth={1.5} />
            ) : (
              <rect
                x={seat.pos_x - geo.width / 2}
                y={seat.pos_y - geo.height / 2}
                width={geo.width}
                height={geo.height}
                rx={geo.rx}
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

            <text x={seat.pos_x} y={seat.pos_y + geo.height / 2 + 11} textAnchor="middle" fontSize={9} fill="var(--pt-muted)" style={{ pointerEvents: 'none', userSelect: 'none' }}>
              {seat.label}
            </text>
          </g>
        )
      })}

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
