import { createTheme, type PaletteMode } from '@mui/material/styles'

const lightTokens = {
  bg: '#F6F8FB',
  surface: '#FFFFFF',
  surfaceSoft: '#EEF3F8',
  primary: '#0F3D5E',
  primaryHover: '#0B314C',
  secondary: '#2563EB',
  accent: '#22C7A9',
  text: '#102033',
  muted: '#64748B',
  border: '#D8E0EA',
  success: '#16A34A',
  warning: '#F59E0B',
  danger: '#DC2626',
  info: '#0284C7',
}

const darkTokens = {
  bg: '#07111F',
  surface: '#0D1B2E',
  surfaceSoft: '#13243A',
  primary: '#38BDF8',
  primaryHover: '#7DD3FC',
  secondary: '#60A5FA',
  accent: '#2DD4BF',
  text: '#F8FAFC',
  muted: '#94A3B8',
  border: '#24364D',
  success: '#22C55E',
  warning: '#FBBF24',
  danger: '#F87171',
  info: '#38BDF8',
}

/**
 * Fonte única da paleta PegaTicket por modo — reaproveitada pelo tema MUI
 * abaixo e por qualquer superfície que não possa ler var(--pt-*)
 * diretamente (ex.: canvas do Chart.js).
 */
export const pegaticketTokens = { light: lightTokens, dark: darkTokens }

/**
 * Tema MUI construído a partir da paleta oficial da PegaTicket
 * (.claude/memory/design-system.md) — nunca as cores default do Material.
 */
