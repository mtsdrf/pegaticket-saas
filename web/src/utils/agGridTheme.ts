import { themeQuartz } from 'ag-grid-community'

/**
 * Tema do ag-Grid via Theming API (CSS-in-JS nativo, v33+) apontando direto
 * pros tokens `--pt-*` — nenhum hex hardcoded, e a troca de tema light/dark
 * funciona sozinha porque são `var(...)` resolvidos pelo browser, sem branch
 * em JS nem CSS estático (`ag-theme-quartz.css`) importado.
 */
export const pegaticketGridTheme = themeQuartz.withParams({
  backgroundColor: 'var(--pt-surface)',
  foregroundColor: 'var(--pt-text)',
  textColor: 'var(--pt-text)',
  headerBackgroundColor: 'var(--pt-surface-soft)',
  headerTextColor: 'var(--pt-text)',
  headerFontWeight: 600,
  borderColor: 'var(--pt-border)',
  accentColor: 'var(--pt-primary)',
  rowHoverColor: 'var(--pt-surface-soft)',
  selectedRowBackgroundColor: 'var(--pt-surface-soft)',
  oddRowBackgroundColor: 'transparent',
  fontFamily: 'inherit',
  fontSize: 14,
  spacing: 8,
  cellHorizontalPadding: 16,
  borderRadius: 'var(--pt-radius-md)',
  wrapperBorderRadius: 'var(--pt-radius-md)',
  wrapperBorder: true,
  headerRowBorder: true,
  rowBorder: true,
  columnBorder: false,
})
