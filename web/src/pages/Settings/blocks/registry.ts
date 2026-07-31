import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CampaignOutlinedIcon from '@mui/icons-material/CampaignOutlined'
import CreditCardOutlinedIcon from '@mui/icons-material/CreditCardOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import TuneOutlinedIcon from '@mui/icons-material/TuneOutlined'
import type { SvgIconComponent } from '@mui/icons-material'
import { lazy, type ComponentType } from 'react'
import { ACCESS } from '../../../access/requirements'
import type { PermissionRequirement } from '../../../types/access'

// `lazy()` aqui (em vez de import estático) mantém `registry.ts` seguro pra
// importar de qualquer lugar (inclusive `AppRoutes.tsx`, pra gerar as rotas
// dos blocos) sem puxar o código de todos os blocos pro bundle principal —
// cada `Component` só carrega quando a rota dele é visitada, mesmo padrão
// de code-splitting já usado pelas demais páginas em `AppRoutes.tsx`.
const CompanyBlock = lazy(() => import('./CompanyBlock').then((m) => ({ default: m.CompanyBlock })))
const OperationsBlock = lazy(() => import('./OperationsBlock').then((m) => ({ default: m.OperationsBlock })))
const ScheduleAddressBlock = lazy(() => import('./ScheduleAddressBlock').then((m) => ({ default: m.ScheduleAddressBlock })))
const PaymentBlock = lazy(() => import('./PaymentBlock').then((m) => ({ default: m.PaymentBlock })))
const RetentionBlock = lazy(() => import('./RetentionBlock').then((m) => ({ default: m.RetentionBlock })))
const DataPrivacyBlock = lazy(() => import('./DataPrivacyBlock').then((m) => ({ default: m.DataPrivacyBlock })))

export interface SettingsBlockConfig {
  key: string
  /** Segmento relativo sob `/configuracoes/*`. */
  path: string
  label: string
  description: string
  icon: SvgIconComponent
  /** Gate de visibilidade (mesma permissão usada pela rota via `PermissionRoute`). */
  permission: PermissionRequirement
  Component: ComponentType
}

export interface SettingsLinkConfig {
  key: string
  /** Rota absoluta de uma tela já existente, fora do hub (CRUD/fluxo complexo demais pra caber num bloco). */
  to: string
  label: string
  description: string
  icon: SvgIconComponent
  permission: PermissionRequirement
  /** Dono da empresa enxerga o item mesmo sem a permissão de grupo (mesmo bypass já usado pela sidebar). */
  ownerBypassesAccess?: boolean
}

/**
 * Índice único dos blocos "editáveis inline" do hub de Configurações — cada
 * bloco é uma variável singleton do tenant, com submit próprio (ver
 * `useTenantSettingsData` pros 3 que compartilham `/tenant-settings`).
 * Ordem = ordem de exibição no índice (proposta de reorganização,
 * 2026-07-24, seção 3.2).
 */
export const SETTINGS_BLOCKS: SettingsBlockConfig[] = [
  {
    key: 'empresa',
    path: 'empresa',
    label: 'Empresa',
    description: 'Nome e logo da sua empresa.',
    icon: ApartmentOutlinedIcon,
    permission: ACCESS.tenantProfileRead,
    Component: CompanyBlock,
  },
  {
    key: 'pedidos',
    path: 'pedidos',
    label: 'Pedidos e Operação',
    description: 'Estoque, pedido mínimo, tempo de preparo e retirada na loja.',
    icon: TuneOutlinedIcon,
    permission: ACCESS.tenantSettingsRead,
    Component: OperationsBlock,
  },
  {
    key: 'horario-endereco',
    path: 'horario-endereco',
    label: 'Horário e Endereço',
    description: 'Horário de funcionamento e endereço da loja.',
    icon: ScheduleOutlinedIcon,
    permission: ACCESS.storefrontUpdate,
    Component: ScheduleAddressBlock,
  },
  {
    key: 'pagamento',
    path: 'pagamento',
    label: 'Pagamento',
    description: 'Formas de pagamento aceitas na loja online.',
    icon: PaymentsOutlinedIcon,
    permission: ACCESS.tenantSettingsRead,
    Component: PaymentBlock,
  },
  {
    key: 'retencao',
    path: 'retencao',
    label: 'Retenção e Marketing',
    description: 'Régua de reativação de clientes inativos.',
    icon: CampaignOutlinedIcon,
    permission: ACCESS.reactivationRead,
    Component: RetentionBlock,
  },
  {
    key: 'dados-privacidade',
    path: 'dados-privacidade',
    label: 'Dados e Privacidade',
    description: 'Exportar os dados da sua empresa.',
    icon: ShieldOutlinedIcon,
    permission: ACCESS.tenantProfileExport,
    Component: DataPrivacyBlock,
  },
]

/**
 * Entradas do índice que navegam pra telas já existentes fora do hub — CRUDs
 * ou fluxos transacionais complexos demais pra caber num bloco inline
 * (mantêm rota própria, só ganham ponto de entrada único e consistente).
 */
export const SETTINGS_LINKS: SettingsLinkConfig[] = [
  {
    key: 'loja-online',
    to: '/configuracoes/loja-online',
    label: 'Loja Online',
    description: 'Taxas de entrega, cupons e promoções.',
    icon: StorefrontOutlinedIcon,
    permission: ACCESS.storefrontUpdate,
  },
  {
    key: 'assinatura',
    to: '/configuracoes/assinatura',
    label: 'Assinatura da empresa',
    description: 'Plano, cobrança e faturas.',
    icon: CreditCardOutlinedIcon,
    permission: ACCESS.subscriptionRead,
    ownerBypassesAccess: true,
  },
]
