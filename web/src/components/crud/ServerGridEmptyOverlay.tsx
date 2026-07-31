import type { GridApi } from 'ag-grid-community'
import type { ReactNode } from 'react'
import { EmptyState } from '../layout/EmptyState'

export interface ServerGridEmptyStateConfig {
  icon: ReactNode
  title: string
  description: string
  /** CTA (ex.: "Cadastrar primeiro X") — some quando há filtro ativo no grid, não faz sentido oferecer "criar" nesse caso. */
  action?: ReactNode
  filteredTitle?: string
  filteredDescription?: string
}

export interface ServerGridEmptyOverlayParams extends ServerGridEmptyStateConfig {
  api?: GridApi
}

/**
 * Overlay de "sem linhas" do ag-Grid, com o mesmo `EmptyState` usado no
 * resto do CRUD. Distingue "nunca teve dado" de "filtro ativo sem resultado"
 * consultando `api.isAnyFilterPresent()` — os filtros do grid (coluna a
 * coluna) não são visíveis pra página que o monta, só o próprio grid sabe.
 */
export function ServerGridEmptyOverlay(params: ServerGridEmptyOverlayParams) {
  const isFiltered = params.api?.isAnyFilterPresent() ?? false

  return (
    <EmptyState
      icon={params.icon}
      title={isFiltered ? (params.filteredTitle ?? 'Nenhum resultado encontrado') : params.title}
      description={
        isFiltered
          ? (params.filteredDescription ?? 'Ajuste os filtros do grid para ver outros resultados.')
          : params.description
      }
      action={isFiltered ? undefined : params.action}
    />
  )
}
