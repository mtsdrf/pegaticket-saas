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
    title: 'Bilheteria online',
    description: 'Página pública para divulgar eventos, vender ingressos e receber o público nos seus próprios canais.',
  },
  {
    key: 'sales',
    icon: ShoppingBagRoundedIcon,
    title: 'Vendas',
    description: 'Vendas online e manuais centralizadas, do checkout à confirmação do pagamento e emissão.',
  },
  {
    key: 'clients',
    icon: GroupsRoundedIcon,
    title: 'Clientes',
    description: 'Cadastro de compradores, participantes e histórico de compras em um só lugar.',
  },
  {
    key: 'reports',
    icon: AutoAwesomeMosaicRoundedIcon,
    title: 'Relatórios & dashboard',
    description: 'Visão geral da operação: vendas, eventos, check-ins e desempenho da equipe.',
  },
  {
    key: 'inventory',
    icon: Inventory2RoundedIcon,
    title: 'Capacidade & lugares',
    description: 'Controle de lotes, assentos, setores, mesas e disponibilidade em tempo real.',
  },
  {
    key: 'operations',
    icon: ShoppingBagRoundedIcon,
    title: 'Operação do evento',
    description: 'Fila dedicada para acompanhar vendas, aprovações, pagamentos, acessos e atendimento ao comprador.',
  },
  {
    key: 'integrations',
    icon: SettingsEthernetRoundedIcon,
    title: 'Integrações',
    description: 'Chaves de API, webhooks, integrações de pagamento e conexões operacionais avançadas.',
  },
  {
    key: 'analytics',
    icon: QueryStatsRoundedIcon,
    title: 'Analytics',
    description: 'Indicadores avançados de vendas, conversão, ocupação e comportamento do público.',
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
