export type PermissionScope = 'global' | 'tenant'

export interface PermissionRequirement {
  functionality: string
  action: string
  scope: PermissionScope
}

export interface AccessProfile {
  is_administrator: boolean
  has_tenant_context: boolean
  is_tenant_owner: boolean
  tenant_uuid: string | null
  global_permissions: string[]
  tenant_permissions: string[]
  tenant_functionalities: string[]
}
