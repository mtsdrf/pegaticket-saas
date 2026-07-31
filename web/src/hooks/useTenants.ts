import { useEffect } from 'react'
import { useAuth } from './useAuth'

/**
 * `tenants`/`error` vêm do `AuthContext` (fetch único de `auth/my-tenants`,
 * feito no login/registro/troca de tenant e no mount da sessão) — não faz
 * fetch próprio, só reaproveita o que já foi carregado (ver `AuthContext.tsx`).
 */
export function useTenants() {
  const { activeTenantUuid, selectTenant, tenants, tenantsError } = useAuth()

  // Usuário com um único tenant não deve precisar escolher manualmente:
  // seleciona sozinho assim que a lista chega, sem esperar interação.
  useEffect(() => {
    if (tenants && tenants.length === 1 && !activeTenantUuid) {
      void selectTenant(tenants[0].tenant_uuid)
    }
  }, [tenants, activeTenantUuid, selectTenant])

  return { tenants, error: tenantsError }
}
