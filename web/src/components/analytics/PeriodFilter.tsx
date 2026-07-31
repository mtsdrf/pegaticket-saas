import { Chip, MenuItem, Paper, Stack, TextField } from '@mui/material'
import { useState } from 'react'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { presetRange, type PeriodPreset } from '../../utils/period'

const PRESET_OPTIONS: { value: PeriodPreset; label: string }[] = [
  { value: 'this_month', label: 'Este mês' },
  { value: 'last_30', label: 'Últimos 30 dias' },
  { value: 'last_90', label: 'Últimos 90 dias' },
  { value: 'last_12_months', label: 'Últimos 12 meses' },
  { value: 'this_year', label: 'Este ano' },
  { value: 'custom', label: 'Personalizado' },
]

interface PeriodFilterProps {
  from: string
  to: string
  onChange: (from: string, to: string) => void
  /** Aba ativa não usa período (ex.: Sazonalidade) — inputs desabilitados + aviso. */
  disabled?: boolean
  disabledHint?: string
  /** Preset selecionado inicialmente no dropdown — não recalcula `from`/`to` (quem monta a tela já inicializa o estado com `presetRange(defaultPreset)`). */
  defaultPreset?: PeriodPreset
}

/**
 * Filtro de período compartilhado das Análises e da Visão geral: presets +
 * datas livres. Editar uma data manualmente muda o preset para "Personalizado".
 */
export function PeriodFilter({ from, to, onChange, disabled, disabledHint, defaultPreset = 'last_12_months' }: PeriodFilterProps) {
  const [preset, setPreset] = useState<PeriodPreset>(defaultPreset)

  function handlePresetChange(value: PeriodPreset) {
    setPreset(value)
    if (value !== 'custom') {
      const range = presetRange(value)
      onChange(range.from, range.to)
    }
  }

  function handleDateChange(nextFrom: string, nextTo: string) {
    setPreset('custom')
    onChange(nextFrom, nextTo)
  }

  return (
    <Paper
      variant="outlined"
      sx={{
        p: { xs: 1.5, sm: 2 },
        ...ELEVATED_SURFACE_SX,
        mb: 2.5,
      }}
    >
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1.5}
        sx={{ alignItems: { xs: 'stretch', sm: 'center' }, flexWrap: 'wrap', rowGap: 1.5 }}
      >
        <TextField
          select
          label="Período"
          size="small"
          value={preset}
          onChange={(event) => handlePresetChange(event.target.value as PeriodPreset)}
          disabled={disabled}
          sx={{ flex: 1, minWidth: { xs: '100%', sm: 160 } }}
        >
          {PRESET_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>

        <TextField
          label="De"
          type="date"
          size="small"
          value={from}
          onChange={(event) => handleDateChange(event.target.value, to)}
          disabled={disabled}
          slotProps={{ inputLabel: { shrink: true }, htmlInput: { max: to || undefined } }}
          sx={{ flex: 1, minWidth: { xs: '100%', sm: 160 } }}
        />
        <TextField
          label="Até"
          type="date"
          size="small"
          value={to}
          onChange={(event) => handleDateChange(from, event.target.value)}
          disabled={disabled}
          slotProps={{ inputLabel: { shrink: true }, htmlInput: { min: from || undefined } }}
          sx={{ flex: 1, minWidth: { xs: '100%', sm: 160 } }}
        />

        {disabled && disabledHint ? (
          <Chip
            label={disabledHint}
            size="small"
            sx={{
              ...SOFT_PANEL_SX,
              color: 'var(--pt-muted)',
              alignSelf: { xs: 'flex-start', sm: 'center' },
            }}
          />
        ) : null}
      </Stack>
    </Paper>
  )
}
