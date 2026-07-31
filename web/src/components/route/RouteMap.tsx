import { Box } from '@mui/material'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useEffect, useMemo } from 'react'
import { MapContainer, Marker, Polyline, TileLayer, useMap } from 'react-leaflet'
import type { GeoPoint } from '../../services/osrmService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { OptimizedStop } from '../../types/route'

/**
 * Ícones numerados via `L.divIcon` (HTML puro, sem imagem) — evita o problema
 * clássico do Leaflet com bundlers (paths do ícone padrão quebrados pelo Vite)
 * e já resolve a numeração da ordem otimizada de visita.
 */
function markerIcon(label: string, background: string): L.DivIcon {
  return L.divIcon({
    className: 'mk-route-marker',
    html: `<span style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:${background};color:#fff;font-weight:700;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.35);border:2px solid #fff;">${label}</span>`,
    iconSize: [30, 30],
    iconAnchor: [15, 15],
  })
}

const ORIGIN_ICON = markerIcon('•', 'var(--mk-primary)')

interface RouteMapProps {
  origin: GeoPoint
  stops: OptimizedStop[]
  /** `[lon, lat]`, na ordem em que deve ser desenhada (formato GeoJSON do OSRM). */
  geometry: [number, number][]
}

function FitBounds({ origin, stops }: { origin: GeoPoint; stops: OptimizedStop[] }) {
  const map = useMap()

  useEffect(() => {
    const points: [number, number][] = [
      [origin.lat, origin.lng],
      ...stops.map((stop) => [stop.location[1], stop.location[0]] as [number, number]),
    ]
    map.fitBounds(L.latLngBounds(points), { padding: [32, 32] })
  }, [map, origin, stops])

  return null
}

export function RouteMap({ origin, stops, geometry }: RouteMapProps) {
  const polylinePositions = useMemo<[number, number][]>(
    () => geometry.map(([lon, lat]) => [lat, lon]),
    [geometry],
  )
  const center: [number, number] = [origin.lat, origin.lng]

  return (
    <Box
      sx={{
        ...ELEVATED_SURFACE_SX,
        height: { xs: 320, sm: 440 },
        overflow: 'hidden',
      }}
    >
      <MapContainer center={center} zoom={13} style={{ height: '100%', width: '100%' }} scrollWheelZoom>
        <TileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        />

        <Marker position={center} icon={ORIGIN_ICON} />

        {stops.map((optimized) => (
          <Marker
            key={optimized.stop.client_uuid}
            position={[optimized.location[1], optimized.location[0]]}
            icon={markerIcon(String(optimized.position), 'var(--mk-secondary)')}
          />
        ))}

        {polylinePositions.length > 1 && (
          <Polyline positions={polylinePositions} pathOptions={{ color: 'var(--mk-secondary)', weight: 4, opacity: 0.85 }} />
        )}

        <FitBounds origin={origin} stops={stops} />
      </MapContainer>
    </Box>
  )
}
