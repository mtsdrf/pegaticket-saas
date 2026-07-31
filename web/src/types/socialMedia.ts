/** Um dos 5 tipos de conteúdo do MVP de "Redes Sociais" — ver `pages/SocialMedia/contentTypes.ts` pros metadados de UI de cada um. */
export type SocialContentTypeKey = 'product' | 'top_client' | 'top_products' | 'top_neighborhoods' | 'announcement'

/** Um dos 3 templates fixos — a moldura/marca é definida pelo template, o conteúdo interno é resolvido à parte (ver `StoryCanvas.tsx`). */
export type StoryTemplateVariant = 'border' | 'logo_only' | 'wordmark'

/**
 * Precificação estruturada exclusiva do conteúdo "Produto" — quando
 * presente, `StorySingleBody` usa isso em vez de `value` (texto livre, que
 * continua servindo cliente do mês/comunicado). `regularPrice` é sempre o
 * preço varejo (pré-preenchido da base, editável); `offerPrice` ativa o
 * visual "de/por" (riscado + destaque); `wholesalePrice` acrescenta um
 * selo à parte com o preço de atacado.
 */
export interface StoryProductPricing {
  regularPrice: number
  offerPrice?: number
  wholesalePrice?: number
  wholesaleMinQuantity?: number
}

/** Corpo "item único" — produto, cliente do mês, comunicado livre (foto/nome/valor centralizados). */
export interface StorySingleContent {
  kind: 'single'
  eyebrow?: string
  title: string
  description?: string
  value?: string
  imageDataUrl?: string | null
  pricing?: StoryProductPricing
}

export interface StoryRankingItem {
  label: string
  primaryValue: string
  secondaryValue?: string
}

/** Corpo "lista top-N" — produtos mais vendidos, bairros com mais pedidos (ranking numerado). */
export interface StoryRankingContent {
  kind: 'ranking'
  eyebrow?: string
  title: string
  items: StoryRankingItem[]
}

export type StoryContent = StorySingleContent | StoryRankingContent
