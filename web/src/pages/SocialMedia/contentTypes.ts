import CampaignOutlinedIcon from '@mui/icons-material/CampaignOutlined'
import EmojiEventsOutlinedIcon from '@mui/icons-material/EmojiEventsOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import LocationOnOutlinedIcon from '@mui/icons-material/LocationOnOutlined'
import TrendingUpOutlinedIcon from '@mui/icons-material/TrendingUpOutlined'
import type { SvgIconComponent } from '@mui/icons-material'
import type { SocialContentTypeKey } from '../../types/socialMedia'

export interface ContentTypeMeta {
  key: SocialContentTypeKey
  label: string
  description: string
  icon: SvgIconComponent
  /** `true` quando o conteúdo vem de `reports/analytics/*` — plano pode bloquear (ver `useAnalyticsData`/`PlanUpgradeNotice`). */
  isAnalytics: boolean
}

export const CONTENT_TYPES: ContentTypeMeta[] = [
  {
    key: 'product',
    label: 'Produto',
    description: 'Destaque um produto do catálogo com foto e preço.',
    icon: Inventory2OutlinedIcon,
    isAnalytics: false,
  },
  {
    key: 'top_client',
    label: 'Cliente do mês',
    description: 'Reconheça o cliente que mais comprou no período.',
    icon: EmojiEventsOutlinedIcon,
    isAnalytics: true,
  },
  {
    key: 'top_products',
    label: 'Produtos mais vendidos',
    description: 'Ranking dos produtos campeões de venda.',
    icon: TrendingUpOutlinedIcon,
    isAnalytics: true,
  },
  {
    key: 'top_neighborhoods',
    label: 'Bairros com mais pedidos',
    description: 'Ranking dos bairros que mais compraram.',
    icon: LocationOnOutlinedIcon,
    isAnalytics: true,
  },
  {
    key: 'announcement',
    label: 'Comunicado livre',
    description: 'Um aviso ou novidade, com texto e imagem opcional.',
    icon: CampaignOutlinedIcon,
    isAnalytics: false,
  },
]

export function getContentTypeMeta(key: SocialContentTypeKey): ContentTypeMeta {
  return CONTENT_TYPES.find((item) => item.key === key)!
}
