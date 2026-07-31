import AutoAwesomeMosaicRoundedIcon from '@mui/icons-material/AutoAwesomeMosaicRounded'
import GroupsRoundedIcon from '@mui/icons-material/GroupsRounded'
import Inventory2RoundedIcon from '@mui/icons-material/Inventory2Rounded'
import QueryStatsRoundedIcon from '@mui/icons-material/QueryStatsRounded'
import RouteRoundedIcon from '@mui/icons-material/RouteRounded'
import ReceiptLongRoundedIcon from '@mui/icons-material/ReceiptLongRounded'
import SettingsEthernetRoundedIcon from '@mui/icons-material/SettingsEthernetRounded'
import ShoppingBagRoundedIcon from '@mui/icons-material/ShoppingBagRounded'
import StorefrontRoundedIcon from '@mui/icons-material/StorefrontRounded'
import type { SvgIconComponent } from '@mui/icons-material'

/**
 * Recorte comercial dos módulos hoje ativos nos planos do produto. Não é um
 * espelho 1:1 de todas as functionalities técnicas do backend; aqui entram
 * só os blocos que fazem sentido na comparação pública de planos.
 */
export interface ModuleInfo {
  key: string
  icon: SvgIconComponent
  title: string
  description: string
}

export const MODULES: ModuleInfo[] = [
  {
    key: 'storefront',
    icon: StorefrontRoundedIcon,
    title: 'Loja online',
    description: 'Cardápio e catálogo digital para o cliente pedir direto com você, com redes sociais integradas.',
  },
  {
    key: 'orders',
    icon: ShoppingBagRoundedIcon,
    title: 'Pedidos',
    description: 'Pedidos de loja, atendimento interno e atacado centralizados, do recebimento até a entrega.',
  },
  {
    key: 'clients',
    icon: GroupsRoundedIcon,
    title: 'Clientes',
    description: 'Cadastro de clientes por categoria, endereços e histórico de compras em um só lugar.',
  },
  {
    key: 'reports',
    icon: AutoAwesomeMosaicRoundedIcon,
    title: 'Relatórios & dashboard',
    description: 'Visão geral da operação: vendas, produtos e desempenho da equipe.',
  },
  {
    key: 'stock',
    icon: Inventory2RoundedIcon,
    title: 'Estoque',
    description: 'Controle de estoque por depósito/filial, com movimentações e disponibilidade em tempo real.',
  },
  {
    key: 'storefront-orders',
    icon: ShoppingBagRoundedIcon,
    title: 'Operação da loja',
    description: 'Fila dedicada para aprovar, despachar, entregar e receber pedidos vindos da loja online.',
  },
  {
    key: 'integrations',
    icon: SettingsEthernetRoundedIcon,
    title: 'Integrações',
    description: 'Chaves de API, webhooks, pedidos externos e conexões operacionais avançadas.',
  },
  {
    key: 'analytics',
    icon: QueryStatsRoundedIcon,
    title: 'Analytics & rotas',
    description: 'Indicadores avançados de vendas e planejamento de rotas de entrega.',
  },
  {
    key: 'subscription',
    icon: RouteRoundedIcon,
    title: 'Assinatura self-service',
    description: 'Gestão da própria assinatura PegaTicket: plano, ciclo, faturas e cancelamento.',
  },
  {
    key: 'compliance',
    icon: ReceiptLongRoundedIcon,
    title: 'Fiscal & contador',
    description: 'Governança fiscal, perfis tributários e acesso dedicado para o escritório contábil.',
  },
]
