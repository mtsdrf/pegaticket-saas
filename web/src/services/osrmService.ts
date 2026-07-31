import type { LocatedRouteStop, OptimizedStop } from '../types/route'

const OSRM_BASE_URL = 'https://router.project-osrm.org'

export interface GeoPoint {
  lat: number
  lng: number
}

interface OsrmWaypoint {
  waypoint_index: number
  location: [number, number]
}

interface OsrmTrip {
  geometry: { type: 'LineString'; coordinates: [number, number][] }
  distance: number
  duration: number
}

interface OsrmTripResponse {
  code: string
  message?: string
  trips: OsrmTrip[]
  waypoints: OsrmWaypoint[]
}

export interface OptimizedTrip {
  /** Sequência otimizada de visita (origem excluída, já ordenada por `waypoint_index`). */
  stops: OptimizedStop[]
  /** `[lon, lat]` — geometria completa da rota, na ordem em que deve ser desenhada. */
  geometry: [number, number][]
  distanceMeters: number
  durationSeconds: number
}

/**
 * OSRM Trip API espera `lon,lat` (GeoJSON/ordem geográfica padrão) — nossa API
 * devolve `lat`/`lng` separados, então a inversão é sempre explícita aqui,
 * nunca implícita num template string solto.
 */
function toLonLat(point: GeoPoint): string {
  return `${point.lng},${point.lat}`
}

/**
 * Busca o itinerário otimizado via OSRM Trip API (demo público). A origem
 * (geolocalização atual) sempre entra como primeiro ponto (`source=first`);
 * as paradas seguem na ordem recebida. O retorno reordena as paradas pela
 * sequência otimizada (`waypoint_index` de cada waypoint, excluindo a
 * origem) — ver `web/src/services/osrmService.ts` no roadmap para o contrato
 * completo da resposta.
 */
export async function fetchOptimizedTrip(origin: GeoPoint, stops: LocatedRouteStop[]): Promise<OptimizedTrip> {
  if (stops.length === 0) {
    throw new Error('Nenhuma parada roteirizável informada.')
  }

  const coordinates = [origin, ...stops.map((stop) => ({ lat: stop.endereco.lat, lng: stop.endereco.lng }))]
    .map(toLonLat)
    .join(';')

  const url = `${OSRM_BASE_URL}/trip/v1/driving/${coordinates}?source=first&roundtrip=false&overview=full&geometries=geojson`

  let response: Response
  try {
    response = await fetch(url)
  } catch {
    throw new Error('Não foi possível conectar ao serviço de rotas (OSRM). Verifique sua conexão e tente novamente.')
  }

  if (!response.ok) {
    throw new Error('O serviço de rotas (OSRM) está indisponível no momento. Tente novamente em instantes.')
  }

  const payload = (await response.json()) as OsrmTripResponse

  if (payload.code !== 'Ok' || !payload.trips?.[0]) {
    throw new Error('Não foi possível calcular um itinerário para as paradas selecionadas.')
  }

  const trip = payload.trips[0]

  // waypoints[0] é a origem (source=first) — as paradas ocupam os índices 1..n
  // na MESMA ordem em que foram enviadas na URL, não na ordem otimizada.
  // `waypoint_index` de cada waypoint é a posição dele na sequência otimizada.
  const orderedStops: OptimizedStop[] = payload.waypoints
    .slice(1)
    .map((waypoint, index) => ({ waypoint, stop: stops[index] }))
    .sort((a, b) => a.waypoint.waypoint_index - b.waypoint.waypoint_index)
    .map(({ waypoint, stop }, index) => ({
      position: index + 1,
      stop,
      location: waypoint.location,
    }))

  return {
    stops: orderedStops,
    geometry: trip.geometry.coordinates,
    distanceMeters: trip.distance,
    durationSeconds: trip.duration,
  }
}

export function googleMapsDirectionsUrl(point: GeoPoint): string {
  return `https://www.google.com/maps/dir/?api=1&destination=${point.lat},${point.lng}`
}

function toLatLngParam(location: [number, number]): string {
  return `${location[1]},${location[0]}`
}

/**
 * Rota completa (todas as paradas) no Google Maps. A última parada vira
 * `destination`; as demais entram em `waypoints` separadas por `|`. O Google
 * Maps tem um limite prático de waypoints (historicamente ~9-10) — não
 * validamos isso aqui, o Maps lida/trunca do lado dele.
 */
export function googleMapsRouteUrl(origin: GeoPoint, stops: OptimizedStop[]): string {
  const params = new URLSearchParams({
    api: '1',
    origin: `${origin.lat},${origin.lng}`,
    travelmode: 'driving',
  })

  if (stops.length === 0) {
    return `https://www.google.com/maps/dir/?${params.toString()}`
  }

  const destination = stops[stops.length - 1]
  const waypoints = stops.slice(0, -1)

  params.set('destination', toLatLngParam(destination.location))
  if (waypoints.length > 0) {
    params.set('waypoints', waypoints.map((stop) => toLatLngParam(stop.location)).join('|'))
  }

  return `https://www.google.com/maps/dir/?${params.toString()}`
}

/**
 * O Waze não tem API de URL para múltiplas paradas — abre navegação só para
 * a próxima parada da rota (a primeira da sequência otimizada).
 */
export function wazeNavigationUrl(point: GeoPoint): string {
  return `https://waze.com/ul?ll=${point.lat},${point.lng}&navigate=yes`
}

/** Fórmula de haversine — distância em metros entre dois pontos geográficos. */
export function distanceInMeters(a: GeoPoint, b: GeoPoint): number {
  const R = 6371000
  const toRad = (deg: number) => (deg * Math.PI) / 180
  const dLat = toRad(b.lat - a.lat)
  const dLng = toRad(b.lng - a.lng)
  const lat1 = toRad(a.lat)
  const lat2 = toRad(b.lat)

  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}
