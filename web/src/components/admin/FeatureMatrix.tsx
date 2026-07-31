import { Box, Checkbox, FormControlLabel, Stack, Typography } from '@mui/material'
import { SOFT_PANEL_SX } from '../../styles/surfaces'

interface FeatureMatrixProps {
  title: string
  functionalities: Array<{ uuid: string; name: string; slug: string }>
  selected: string[]
  onToggle: (functionalitySlug: string, checked: boolean) => void
}

export function FeatureMatrix({ title, functionalities, selected, onToggle }: FeatureMatrixProps) {
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
            <FormControlLabel
              control={
                <Checkbox
                  checked={selected.includes(functionality.slug)}
                  onChange={(event) => onToggle(functionality.slug, event.target.checked)}
                />
              }
              label={
                <Box>
                  <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700 }}>{functionality.name}</Typography>
                  <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{functionality.slug}</Typography>
                </Box>
              }
              sx={{ alignItems: 'flex-start', m: 0 }}
            />
          </Box>
        ))}
      </Stack>
    </Box>
  )
}
