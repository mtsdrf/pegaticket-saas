import { useState } from 'react'
import * as locationService from '../services/locationService'
import type { Bairro, Cidade } from '../types/location'

/**
 * Estado (listas + loading) da cascata Cidade→Bairro dependente de um
 * Estado/Cidade selecionado alhures. Extraído pra reaproveitar em qualquer
 * formulário com a mesma cascata (hoje: `EnderecoFormPage`) sem duplicar a
 * lógica assíncrona. Quem usa o hook ainda é dono do valor selecionado
 * (`estado_uuid`/`cidade_uuid`/`bairro_uuid` do próprio form) — o hook só
 * busca e guarda as opções disponíveis pra cada nível.
 *
 * `ClientFormPage.tsx` mantém sua própria versão inline dessa lógica
 * (não foi migrada pra este hook de propósito — é a tela mais sensível a
 * regressão do app, migrar sem necessidade real adicionava risco sem
 * ganho imediato). Se um terceiro formulário precisar da mesma cascata,
 * migrar `ClientFormPage` pra este hook então faz sentido.
 */
export function useLocationCascade() {
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)

  async function loadCidades(estadoUuid: string) {
    setBairros([])
    setCidades([])
    if (!estadoUuid) return

    setIsLoadingCidades(true)
    try {
      setCidades(await locationService.getCidades(estadoUuid))
    } catch {
      setCidades([])
    } finally {
      setIsLoadingCidades(false)
    }
  }

  async function loadBairros(cidadeUuid: string) {
    setBairros([])
    if (!cidadeUuid) return

    setIsLoadingBairros(true)
    try {
      setBairros(await locationService.getBairros(cidadeUuid))
    } catch {
      setBairros([])
    } finally {
      setIsLoadingBairros(false)
    }
  }

  return {
    cidades,
    bairros,
    isLoadingCidades,
    isLoadingBairros,
    loadCidades,
    loadBairros,
    setCidades,
    setBairros,
  }
}
