import { themeQuartz } from 'ag-grid-community'

/**
 * Tema do ag-Grid via Theming API (CSS-in-JS nativo, v33+) apontando direto
 * pros tokens `--mk-*` — nenhum hex hardcoded, e a troca de tema light/dark
 * funciona sozinha porque são `var(...)` resolvidos pelo browser, sem branch
 * em JS nem CSS estático (`ag-theme-quartz.css`) importado.
 */
export const maskatsGridTheme = themeQuartz.withParams({
  backgroundColor: 'var(--mk-surface)',
  foregroundColor: 'var(--mk-text)',
  textColor: 'var(--mk-text)',
  headerBackgroundColor: 'var(--mk-surface-soft)',
  headerTextColor: 'var(--mk-text)',
  headerFontWeight: 600,
  borderColor: 'var(--mk-border)',
  accentColor: 'var(--mk-primary)',
  rowHoverColor: 'var(--mk-surface-soft)',
  selectedRowBackgroundColor: 'var(--mk-surface-soft)',
  oddRowBackgroundColor: 'transparent',
  fontFamily: 'inherit',
  fontSize: 14,
  spacing: 8,
  cellHorizontalPadding: 16,
  borderRadius: 'var(--mk-radius-md)',
  wrapperBorderRadius: 'var(--mk-radius-md)',
  wrapperBorder: true,
  headerRowBorder: true,
  rowBorder: true,
  columnBorder: false,
})
