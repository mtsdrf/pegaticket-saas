import DarkModeOutlinedIcon from '@mui/icons-material/DarkModeOutlined'
import LightModeOutlinedIcon from '@mui/icons-material/LightModeOutlined'
import { Box, Stack, Switch, Typography } from '@mui/material'
import { useThemeMode } from '../hooks/useThemeMode'

/** Embutido no dropdown de conta (`UserMenu`) — sem card/borda própria, o container que o hospeda define o espaçamento. */
export function ThemeModeSwitch() {
  const { preference, resolvedMode, setPreference } = useThemeMode()

  return (
    <Box>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
        <Box>
          <Typography variant="body2" sx={{ fontWeight: 700 }}>
            Tema
          </Typography>
          <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }}>
            {preference === 'system' ? 'Seguindo o sistema' : 'Preferência manual salva'}
          </Typography>
        </Box>
        <Typography variant="caption" sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>
          {resolvedMode === 'dark' ? 'Escuro' : 'Claro'}
        </Typography>
      </Stack>

      <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', justifyContent: 'center' }}>
        <LightModeOutlinedIcon
          sx={{ fontSize: 18, color: resolvedMode === 'light' ? 'var(--mk-warning)' : 'var(--mk-muted)' }}
        />

        <Switch
          checked={resolvedMode === 'dark'}
          onChange={(_, checked) => setPreference(checked ? 'dark' : 'light')}
          slotProps={{ input: { 'aria-label': 'Alternar tema claro ou escuro' } }}
          sx={{ mx: 0.25 }}
        />

        <DarkModeOutlinedIcon
          sx={{ fontSize: 18, color: resolvedMode === 'dark' ? 'var(--mk-primary)' : 'var(--mk-muted)' }}
        />
      </Stack>
    </Box>
  )
}
