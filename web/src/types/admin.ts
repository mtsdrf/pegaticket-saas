import type { PaginationMeta } from './pagination'

export interface AdminGroupRef {
  uuid: string
  name: string
  slug: string
}

export interface AdminUser {
  uuid: string
  name: string
  email: string
  is_active: boolean
  groups?: AdminGroupRef[]
  created_at: string
  updated_at?: string
}

export interface AdminUserPayload {
  name: string
  email: string
  password?: string
  is_active?: boolean
  group_uuids?: string[]
}

export interface AdminGroup {
  uuid: string
  name: string
  slug: string
  is_active: boolean
  users?: {
    uuid: string
    name: string
    email: string
  }[]
  created_at: string
  updated_at?: string
}

export interface AdminGroupPayload {
  name: string
  slug: string
  is_active?: boolean
}

export interface Functionality {
  uuid: string
  name: string
  slug: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at?: string
}

export interface FunctionalityPayload {
  name: string
  slug: string
  description?: string | null
  is_active?: boolean
}

export interface Tenant {
  uuid: string
  name: string
  slug: string
  plan_uuid: string | null
  plan_name: string | null
  logo_url: string | null
  is_active: boolean
  trial_ends_at: string | null
  created_at: string
}

export interface TenantPayload {
  name: string
  slug?: string
  plan_uuid: string
  is_active?: boolean
}

export interface Plan {
  uuid: string
  name: string
  slug: string
  description: string | null
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at?: string
}

export interface PlanPayload {
  name: string
  slug: string
  description?: string | null
  sort_order?: number
  is_active?: boolean
}

export interface PlanFunctionalityRef {
  functionality: string
}

/** Espelha `TenantFeatureOverrideRepository::getForTenant()` — roadmap A5 item 19. */
export interface TenantFeatureOverride {
  functionality: string
  is_enabled: boolean
}

export interface TenantFeatureOverrideInput {
  functionality: string
  is_enabled: boolean
}

export interface TenantRole {
  uuid: string
  name: string
  slug: string
  is_active: boolean
  created_at: string
}

export interface TenantRolePayload {
  name: string
  slug: string
  is_active?: boolean
}

export interface TenantRolePermission {
  functionality: string
  action: string
  /**
   * Limite percentual de desconto do perfil (roadmap A1.5) — só tem efeito
   * nas linhas `functionality: 'sales'` (backend lê a menor configuração
   * não-nula entre elas, ver `PermissionService::resolveOrderDiscountLimitPercent`).
   * `null`/ausente = sem limite.
   */
  discount_limit_percent?: number | null
}

export interface TenantUser {
  uuid: string
  tenant_uuid: string
  user_uuid: string
  role_uuid: string
  tenant_name?: string
  user_name?: string
  user_email?: string
  role_name?: string
  is_active: boolean
  created_at: string
}

export interface TenantUserInvite {
  uuid: string
  tenant_uuid: string
  role_uuid: string
  name: string
  email: string
  expires_at: string
  accepted_at: string | null
  created_at: string
}

export interface TenantUserInvitePayload {
  name: string
  email: string
  role_uuid: string
}

export interface TenantUserPayload {
  tenant_uuid: string
  role_uuid: string
  user_uuid?: string
  user?: {
    name: string
    email: string
    password: string
  }
  is_active?: boolean
}

export interface AuditLog {
  uuid: string
  user_name: string | null
  event: string
  auditable_type: string | null
  auditable_id: number | null
  route: string | null
  method: string | null
  ip: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  meta: Record<string, unknown> | null
  created_at: string
}

export interface AdminPaginatedResult<T> {
  items: T[]
  pagination: PaginationMeta
}

export const TENANT_ROLE_ACTION_OPTIONS = [
  'read',
  'create',
  'update',
  'delete',
  'entry',
  'exit',
  'adjustment',
  'transfer',
  'block',
  'reserve',
  'view_costs',
  'approve_inventory',
  'reverse',
  'cancel',
  'export_pdf',
] as const

const CRUD_ACTION_OPTIONS = ['read', 'create', 'update', 'delete'] as const

const TENANT_ROLE_ACTIONS_BY_FUNCTIONALITY: Record<string, readonly string[]> = {
  users: CRUD_ACTION_OPTIONS,
  tenant_roles: CRUD_ACTION_OPTIONS,
  tenant_users: CRUD_ACTION_OPTIONS,
  event_categories: CRUD_ACTION_OPTIONS,
  events: CRUD_ACTION_OPTIONS,
  event_sessions: CRUD_ACTION_OPTIONS,
  ticket_types: CRUD_ACTION_OPTIONS,
  ticket_batches: CRUD_ACTION_OPTIONS,
  event_products: CRUD_ACTION_OPTIONS,
  venues: CRUD_ACTION_OPTIONS,
  seats: CRUD_ACTION_OPTIONS,
  support: ['read', 'create'],
  dashboard: ['read'],
  analytics: ['read'],
  reports: ['read', 'export_pdf'],
  finance: ['read'],
  tenant_settings: ['read', 'update'],
  'tenant-profile': ['read', 'update', 'export'],
  storefront: ['read', 'update'],
  subscription: ['read', 'update'],
  sales: ['read', 'create', 'update', 'cancel'],
  'storefront-sales': ['read', 'approve', 'cancel'],
  sale_refunds: ['read', 'create'],
}

export function getTenantRoleActionOptions(functionalitySlug: string): readonly string[] {
  return TENANT_ROLE_ACTIONS_BY_FUNCTIONALITY[functionalitySlug] ?? CRUD_ACTION_OPTIONS
}

export const GROUP_ACTION_OPTIONS = ['read', 'create', 'update', 'delete'] as const

/** Rótulo em português de cada ação de permissão (chave crua vem da API em inglês). */
export const ACTION_LABELS_PT: Record<string, string> = {
  read: 'Visualizar',
  create: 'Criar',
  update: 'Editar',
  delete: 'Excluir',
  entry: 'Entrada',
  exit: 'Saída',
  adjustment: 'Ajuste',
  transfer: 'Transferência',
  block: 'Bloquear',
  reserve: 'Reservar',
  view_costs: 'Ver custos',
  approve_inventory: 'Aprovar estoque',
  reverse: 'Estornar',
  cancel: 'Cancelar',
  approve: 'Aprovar',
  export: 'Exportar dados',
  export_pdf: 'Exportar PDF',
  open: 'Abrir',
  close: 'Fechar',
  movement: 'Sangria / suprimento',
  sell: 'Vender',
  add_item: 'Adicionar item',
  prep: 'Preparar',
}
