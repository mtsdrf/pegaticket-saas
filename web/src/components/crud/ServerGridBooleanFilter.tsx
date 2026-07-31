import { ToggleButton, ToggleButtonGroup } from '@mui/material'
import type { CustomFilterProps } from 'ag-grid-react'
import { useGridFilter } from 'ag-grid-react'

export interface ServerGridBooleanFilterModel {
  value: boolean
}

type ThreeState = 'all' | 'true' | 'false'

/**
 * Filtro boolean de 3 estados (Todos/Sim/Não), Community-safe.
 * `agSetColumnFilter` é Enterprise-only. ag-Grid v33+ (Theming API) usa um
 * contrato de filtro React "model-driven" — `props.model`/`onModelChange`,
 * mais o hook `useGridFilter` só pra registrar `doesFilterPass` (nunca
 * chamado de fato: o Infinite Row Model delega a filtragem pro backend via
 * `getRows`, a interface só exige o método). O contrato antigo baseado em
 * `forwardRef`/`filterChangedCallback` não existe mais nessa versão.
 */
export function ServerGridBooleanFilter(props: CustomFilterProps<unknown, unknown, ServerGridBooleanFilterModel>) {
  const { model, onModelChange } = props

  useGridFilter({
    doesFilterPass: () => true,
  })

  function handleChange(_event: unknown, next: ThreeState | null) {
    if (next === null) return
    onModelChange(next === 'all' ? null : { value: next === 'true' })
  }

  const selected: ThreeState = !model ? 'all' : model.value ? 'true' : 'false'

  return (
    <ToggleButtonGroup
      value={selected}
      exclusive
      onChange={handleChange}
      orientation="vertical"
      size="small"
      sx={{
        p: 1,
        '& .MuiToggleButton-root': { justifyContent: 'flex-start', minHeight: 44, textTransform: 'none' },
      }}
    >
      <ToggleButton value="all">Todos</ToggleButton>
      <ToggleButton value="true">Sim</ToggleButton>
      <ToggleButton value="false">Não</ToggleButton>
    </ToggleButtonGroup>
  )
}
