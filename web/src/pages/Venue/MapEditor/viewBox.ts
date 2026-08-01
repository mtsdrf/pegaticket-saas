export interface ViewBox {
  x: number
  y: number
  w: number
  h: number
}

const MIN_SCALE = 0.15
const MAX_SCALE = 6

export function clampViewBox(base: ViewBox, next: ViewBox): ViewBox {
  const minW = base.w / MAX_SCALE
  const maxW = base.w / MIN_SCALE
  const w = Math.min(Math.max(next.w, minW), maxW)
  const scale = w / next.w
  const h = next.h * scale
  return { x: next.x, y: next.y, w, h }
}

export function zoomViewBox(current: ViewBox, base: ViewBox, factor: number, focus?: { x: number; y: number }): ViewBox {
  const cx = focus ? focus.x : current.x + current.w / 2
  const cy = focus ? focus.y : current.y + current.h / 2
  const newW = current.w * factor
  const newH = current.h * factor
  const ratioX = (cx - current.x) / current.w
  const ratioY = (cy - current.y) / current.h
  const next: ViewBox = {
    x: cx - ratioX * newW,
    y: cy - ratioY * newH,
    w: newW,
    h: newH,
  }
  return clampViewBox(base, next)
}

/** Ponto do cliente (mouse/touch) convertido pra coordenada de usuário do SVG (mesmo plano de `pos_x`/`pos_y`). */
export function clientToSvgPoint(svg: SVGSVGElement, clientX: number, clientY: number): { x: number; y: number } {
  const point = svg.createSVGPoint()
  point.x = clientX
  point.y = clientY
  const ctm = svg.getScreenCTM()
  if (!ctm) return { x: 0, y: 0 }
  const transformed = point.matrixTransform(ctm.inverse())
  return { x: transformed.x, y: transformed.y }
}
