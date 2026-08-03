import { useEffect, useState } from 'react'
import * as tenantSettingsService from '../../../services/tenantSettingsService'
import { getApiErrorMessage } from '../../../types/api'
import type { TenantSettings } from '../../../types/tenantSettings'

/**
 * Carrega `GET /tenant-settings` uma vez — compartilhado pelos blocos
 * "Vendas e Operação" e "Pagamento" (todos
 * derivados do mesmo endpoint singleton, cada um editando só o próprio
 * subconjunto de campos). Cada bloco chama `updateTenantSettings` enviando
 * o objeto carregado aqui com só os campos dele sobrescritos — preserva os
 * campos dos outros blocos sem precisar de um form único gigante (ver
 * decisão da reorganização de Configurações, 2026-07-24).
 */
export function useTenantSettingsData() {
  const [settings, setSettings] = useState<TenantSettings | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    tenantSettingsService
      .getTenantSettings()
      .then(setSettings)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as configurações agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  return { settings, setSettings, isLoading, loadError, reload: load }
}
