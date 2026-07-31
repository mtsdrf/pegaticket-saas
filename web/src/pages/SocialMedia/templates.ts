import type { StoryTemplateVariant } from '../../types/socialMedia'

export interface TemplateMeta {
  key: StoryTemplateVariant
  label: string
  description: string
}

export const STORY_TEMPLATES: TemplateMeta[] = [
  { key: 'border', label: 'Com borda', description: 'Moldura decorativa nas cores Maskats ao redor do conteúdo.' },
  { key: 'logo_only', label: 'Só logo', description: 'Símbolo Maskats discreto num canto, sem texto de marca.' },
  { key: 'wordmark', label: 'Com nome "Maskats"', description: 'Wordmark completo visível no topo do story.' },
]
