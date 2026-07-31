import { Box } from '@mui/material'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { MapContainer, Marker, TileLayer } from 'react-leaflet'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

/**
 * Mapa read-only com um único marcador na posição da loja — reaproveita o
 * mesmo setup de Leaflet/`react-leaflet` de `RouteMap.tsx` (ícone via
 * `L.divIcon`, HTML puro, pra fugir do problema clássico dos paths de ícone
 * quebrados pelo Vite). Sem rota, sem interação de arrastar/scroll.
 */
const STORE_ICON = L.divIcon({
  className: 'mk-store-marker',
  html: `<span style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:var(--mk-primary);box-shadow:0 2px 6px rgba(0,0,0,0.35);border:2px solid #fff;"><span style="width:9px;height:9px;border-radius:50%;background:#fff;"></span></span>`,
  iconSize: [32, 32],
  iconAnchor: [16, 30],
})

interface StoreLocationMapProps {
  lat: number
  lng: number
  label?: string
}

export function StoreLocationMap({ lat, lng, label }: StoreLocationMapProps) {
  const center: [number, number] = [lat, lng]

  return (
    <Box
      role="img"
      aria-label={label ? `Mapa da localização de ${label}` : 'Mapa da localização da loja'}
      sx={{
        ...ELEVATED_SURFACE_SX,
        height: { xs: 220, sm: 280 },
        overflow: 'hidden',
      }}
    >
      <MapContainer
        center={center}
        zoom={16}
        style={{ height: '100%', width: '100%' }}
        scrollWheelZoom={false}
        dragging={false}
        doubleClickZoom={false}
        zoomControl={false}
        attributionControl={false}
        keyboard={false}
      >
        <TileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        />
        <Marker position={center} icon={STORE_ICON} />
      </MapContainer>
    </Box>
  )
}
