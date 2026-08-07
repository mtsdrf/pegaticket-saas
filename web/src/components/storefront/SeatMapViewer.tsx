import AccessibleOutlinedIcon from '@mui/icons-material/AccessibleOutlined'
import CenterFocusStrongOutlinedIcon from '@mui/icons-material/CenterFocusStrongOutlined'
import ZoomInOutlinedIcon from '@mui/icons-material/ZoomInOutlined'
import ZoomOutOutlinedIcon from '@mui/icons-material/ZoomOutOutlined'
import { Box, IconButton, Stack, Tooltip, Typography } from '@mui/material'
import { useCallback, useEffect, useRef, useState } from 'react'
import type { PointerEvent as ReactPointerEvent } from 'react'
import { normalizeGeometryPoints, polygonBounds, resolveSeatGeometry } from '../../pages/Venue/MapEditor/mapVisuals'
import { clampViewBox, clientToSvgPoint, zoomViewBox, type ViewBox } from '../../pages/Venue/MapEditor/viewBox'

export type SeatMapViewerVisualState = 'available' | 'selected' | 'unavailable'

export interface SeatMapViewerSeat {
  uuid: string
  pos_x: number
  pos_y: number
  kind: string
  width: number | null
  height: number | null
  geometryPoints: Array<{ x: number; y: number }>
  label: string
  isAccessible: boolean
  visualState: SeatMapViewerVisualState
  disabled: boolean
  ariaLabel: string
}

interface SeatMapViewerProps {
  seats: SeatMapViewerSeat[]
  mapWidth: number
  mapHeight: number
  backgroundImageUrl?: string | null
  onSelectSeat: (uuid: string) => void
  readOnly?: boolean
}

/** Alvo de toque mínimo recomendado (44px CSS) — maior que o desenho visual do assento,
 * padrão comum em mapas de assento (o círculo pequeno seria alvo demais estreito no dedo). */
const MIN_TOUCH_PX = 44

const STATE_FILL: Record<SeatMapViewerVisualState, string> = {
  available: 'var(--pt-success)',
  selected: 'var(--pt-primary)',
  unavailable: 'var(--pt-danger)',
}

const CLICK_THRESHOLD_PX = 6

type DragState =
  | { mode: 'idle' }
  | { mode: 'pan'; startClientX: number; startClientY: number; startViewBox: ViewBox }
  | { mode: 'tap'; startClientX: number; startClientY: number; seatUuid: string | null }
  | { mode: 'pinch'; startDistance: number; startViewBox: ViewBox; midpoint: { x: number; y: number } }

function distance(a: { x: number; y: number }, b: { x: number; y: number }) {
  return Math.hypot(a.x - b.x, a.y - b.y)
}

function buildBaseViewBox(mapWidth: number, mapHeight: number): ViewBox {
  const pad = Math.max(mapWidth, mapHeight) * 0.04
  return { x: -pad, y: -pad, w: mapWidth + pad * 2, h: mapHeight + pad * 2 }
}

/**
 * Mapa de assentos somente leitura + seleção para a loja pública — mesma técnica de
 * canvas SVG puro do editor admin (`VenueMapEditor`/`MapCanvas`), sem lib de terceiros,
 * mas sem edição de posição/criação de assento. Zoom por roda do mouse e pinça no touch,
 * pan por arraste, alvo de toque ampliado (ver `MIN_TOUCH_PX`) e fallback de teclado
 * (Tab + Enter/Espaço) — SVG puro não suporta um "roving focus" nativo tão rico quanto
 * um DOM real, então o foco visual usa apenas o anel `--pt-focus-ring` do navegador.
 */