export function buildPegaTicketTheme(mode: PaletteMode) {
  const tokens = pegaticketTokens[mode]

  return createTheme({
    palette: {
      mode,
      background: { default: tokens.bg, paper: tokens.surface },
      primary: { main: tokens.primary, dark: tokens.primaryHover, contrastText: mode === 'dark' ? tokens.bg : '#FFFFFF' },
      secondary: { main: tokens.secondary },
      success: { main: tokens.success },
      warning: { main: tokens.warning },
      error: { main: tokens.danger },
      info: { main: tokens.info },
      text: { primary: tokens.text, secondary: tokens.muted },
      divider: tokens.border,
    },
    shape: {
      borderRadius: 10,
    },
    typography: {
      fontFamily: "'Inter', system-ui, 'Segoe UI', Roboto, sans-serif",
      button: { textTransform: 'none', fontWeight: 600 },
    },
    components: {
      MuiCssBaseline: {
        styleOverrides: {
          ':focus-visible': {
            outline: `2px solid ${tokens.primary}`,
            outlineOffset: 2,
          },
        },
      },
      MuiPaper: {
        styleOverrides: {
          root: { backgroundImage: 'none' },
        },
      },
      MuiCard: {
        defaultProps: { variant: 'outlined' },
        styleOverrides: {
          root: {
            borderRadius: 14,
            borderColor: tokens.border,
            boxShadow: 'var(--pt-shadow-sm)',
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            minHeight: 44,
            borderRadius: 10,
            boxShadow: 'none',
            '&:hover': { boxShadow: 'none' },
            '&.MuiButton-containedPrimary:hover': { backgroundColor: tokens.primaryHover },
          },
          outlined: {
            borderColor: tokens.border,
            '&:hover': {
              borderColor: tokens.primary,
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 10%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
        },
      },
      MuiIconButton: {
        styleOverrides: {
          root: {
            borderRadius: 10,
            '&:hover': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 12%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
        },
      },
      MuiTextField: {
        defaultProps: { size: 'small' },
      },
      MuiOutlinedInput: {
        styleOverrides: {
          root: {
            borderRadius: 10,
            backgroundColor: tokens.surface,
            '& .MuiOutlinedInput-notchedOutline': { borderColor: tokens.border },
            '&:hover .MuiOutlinedInput-notchedOutline': { borderColor: tokens.primary },
            '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
              borderColor: tokens.primary,
              borderWidth: 1.5,
            },
          },
        },
      },
      MuiInputLabel: {
        styleOverrides: {
          root: { color: tokens.muted, '&.Mui-focused': { color: tokens.primary } },
        },
      },
      MuiMenu: {
        styleOverrides: {
          paper: {
            borderRadius: 12,
            border: `1px solid ${tokens.border}`,
            boxShadow: 'var(--pt-shadow-md)',
          },
        },
      },
      MuiMenuItem: {
        styleOverrides: {
          root: {
            borderRadius: 8,
            marginInline: 4,
            marginBlock: 1,
            minHeight: 40,
            '&.Mui-selected': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 16%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
        },
      },
      MuiDialog: {
        styleOverrides: {
          paper: {
            borderRadius: 16,
            border: `1px solid ${tokens.border}`,
            boxShadow: 'var(--pt-shadow-lg)',
          },
        },
      },
      MuiTooltip: {
        styleOverrides: {
          tooltip: {
            backgroundColor: tokens.text,
            color: tokens.bg,
            fontSize: 12.5,
            fontWeight: 500,
            borderRadius: 8,
            padding: '6px 10px',
          },
          arrow: { color: tokens.text },
        },
      },
      MuiAlert: {
        styleOverrides: {
          root: { borderRadius: 10, fontSize: 13.5 },
        },
      },
      MuiChip: {
        styleOverrides: {
          root: { borderRadius: 999, fontWeight: 600 },
        },
      },
      MuiCheckbox: {
        defaultProps: { color: 'primary' },
        styleOverrides: {
          root: {
            borderRadius: 8,
            '&:hover': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 12%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
        },
      },
      MuiRadio: {
        defaultProps: { color: 'primary' },
        styleOverrides: {
          root: {
            '&:hover': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 12%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
        },
      },
      MuiSwitch: {
        styleOverrides: {
          switchBase: {
            '&.Mui-checked': { color: tokens.primary },
            '&.Mui-checked + .MuiSwitch-track': { backgroundColor: tokens.primary, opacity: 0.5 },
          },
        },
      },
      MuiAutocomplete: {
        styleOverrides: {
          paper: {
            borderRadius: 12,
            border: `1px solid ${tokens.border}`,
            boxShadow: 'var(--pt-shadow-md)',
          },
          option: {
            borderRadius: 8,
            marginInline: 4,
            '&.Mui-focused': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 16%, transparent)'
                  : tokens.surfaceSoft,
            },
          },
          tag: { borderRadius: 8 },
        },
      },
      MuiTabs: {
        styleOverrides: {
          indicator: { backgroundColor: tokens.primary, height: 2.5, borderRadius: 999 },
        },
      },
      MuiTab: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            fontWeight: 600,
            minHeight: 44,
            '&.Mui-selected': { color: tokens.primary },
          },
        },
      },
      MuiDivider: {
        styleOverrides: {
          root: { borderColor: tokens.border },
        },
      },
      MuiTableCell: {
        styleOverrides: {
          root: { borderColor: tokens.border },
        },
      },
      MuiAppBar: {
        styleOverrides: {
          root: {
            backgroundColor: tokens.surface,
            color: tokens.text,
            borderBottom: `1px solid ${tokens.border}`,
            boxShadow: 'var(--pt-shadow-md)',
          },
        },
      },
      MuiDrawer: {
        styleOverrides: {
          paper: {
            backgroundColor: tokens.surface,
            borderRight: `1px solid ${tokens.border}`,
            backgroundImage: 'none',
          },
        },
      },
      MuiListItemButton: {
        styleOverrides: {
          root: {
            borderRadius: 0,
            borderLeft: '3px solid transparent',
            '&.Mui-selected': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 16%, transparent)'
                  : tokens.surfaceSoft,
              borderLeftColor: tokens.primary,
              color: tokens.primary,
              '& .MuiListItemIcon-root': { color: tokens.primary },
              '&:hover': {
                backgroundColor:
                  mode === 'dark'
                    ? 'color-mix(in srgb, var(--pt-primary) 22%, transparent)'
                    : tokens.surfaceSoft,
              },
            },
          },
        },
      },
    },
  })
}
