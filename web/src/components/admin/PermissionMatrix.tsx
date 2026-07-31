import { Box, FormControlLabel, Stack, Switch, Typography } from '@mui/material'
import { SOFT_PANEL_SX } from '../../styles/surfaces'

interface PermissionMatrixProps {
  title: string
  functionalities: Array<{ uuid: string; name: string; slug: string }>
  getActionsForFunctionality: (functionalitySlug: string) => readonly string[]
  selected: Record<string, string[]>
  onToggle: (functionalitySlug: string, action: string, checked: boolean) => void
  /** Rótulo em português de cada ação — sem entrada no mapa, usa a chave crua como fallback. */
  actionLabels?: Record<string, string>
}

export function PermissionMatrix({ title, functionalities, getActionsForFunctionality, selected, onToggle, actionLabels }: PermissionMatrixProps) {
  return (
    <Box>
      <Typography sx={{ fontWeight: 700, mb: 1.5 }}>{title}</Typography>
      <Stack spacing={1.5}>
        {functionalities.map((functionality) => (
          <Box
            key={functionality.uuid}
            sx={{
              p: 1.5,
              ...SOFT_PANEL_SX,
            }}
          >
            <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, mb: 1 }}>{functionality.name}</Typography>
            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: {
                  xs: 'repeat(2, minmax(0, 1fr))',
                  sm: 'repeat(3, minmax(0, 1fr))',
                  md: 'repeat(4, minmax(0, 1fr))',
                  lg: 'repeat(5, minmax(0, 1fr))',
                },
                columnGap: 1,
                rowGap: 0.5,
              }}
            >
              {getActionsForFunctionality(functionality.slug).map((action) => (
                <FormControlLabel
                  key={`${functionality.slug}-${action}`}
                  sx={{ mx: 0, minHeight: 36 }}
                  control={
                    <Switch
                      size="small"
                      checked={selected[functionality.slug]?.includes(action) ?? false}
                      onChange={(event) => onToggle(functionality.slug, action, event.target.checked)}
                    />
                  }
                  label={actionLabels?.[action] ?? action}
                  slotProps={{ typography: { sx: { fontSize: 13.5 } } }}
                />
              ))}
            </Box>
          </Box>
        ))}
      </Stack>
    </Box>
  )
}
