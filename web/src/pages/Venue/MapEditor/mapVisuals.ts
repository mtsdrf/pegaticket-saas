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

export function seatBounds(kind: SeatKind, x: number, y: number) {
  const geo = KIND_GEOMETRY[kind]
  return {
    left: x - geo.width / 2,
    right: x + geo.width / 2,
    top: y - geo.height / 2,
    bottom: y + geo.height / 2,
  }
}