export function SeatMapViewer({ seats, mapWidth, mapHeight, backgroundImageUrl, onSelectSeat, readOnly = false }: SeatMapViewerProps) {
  const svgRef = useRef<SVGSVGElement | null>(null)
  const dragRef = useRef<DragState>({ mode: 'idle' })
  const pointersRef = useRef<Map<number, { x: number; y: number }>>(new Map())
  const baseViewBoxRef = useRef<ViewBox>(buildBaseViewBox(mapWidth, mapHeight))
  const [viewBox, setViewBox] = useState<ViewBox>(baseViewBoxRef.current)
  const [svgWidthPx, setSvgWidthPx] = useState(320)

  useEffect(() => {
    const base = buildBaseViewBox(mapWidth, mapHeight)
    baseViewBoxRef.current = base
    setViewBox(base)
  }, [mapWidth, mapHeight])

  useEffect(() => {
    const svg = svgRef.current
    if (!svg || typeof ResizeObserver === 'undefined') return
    const observer = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect.width
      if (width) setSvgWidthPx(width)
    })
    observer.observe(svg)
    return () => observer.disconnect()
  }, [])

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
      pointersRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY })

      if (pointersRef.current.size === 2) {
        const [p1, p2] = Array.from(pointersRef.current.values())
        dragRef.current = {
          mode: 'pinch',
          startDistance: distance(p1, p2),
          startViewBox: viewBox,
          midpoint: { x: (p1.x + p2.x) / 2, y: (p1.y + p2.y) / 2 },
        }
        return
      }

      const seatUuid = findSeatUuid(event.target)
      if (seatUuid) {
        dragRef.current = { mode: 'tap', startClientX: event.clientX, startClientY: event.clientY, seatUuid }
        return
      }

      dragRef.current = { mode: 'pan', startClientX: event.clientX, startClientY: event.clientY, startViewBox: viewBox }
    },
    [findSeatUuid, viewBox],
  )

  const handlePointerMove = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      const svg = svgRef.current
      if (!svg) return
      if (pointersRef.current.has(event.pointerId)) {
        pointersRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY })
      }
      const state = dragRef.current

      if (state.mode === 'pinch' && pointersRef.current.size === 2) {
        const [p1, p2] = Array.from(pointersRef.current.values())
        const newDistance = distance(p1, p2)
        if (newDistance <= 0 || state.startDistance <= 0) return
        const factor = state.startDistance / newDistance
        const focus = clientToSvgPoint(svg, state.midpoint.x, state.midpoint.y)
        setViewBox(zoomViewBox(state.startViewBox, baseViewBoxRef.current, factor, focus))
        return
      }

      if (state.mode === 'pan') {
        const scale = viewBox.w / svg.clientWidth
        const dx = (event.clientX - state.startClientX) * scale
        const dy = (event.clientY - state.startClientY) * scale
        setViewBox({ ...state.startViewBox, x: state.startViewBox.x - dx, y: state.startViewBox.y - dy })
        return
      }

      if (state.mode === 'tap') {
        const movedPx = Math.hypot(event.clientX - state.startClientX, event.clientY - state.startClientY)
        if (movedPx > CLICK_THRESHOLD_PX) {
          dragRef.current = { mode: 'pan', startClientX: event.clientX, startClientY: event.clientY, startViewBox: viewBox }
        }
      }
    },
    [viewBox],
  )

  const handlePointerUp = useCallback(
    (event: ReactPointerEvent<SVGSVGElement>) => {
      pointersRef.current.delete(event.pointerId)
      const state = dragRef.current
      dragRef.current = { mode: 'idle' }

      if (pointersRef.current.size >= 1) return

      if (state.mode === 'tap' && state.seatUuid) {
        const seat = seats.find((item) => item.uuid === state.seatUuid)
        if (seat && !seat.disabled && !readOnly) onSelectSeat(seat.uuid)
      }
    },
    [onSelectSeat, readOnly, seats],
  )

  // React anexa `onWheel` como listener passivo por padrão — preventDefault() não
  // funciona nele (a página rolaria junto do zoom). Listener nativo não-passivo é o
  // único jeito de bloquear o scroll da página enquanto o zoom do mapa acontece.
  useEffect(() => {
    const svg = svgRef.current
    if (!svg) return
    const listener = (event: WheelEvent) => {
      event.preventDefault()
      const point = clientToSvgPoint(svg, event.clientX, event.clientY)
      const factor = event.deltaY > 0 ? 1.15 : 1 / 1.15
      setViewBox((current) => zoomViewBox(current, baseViewBoxRef.current, factor, point))
    }
    svg.addEventListener('wheel', listener, { passive: false })
    return () => svg.removeEventListener('wheel', listener)
  }, [])

  function handleZoomButton(factor: number) {
    setViewBox((current) => zoomViewBox(current, baseViewBoxRef.current, factor))
  }

  function handleZoomReset() {
    setViewBox(clampViewBox(baseViewBoxRef.current, baseViewBoxRef.current))
  }

  function handleSeatKeyDown(event: React.KeyboardEvent<SVGGElement>, seat: SeatMapViewerSeat) {
    if (seat.disabled || readOnly) return
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      onSelectSeat(seat.uuid)
    }
  }

  const pxPerUnit = svgWidthPx > 0 ? svgWidthPx / viewBox.w : 1
  const touchRadiusUnits = Math.max(0, MIN_TOUCH_PX / 2 / pxPerUnit)

  return (
    <Box sx={{ position: 'relative' }}>
      <Box
        sx={{
          position: 'relative',
          width: '100%',
          height: { xs: 320, sm: 420, md: 480 },
          borderRadius: 'var(--pt-radius-lg)',
          border: '1px solid var(--pt-border)',
          overflow: 'hidden',
          background: 'var(--pt-surface-soft)',
        }}
      >
        <svg
          ref={svgRef}
          viewBox={`${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
          role="application"
          aria-label="Mapa de assentos do evento. Toque num lugar disponível para selecionar."
          style={{ width: '100%', height: '100%', display: 'block', touchAction: 'none', cursor: 'grab' }}
        >
          {backgroundImageUrl ? (
            <image
              href={backgroundImageUrl}
              x={0}
              y={0}
              width={mapWidth}
              height={mapHeight}
              opacity={0.55}
              preserveAspectRatio="xMidYMid slice"
            />
          ) : null}

          {seats.map((seat) => {
            const geo = resolveSeatGeometry(
              seat.kind === 'assento' || seat.kind === 'mesa' || seat.kind === 'area' || seat.kind === 'camarote' ? seat.kind : 'assento',
              seat.width,
              seat.height,
            )
            const geometryPoints = normalizeGeometryPoints(seat.geometryPoints)
            const areaBounds = seat.kind === 'area' ? polygonBounds(geometryPoints) : null
            const radius = Math.max(geo.width, geo.height) / 2
            const isSelected = seat.visualState === 'selected'
            const fill = STATE_FILL[seat.visualState]
            const isInteractive = !seat.disabled && !readOnly

            return (
              <g
                key={seat.uuid}
                data-seat-uuid={seat.uuid}
                tabIndex={isInteractive ? 0 : -1}
                role="button"
                aria-label={seat.ariaLabel}
                aria-disabled={seat.disabled || readOnly}
                aria-pressed={isSelected}
                onKeyDown={(event) => handleSeatKeyDown(event, seat)}
                style={{ cursor: isInteractive ? 'pointer' : 'default', outline: 'none' }}
              >
                {/* Área de toque invisível maior que o desenho visual — alvo de toque real em SVG puro. */}
                {seat.kind === 'area' && geometryPoints.length >= 3 ? (
                  <>
                    <polygon
                      points={geometryPoints.map((point) => `${point.x},${point.y}`).join(' ')}
                      fill="transparent"
                    />

                    {isSelected ? (
                      <polygon
                        points={geometryPoints.map((point) => `${point.x},${point.y}`).join(' ')}
                        fill="none"
                        stroke="var(--pt-primary)"
                        strokeWidth={2}
                        strokeDasharray="3 2"
                      />
                    ) : null}

                    <polygon
                      points={geometryPoints.map((point) => `${point.x},${point.y}`).join(' ')}
                      fill={fill}
                      fillOpacity={seat.disabled && !isSelected ? 0.35 : 0.5}
                      stroke="var(--pt-surface)"
                      strokeWidth={1.5}
                    />
                  </>
                ) : geo.shape === 'circle' ? (
                  <>
                    <circle cx={seat.pos_x} cy={seat.pos_y} r={Math.max(radius, touchRadiusUnits)} fill="transparent" />

                    {isSelected ? (
                      <circle
                        cx={seat.pos_x}
                        cy={seat.pos_y}
                        r={radius + 5}
                        fill="none"
                        stroke="var(--pt-primary)"
                        strokeWidth={2}
                        strokeDasharray="3 2"
                      />
                    ) : null}

                    <circle
                      cx={seat.pos_x}
                      cy={seat.pos_y}
                      r={radius}
                      fill={fill}
                      fillOpacity={seat.disabled && !isSelected ? 0.45 : 0.92}
                      stroke="var(--pt-surface)"
                      strokeWidth={1.5}
                    />
                  </>
                ) : (
                  <>
                    <rect
                      x={seat.pos_x - Math.max(geo.width / 2, touchRadiusUnits)}
                      y={seat.pos_y - Math.max(geo.height / 2, touchRadiusUnits)}
                      width={Math.max(geo.width, touchRadiusUnits * 2)}
                      height={Math.max(geo.height, touchRadiusUnits * 2)}
                      rx={geo.rx}
                      fill="transparent"
                    />

                    {isSelected ? (
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
                    ) : null}

                    <rect
                      x={seat.pos_x - geo.width / 2}
                      y={seat.pos_y - geo.height / 2}
                      width={geo.width}
                      height={geo.height}
                      rx={geo.rx}
                      fill={fill}
                      fillOpacity={seat.disabled && !isSelected ? 0.45 : 0.92}
                      stroke="var(--pt-surface)"
                      strokeWidth={1.5}
                    />
                  </>
                )}

                {seat.isAccessible ? (
                  <text
                    x={seat.pos_x}
                    y={seat.kind === 'area' && areaBounds ? areaBounds.top + 16 : seat.pos_y}
                    textAnchor="middle"
                    dominantBaseline="central"
                    fontSize={Math.max(12, Math.min(radius, 18))}
                    fill="var(--pt-surface)"
                    style={{ pointerEvents: 'none', userSelect: 'none' }}
                  >
                    ♿
                  </text>
                ) : null}
              </g>
            )
          })}
        </svg>

        <Stack
          direction="row"
          spacing={0.5}
          sx={{
            position: 'absolute',
            bottom: 10,
            left: 10,
            background: 'var(--pt-surface)',
            border: '1px solid var(--pt-border)',
            borderRadius: 'var(--pt-radius-md)',
            p: 0.5,
          }}
        >
          <Tooltip title="Aumentar zoom" arrow>
            <IconButton size="small" onClick={() => handleZoomButton(1 / 1.25)} sx={{ minWidth: 44, minHeight: 44 }} aria-label="Aumentar zoom">
              <ZoomInOutlinedIcon fontSize="small" />
            </IconButton>
          </Tooltip>
          <Tooltip title="Diminuir zoom" arrow>
            <IconButton size="small" onClick={() => handleZoomButton(1.25)} sx={{ minWidth: 44, minHeight: 44 }} aria-label="Diminuir zoom">
              <ZoomOutOutlinedIcon fontSize="small" />
            </IconButton>
          </Tooltip>
          <Tooltip title="Redefinir visualização" arrow>
            <IconButton size="small" onClick={handleZoomReset} sx={{ minWidth: 44, minHeight: 44 }} aria-label="Redefinir visualização">
              <CenterFocusStrongOutlinedIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </Stack>
      </Box>

      <Stack direction="row" spacing={1.5} sx={{ flexWrap: 'wrap', alignItems: 'center', mt: 1 }}>
        <LegendItem color="var(--pt-success)" label="Disponível" />
        <LegendItem color="var(--pt-primary)" label="Selecionado" />
        <LegendItem color="var(--pt-danger)" label="Indisponível" />
        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
          <AccessibleOutlinedIcon sx={{ fontSize: 16, color: 'var(--pt-muted)' }} />
          <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Lugar acessível</Typography>
        </Stack>
      </Stack>
    </Box>
  )
}

function LegendItem({ color, label }: { color: string; label: string }) {
  return (
    <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
      <Box sx={{ width: 12, height: 12, borderRadius: 999, bgcolor: color, flexShrink: 0 }} />
      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{label}</Typography>
    </Stack>
  )
}
