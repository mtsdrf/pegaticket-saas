import type { SeatKind, SeatStatus } from '../../../types/venue'

/**
 * Metadados visuais por `kind`/`status` do editor de mapa — únicas fontes de
 * verdade pra tamanho/forma/cor usadas por `MapCanvas`. Cores sempre via
 * token `--pt-*` (nunca hex), formas via geometria simples (círculo/retângulo
 * arredondado) sem depender de ícone externo pesado no SVG.
 */
export const STATUS_COLOR_VAR: Record<SeatStatus, string> = {
  disponivel: 'var(--pt-success)',
  bloqueado: 'var(--pt-warning)',
  indisponivel: 'var(--pt-danger)',
}

export const STATUS_LABEL: Record<SeatStatus, string> = {
  disponivel: 'Disponível',
  bloqueado: 'Bloqueado',
  indisponivel: 'Indisponível',
}

export const KIND_LABEL: Record<SeatKind, string> = {
  assento: 'Assento',
  mesa: 'Mesa',
  area: 'Área',
  camarote: 'Camarote',
}

interface KindGeometry {
  shape: 'circle' | 'rect'
  width: number
  height: number
  rx: number
  fillOpacity: number
}

export const KIND_GEOMETRY: Record<SeatKind, KindGeometry> = {
  assento: { shape: 'circle', width: 18, height: 18, rx: 9, fillOpacity: 0.9 },
  mesa: { shape: 'rect', width: 36, height: 36, rx: 8, fillOpacity: 0.85 },
  camarote: { shape: 'rect', width: 56, height: 38, rx: 10, fillOpacity: 0.85 },
  area: { shape: 'rect', width: 96, height: 68, rx: 6, fillOpacity: 0.28 },
}

export function normalizeGeometryPoints(points?: Array<{ x: number; y: number }> | null): Array<{ x: number; y: number }> {
  return (points ?? []).filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y))
}

export function polygonBounds(points?: Array<{ x: number; y: number }> | null) {
  const normalized = normalizeGeometryPoints(points)
  if (normalized.length === 0) return null

  const xs = normalized.map((point) => point.x)
  const ys = normalized.map((point) => point.y)

  return {
    left: Math.min(...xs),
    right: Math.max(...xs),
    top: Math.min(...ys),
    bottom: Math.max(...ys),
  }
}

export function resolveSeatGeometry(kind: SeatKind, width?: number | null, height?: number | null): KindGeometry {
  const geo = KIND_GEOMETRY[kind]

  return {
    ...geo,
    width: width && width > 0 ? width : geo.width,
    height: height && height > 0 ? height : geo.height,
  }
}

export function seatBounds(kind: SeatKind, x: number, y: number, width?: number | null, height?: number | null) {
  const geo = resolveSeatGeometry(kind, width, height)
  return {
    left: x - geo.width / 2,
    right: x + geo.width / 2,
    top: y - geo.height / 2,
    bottom: y + geo.height / 2,
  }
}

export function seatVisualBounds(
  kind: SeatKind,
  x: number,
  y: number,
  width?: number | null,
  height?: number | null,
  geometryPoints?: Array<{ x: number; y: number }> | null,
) {
  if (kind === 'area') {
    const bounds = polygonBounds(geometryPoints)
    if (bounds) return bounds
  }

  return seatBounds(kind, x, y, width, height)
}
