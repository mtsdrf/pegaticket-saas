import { Box, MenuItem, Stack, TextField, Typography } from '@mui/material'
import { SOFT_PANEL_SX } from '../../styles/surfaces'

/** `null` = sem override (segue o plano); `true`/`false` = override forçado ligado/desligado. */
export type FeatureOverrideState = boolean | null

interface TenantFeatureOverrideMatrixProps {
  title: string
  functionalities: Array<{ uuid: string; name: string; slug: string }>
  overrides: Record<string, FeatureOverrideState>
  onChange: (functionalitySlug: string, value: FeatureOverrideState) => void
  disabled?: boolean
}

const OPTIONS: Array<{ value: string; label: FeatureOverrideState }> = [
  { value: 'default', label: null },
  { value: 'enabled', label: true },
  { value: 'disabled', label: false },
]

function stateToOption(state: FeatureOverrideState): string {
  if (state === true) return 'enabled'
  if (state === false) return 'disabled'
  return 'default'
}

function optionToState(value: string): FeatureOverrideState {
  const option = OPTIONS.find((item) => item.value === value)
  return option ? option.label : null
}

/**
 * Feature flag por tenant individual (roadmap A5, item 19) — 3 estados por
 * funcionalidade, diferente de `FeatureMatrix` (checkbox 2 estados usado em
 * planos): "sem override" (segue o plano) é um estado distinto de "override
 * desligado" (força bloqueio mesmo se o plano libera).
 */
export function TenantFeatureOverrideMatrix({
  title,
  functionalities,
  overrides,
  onChange,
  disabled,
}: TenantFeatureOverrideMatrixProps) {
  return (
    <Box>
      <Typography sx={{ fontWeight: 700, mb: 0.5 }}>{title}</Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', mb: 1.5 }}>
        Sem override, a funcionalidade segue o que o plano da empresa libera. Use "Forçar ativado/desativado" para
        exceções individuais.
      </Typography>
      <Stack spacing={1.5}>
        {functionalities.map((functionality) => (
          <Stack
            key={functionality.uuid}
            direction={{ xs: 'column', sm: 'row' }}
            spacing={1.5}
            sx={{
              p: 1.5,
              ...SOFT_PANEL_SX,
              alignItems: { xs: 'stretch', sm: 'center' },
              justifyContent: 'space-between',
            }}
          >
            <Box>
              <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700 }}>{functionality.name}</Typography>
              <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{functionality.slug}</Typography>
            </Box>
            <TextField
              select
              size="small"
              value={stateToOption(overrides[functionality.slug] ?? null)}
              onChange={(event) => onChange(functionality.slug, optionToState(event.target.value))}
              disabled={disabled}
              sx={{ minWidth: { xs: '100%', sm: 220 } }}
            >
              <MenuItem value="default">Padrão do plano</MenuItem>
              <MenuItem value="enabled">Forçar ativado</MenuItem>
              <MenuItem value="disabled">Forçar desativado</MenuItem>
            </TextField>
          </Stack>
        ))}
      </Stack>
    </Box>
  )
}
