import { createTheme, type PaletteMode } from '@mui/material/styles'
import { UI_RADIUS, UI_SIZE } from '../styles/layoutStandards'

const lightTokens = {
  bg: '#F8F8FA',
  surface: '#FFFFFF',
  surfaceSoft: '#EEF3F8',
  primary: '#005BDA',
  primaryHover: '#004EC0',
  primarySoft: '#E9F2FF',
  secondary: '#0A6BFF',
  accent: '#003F9A',
  text: '#10213E',
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
  primary: '#0A6BFF',
  primaryHover: '#3B82FF',
  primarySoft: '#12325F',
  secondary: '#005BDA',
  accent: '#38BDF8',
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
      borderRadius: 15,
    },
    typography: {
      fontFamily: "'Inter', system-ui, 'Segoe UI', Roboto, sans-serif",
      h1: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 700,
        fontSize: '2.5rem',
        lineHeight: 1.15,
      },
      h2: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 600,
        fontSize: '1.875rem',
        lineHeight: 1.2,
      },
      h3: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 600,
        fontSize: '1.5rem',
        lineHeight: 1.25,
      },
      h4: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 600,
        fontSize: '1.25rem',
        lineHeight: 1.25,
      },
      h5: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 700,
        fontSize: '1.75rem',
        lineHeight: 1.15,
      },
      h6: {
        fontFamily: "'Sora', 'Inter', system-ui, sans-serif",
        fontWeight: 600,
        fontSize: '1.125rem',
        lineHeight: 1.2,
      },
      subtitle1: { fontWeight: 500, fontSize: '1rem', lineHeight: 1.5 },
      subtitle2: { fontWeight: 500, fontSize: '0.875rem', lineHeight: 1.45 },
      body1: { fontWeight: 400, fontSize: '1rem', lineHeight: 1.5 },
      body2: { fontWeight: 400, fontSize: '0.875rem', lineHeight: 1.45 },
      caption: { fontWeight: 500, fontSize: '0.75rem', lineHeight: 1.35 },
      button: { textTransform: 'none', fontWeight: 600, fontSize: '0.875rem', lineHeight: 1 },
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
            borderRadius: UI_RADIUS.lg,
            borderColor: tokens.border,
            boxShadow: 'var(--pt-shadow-sm)',
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            minHeight: UI_SIZE.control,
            borderRadius: UI_RADIUS.md,
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
            borderRadius: UI_RADIUS.md,
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
            minHeight: UI_SIZE.control,
            borderRadius: UI_RADIUS.md,
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
            borderRadius: UI_RADIUS.lg,
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
            minHeight: UI_SIZE.compactControl,
            '&.Mui-selected': {
              backgroundColor:
                mode === 'dark'
                  ? 'color-mix(in srgb, var(--pt-primary) 16%, transparent)'
                  : tokens.primarySoft,
            },
          },
        },
      },
      MuiDialog: {
        styleOverrides: {
          paper: {
            borderRadius: UI_RADIUS.xl,
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
          root: { borderRadius: UI_RADIUS.lg, fontSize: 13.5 },
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
