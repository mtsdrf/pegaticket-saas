import AccountBalanceRoundedIcon from '@mui/icons-material/AccountBalanceRounded'
import AutoAwesomeMosaicRoundedIcon from '@mui/icons-material/AutoAwesomeMosaicRounded'
import CardGiftcardRoundedIcon from '@mui/icons-material/CardGiftcardRounded'
import GroupsRoundedIcon from '@mui/icons-material/GroupsRounded'
import Inventory2RoundedIcon from '@mui/icons-material/Inventory2Rounded'
import LocalBarRoundedIcon from '@mui/icons-material/LocalBarRounded'
import PointOfSaleRoundedIcon from '@mui/icons-material/PointOfSaleRounded'
import QueryStatsRoundedIcon from '@mui/icons-material/QueryStatsRounded'
import ReceiptLongRoundedIcon from '@mui/icons-material/ReceiptLongRounded'
import RouteRoundedIcon from '@mui/icons-material/RouteRounded'
import ShoppingBagRoundedIcon from '@mui/icons-material/ShoppingBagRounded'
import StorefrontRoundedIcon from '@mui/icons-material/StorefrontRounded'
import type { SvgIconComponent } from '@mui/icons-material'

/**
 * Um card por módulo real do produto — mapeado 1:1 das functionalities de
 * `api/database/seeders/InitialPlansSeeder.php` (fonte única, não inventar
 * módulo sem lastro no seeder/plano real).
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
    description: 'Pedidos de loja, balcão e atacado centralizados, do recebimento até a entrega.',
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
    key: 'pdv',
    icon: PointOfSaleRoundedIcon,
    title: 'PDV',
    description: 'Frente de caixa para venda presencial, integrada ao mesmo estoque e catálogo.',
  },
  {
    key: 'cashback',
    icon: CardGiftcardRoundedIcon,
    title: 'Cashback & fidelidade',
    description: 'Programa de cashback para o cliente voltar a comprar direto com você.',
  },
  {
    key: 'analytics',
    icon: QueryStatsRoundedIcon,
    title: 'Analytics & rotas',
    description: 'Indicadores avançados de vendas e planejamento de rotas de entrega.',
  },
  {
    key: 'balcao',
    icon: LocalBarRoundedIcon,
    title: 'Balcão',
    description: 'Mesas, comandas e comunicação com cozinha e bar para restaurantes, bares e casas noturnas.',
  },
  {
    key: 'subscription',
    icon: RouteRoundedIcon,
    title: 'Assinatura self-service',
    description: 'Gestão da própria assinatura PegaTicket: plano, ciclo, faturas e cancelamento.',
  },
  {
    key: 'accounting-access',
    icon: AccountBalanceRoundedIcon,
    title: 'Acesso do contador',
    description: 'Login dedicado para o escritório de contabilidade acompanhar a empresa.',
  },
  {
    key: 'tax-rules',
    icon: ReceiptLongRoundedIcon,
    title: 'Regras fiscais',
    description: 'Configuração de regras fiscais aplicadas aos pedidos da empresa.',
  },
]
