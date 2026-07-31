import DarkModeOutlinedIcon from '@mui/icons-material/DarkModeOutlined'
import LightModeOutlinedIcon from '@mui/icons-material/LightModeOutlined'
import SettingsBrightnessOutlinedIcon from '@mui/icons-material/SettingsBrightnessOutlined'
import { IconButton, ListItemIcon, ListItemText, Menu, MenuItem, Tooltip } from '@mui/material'
import { useState } from 'react'
import { useThemeMode } from '../hooks/useThemeMode'
import type { ThemeModePreference } from '../contexts/theme-mode-context'

const OPTIONS: { value: ThemeModePreference; label: string; icon: typeof LightModeOutlinedIcon }[] = [
  { value: 'light', label: 'Claro', icon: LightModeOutlinedIcon },
  { value: 'dark', label: 'Escuro', icon: DarkModeOutlinedIcon },
  { value: 'system', label: 'Sistema', icon: SettingsBrightnessOutlinedIcon },
]

export function ThemeModeToggle() {
  const { preference, resolvedMode, setPreference } = useThemeMode()
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)

  const TriggerIcon = resolvedMode === 'dark' ? DarkModeOutlinedIcon : LightModeOutlinedIcon

  return (
    <>
      <Tooltip title="Tema" arrow>
        <IconButton
          aria-label="Alternar tema"
          onClick={(event) => setAnchorEl(event.currentTarget)}
          sx={{ ml: 0.5 }}
        >
          <TriggerIcon fontSize="small" />
        </IconButton>
      </Tooltip>

      <Menu anchorEl={anchorEl} open={Boolean(anchorEl)} onClose={() => setAnchorEl(null)}>
        {OPTIONS.map((option) => (
          <MenuItem
            key={option.value}
            selected={preference === option.value}
            onClick={() => {
              setPreference(option.value)
              setAnchorEl(null)
            }}
          >
            <ListItemIcon>
              <option.icon fontSize="small" />
            </ListItemIcon>
            <ListItemText>{option.label}</ListItemText>
          </MenuItem>
        ))}
      </Menu>
    </>
  )
}
