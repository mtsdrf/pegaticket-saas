import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CreditCardOutlinedIcon from '@mui/icons-material/CreditCardOutlined'
import MailOutlineOutlinedIcon from '@mui/icons-material/MailOutlineOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
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
const PaymentBlock = lazy(() => import('./PaymentBlock').then((m) => ({ default: m.PaymentBlock })))
const DataPrivacyBlock = lazy(() => import('./DataPrivacyBlock').then((m) => ({ default: m.DataPrivacyBlock })))
const ScheduledReportsBlock = lazy(() => import('./ScheduledReportsBlock').then((m) => ({ default: m.ScheduledReportsBlock })))

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
    key: 'operacao',
    path: 'operacao',
    label: 'Vendas e Operação',
    description: 'Disponibilidade, regras do canal público e parâmetros operacionais.',
    icon: TuneOutlinedIcon,
    permission: ACCESS.tenantSettingsRead,
    Component: OperationsBlock,
  },
  {
    key: 'pagamento',
    path: 'pagamento',
    label: 'Pagamento',
    description: 'Formas de pagamento aceitas na bilheteria online.',
    icon: PaymentsOutlinedIcon,
    permission: ACCESS.tenantSettingsRead,
    Component: PaymentBlock,
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
  {
    key: 'relatorios-agendados',
    path: 'relatorios-agendados',
    label: 'Relatórios agendados',
    description: 'Receba o resumo do Home por e-mail, diário ou semanal.',
    icon: MailOutlineOutlinedIcon,
    permission: ACCESS.reportsRead,
    Component: ScheduledReportsBlock,
  },
]

/**
 * Entradas do índice que navegam pra telas já existentes fora do hub — CRUDs
 * ou fluxos transacionais complexos demais pra caber num bloco inline
 * (mantêm rota própria, só ganham ponto de entrada único e consistente).
 */
export const SETTINGS_LINKS: SettingsLinkConfig[] = [
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
