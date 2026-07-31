import { useMemo } from 'react'
import { useAccessControl } from '../../../hooks/useAccessControl'
import { useAuth } from '../../../hooks/useAuth'
import { isOwnerRole } from '../../../utils/tenantRole'
import { SETTINGS_BLOCKS, SETTINGS_LINKS } from './registry'

/**
 * Filtra `SETTINGS_BLOCKS`/`SETTINGS_LINKS` pela mesma regra de visibilidade
 * condicional já usada pela sidebar (`AppLayout`) — cada bloco/entrada só
 * aparece no índice do hub se o usuário tiver a permissão relevante (gate
 * por bloco, não por página inteira), com o mesmo bypass de dono já usado
 * pelo item "Assinatura da empresa".
 */
export function useVisibleSettingsEntries() {
  const { can } = useAccessControl()
  const { activeTenant, accessProfile } = useAuth()
  const isTenantOwner = Boolean(accessProfile?.is_tenant_owner || isOwnerRole(activeTenant?.role, activeTenant?.role_slug))

  return useMemo(() => {
    const blocks = SETTINGS_BLOCKS.filter((block) => can(block.permission))
    const links = SETTINGS_LINKS.filter(
      (link) => (link.ownerBypassesAccess && isTenantOwner) || can(link.permission),
    )
    return { blocks, links, isTenantOwner }
  }, [can, isTenantOwner])
}
