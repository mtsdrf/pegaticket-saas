import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PictureAsPdfOutlinedIcon from '@mui/icons-material/PictureAsPdfOutlined'
import { Alert, Box, Button, CircularProgress, Skeleton, Stack } from '@mui/material'
import { AgGridReact } from 'ag-grid-react'
import {
  ColumnApiModule,
  CustomFilterModule,
  InfiniteRowModelModule,
  LocaleModule,
  ModuleRegistry,
  NumberFilterModule,
  PaginationModule,
  RenderApiModule,
  RowApiModule,
  TextFilterModule,
  ValidationModule,
} from 'ag-grid-community'
import type { ColDef, GridApi, GridReadyEvent, IDatasource, IGetRowsParams } from 'ag-grid-community'
import { AG_GRID_LOCALE_BR } from '@ag-grid-community/locale'
import { useEffect, useMemo, useRef, useState } from 'react'
import { getApiErrorMessage } from '../../types/api'
import { maskatsGridTheme } from '../../utils/agGridTheme'
import { buildBackendFilters, buildCsvContent, buildExportTable, downloadTextFile } from '../../utils/gridExport'
import { ServerGridBooleanFilter } from './ServerGridBooleanFilter'
import { ServerGridEmptyOverlay, type ServerGridEmptyStateConfig } from './ServerGridEmptyOverlay'
import { ServerGridLoadingOverlay } from './ServerGridLoadingOverlay'
import { ServerGridPrintExport } from './ServerGridPrintExport'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from './serverGridTypes'

function toColDef<T>(column: ServerGridColumn<T>): ColDef {
  const filterType = column.filterType ?? 'text'

  const base: ColDef = {
    field: column.field,
    headerName: column.headerName,
    sortable: column.sortable ?? filterType !== 'none',
    resizable: true,
    suppressMovable: true,
    ...(column.width ? { width: column.width } : { flex: 1, minWidth: 140 }),
    // O Infinite Row Model pré-aloca linhas "vazias" (`data: undefined`)
    // enquanto o bloco correspondente ainda não voltou do backend. Essa é a
    // "tela de carregamento" que o usuário realmente vê durante paginação/
    // filtro/ordenação — o overlay `loadingOverlayComponent` do ag-Grid quase
    // nunca aparece na prática, porque `infiniteInitialRowCount` já cria
    // linhas-placeholder de cara, então a condição de "0 linhas" que
    // dispara o overlay raramente ocorre (achado real via Playwright com
    // requisição throttled, 2026-07-10). Por isso todo cell (com ou sem
    // `cellRenderer` próprio) mostra uma barra de skeleton enquanto
    // `data` não chegou, em vez de célula em branco.
    cellRenderer: (params: { data?: T }) => {
      if (!params.data) {
        return (
          <Box sx={{ display: 'flex', alignItems: 'center', height: '100%' }}>
            <Skeleton variant="rounded" height={14} sx={{ width: '70%', borderRadius: 'var(--mk-radius-sm)' }} />
          </Box>
        )
      }
      if (column.cellRenderer) return column.cellRenderer(params.data)
      const rawValue = (params.data as Record<string, unknown>)[column.field]
      return rawValue === null || rawValue === undefined ? '' : String(rawValue)
    },
  }

  if (filterType === 'none') {
    base.filter = false
  } else if (filterType === 'text') {
    base.filter = 'agTextColumnFilter'
    // `floatingFilter` deixa um input sempre visível abaixo do cabeçalho —
    // sem isso, o filtro só existe atrás do ícone de funil (achado real: o
    // usuário não encontrava o filtro de coluna). Sem `buttons`, o floating
    // filter E o popup completo aplicam sozinhos ao digitar (com debounce) —
    // com `buttons: ['apply','reset']` configurado, testado via Playwright
    // que o floating filter passa a exigir Enter (não só o popup) pra
    // confirmar o valor, o que ninguém descobre sozinho na prática.
    base.floatingFilter = true
    base.filterParams = {
      filterOptions: ['contains'],
      maxNumConditions: 1,
      debounceMs: 400,
    }
  } else if (filterType === 'number') {
    base.filter = 'agNumberColumnFilter'
    base.floatingFilter = true
    base.filterParams = {
      filterOptions: ['equals', 'greaterThan', 'lessThan', 'inRange'],
      maxNumConditions: 1,
      debounceMs: 400,
    }
  } else if (filterType === 'boolean') {
    base.filter = ServerGridBooleanFilter
  }

  return base
}

ModuleRegistry.registerModules([
  InfiniteRowModelModule,
  TextFilterModule,
  NumberFilterModule,
  // ServerGridBooleanFilter (colunas is_active) é um componente de filtro
  // custom — o ag-Grid 36 exige este módulo registrado à parte dos filtros
  // nativos (agTextColumnFilter/agNumberColumnFilter), senão lança o erro
  // #200 "CustomFilterModule is not registered" ao montar a coluna.
  CustomFilterModule,
  PaginationModule,
  LocaleModule,
  ColumnApiModule,
  RowApiModule,
  RenderApiModule,
  ValidationModule,
])

/**
 * Altura default do grid: preenche a viewport em vez de um valor fixo (era
 * `560px` pra qualquer tela, deixando muito espaço vazio embaixo em
 * telas grandes). Medido via Playwright renderizando `/produtos` (sem
 * breadcrumb) em mobile/tablet/desktop: `100dvh` menos AppBar + padding do
 * `<main>` + `PageHeader` + padding do `Paper` que envolve o grid, com uma
 * folga pequena pro padding inferior do `<main>` ficar visualmente simétrico
 * ao superior. Só existem 2 faixas reais (o `AppBar`/padding do layout não
 * mudam entre sm/md/lg/xl, só `xs` é diferente).
 * Telas com breadcrumb (Categorias/Tipos de produto) têm o cabeçalho ~28px
 * mais alto — sobra uma rolagem externa mínima (<15px) nessas 2 telas, o
 * que é aceitável (ver pedido original) em troca de não repetir essa conta
 * em cada uma das 4 páginas que usam este componente.
 */
const DEFAULT_GRID_HEIGHT = {
  xs: 'calc(100dvh - 264px)',
  sm: 'calc(100dvh - 244px)',
}

/** Mínimo utilizável (~6-8 linhas) mesmo em viewports muito baixas (notebook com janela curta). */
const MIN_GRID_HEIGHT = 420

/** `per_page` máximo aceito pelas `List{Feature}Request` do backend (ver `.claude/memory/api-patterns.md`) — usado pra minimizar o número de requisições ao exportar todas as páginas. */
const EXPORT_PAGE_SIZE = 100

/** Trava de segurança contra loop infinito se `total` vier inconsistente do backend (não é um limite de produto, é só uma rede de proteção). */
const MAX_EXPORT_ROWS = 50_000

interface ServerDataGridProps<T> {
  columns: ServerGridColumn<T>[]
  /** Chamado pelo grid a cada mudança de página/ordenação/filtro — mescla com os filtros manuais da tela (ex.: busca por nome) e chama a service function existente. */
  fetchPage: (params: ServerGridFetchParams) => Promise<ServerGridFetchResult<T>>
  rowIdField: keyof T & string
  pageSize?: number
  /** Sem valor, usa a altura responsiva default (`DEFAULT_GRID_HEIGHT`) — só passe um número aqui pra um caso especial que precise de altura fixa. */
  height?: number
  emptyState: ServerGridEmptyStateConfig
  /** Expõe a `GridApi` pra quem monta o grid (ex.: recarregar depois de excluir um registro, sem `window.location.reload()`). */
  onGridReady?: (api: GridApi) => void
  /**
   * Nome base do arquivo exportado (também usado como título do PDF impresso).
   * Sem valor, os botões de exportar CSV/PDF não aparecem — nem toda tela de
   * listagem precisa deles (ver roadmap 2.6).
   */
  exportFileName?: string
  /**
   * Notifica a tela dos filtros de coluna atuais (já traduzidos pros params
   * do backend) sempre que eles mudam — não dispara em troca de página/
   * ordenação com os mesmos filtros. Usado por telas que mostram um resumo
   * agregado que precisa acompanhar o filtro do grid (ex.: relatório de
   * pedidos), reaproveitando o mesmo estado de filtro, sem duplicá-lo.
   */
  onFiltersChange?: (filters: Record<string, string | number | boolean | undefined>) => void
}

/**
 * Grid server-side genérico (ag-Grid Community, Infinite Row Model — o
 * "Server-Side Row Model" completo é Enterprise, não usar). Paginação,
 * ordenação e filtro (texto/número/boolean) são delegados ao backend via
 * `fetchPage`; o componente só traduz o estado interno do ag-Grid pros
 * parâmetros de query já combinados com o backend (ver `.claude/memory/
 * api-patterns.md`).
 */
export function ServerDataGrid<T extends object>({
  columns,
  fetchPage,
  rowIdField,
  pageSize = 20,
  height,
  emptyState,
  onGridReady,
  exportFileName,
  onFiltersChange,
}: ServerDataGridProps<T>) {
  const gridApiRef = useRef<GridApi | null>(null)
  // Ref (não dep do useMemo) pra não recriar o datasource — recriar dispara
  // um refresh completo do Infinite Row Model a cada render do pai.
  const onFiltersChangeRef = useRef(onFiltersChange)
  onFiltersChangeRef.current = onFiltersChange
  const lastFiltersRef = useRef<string | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [isExportingCsv, setIsExportingCsv] = useState(false)
  const [isExportingPdf, setIsExportingPdf] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)
  const [printExport, setPrintExport] = useState<{
    title: string
    generatedAt: string
    headers: string[]
    rows: string[][]
  } | null>(null)

  const colDefs = useMemo(() => columns.map((column) => toColDef(column)), [columns])
  const gridMinWidth = useMemo(
    () => columns.reduce((total, column) => total + (column.width ?? 140), 0),
    [columns],
  )

  const datasource = useMemo<IDatasource>(
    () => ({
      getRows: (params: IGetRowsParams) => {
        const { startRow, sortModel, filterModel, successCallback, failCallback } = params
        const page = Math.floor(startRow / pageSize) + 1

        const sortEntry = sortModel[0]
        const sortColumn = sortEntry ? columns.find((column) => column.field === sortEntry.colId) : undefined
        const sortBy = sortColumn ? sortColumn.backendField ?? sortColumn.field : undefined
        const sortDir = sortEntry?.sort

        const filters = buildBackendFilters(columns, (filterModel ?? {}) as Record<string, unknown>)

        const serializedFilters = JSON.stringify(filters)
        if (serializedFilters !== lastFiltersRef.current) {
          lastFiltersRef.current = serializedFilters
          onFiltersChangeRef.current?.(filters)
        }

        fetchPage({ page, perPage: pageSize, sortBy, sortDir, filters })
          .then((result) => {
            setLoadError(null)
            successCallback(result.rows, result.total)
          })
          .catch((error) => {
            setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados agora.'))
            failCallback()
          })
      },
    }),
    [columns, fetchPage, pageSize],
  )

  function handleGridReady(event: GridReadyEvent) {
    gridApiRef.current = event.api
    onGridReady?.(event.api)
  }

  function handleRetry() {
    setLoadError(null)
    gridApiRef.current?.refreshInfiniteCache()
  }

  // Dispara o diálogo de impressão assim que a tabela "invisível" é montada
  // com os dados prontos, e limpa o estado quando o usuário fecha o diálogo
  // (evento `afterprint`) — cobre tanto "Salvar como PDF" quanto "Cancelar".
  useEffect(() => {
    if (!printExport) return

    const timer = window.setTimeout(() => window.print(), 50)
    const handleAfterPrint = () => {
      setPrintExport(null)
      setIsExportingPdf(false)
    }
    window.addEventListener('afterprint', handleAfterPrint)

    return () => {
      window.clearTimeout(timer)
      window.removeEventListener('afterprint', handleAfterPrint)
    }
  }, [printExport])

  /** Busca TODAS as páginas que batem com o filtro/ordenação atuais do grid — não só as já carregadas no cache do Infinite Row Model. */
  async function fetchAllRowsForExport(): Promise<T[]> {
    const api = gridApiRef.current
    const sortState = api
      ?.getColumnState()
      .filter((column) => column.sort)
      .sort((a, b) => (a.sortIndex ?? 0) - (b.sortIndex ?? 0))[0]
    const sortColumn = sortState ? columns.find((column) => column.field === sortState.colId) : undefined
    const sortBy = sortColumn ? sortColumn.backendField ?? sortColumn.field : undefined
    const sortDir = (sortState?.sort ?? undefined) as 'asc' | 'desc' | undefined

    const filterModel = (api?.getFilterModel() ?? {}) as Record<string, unknown>
    const filters = buildBackendFilters(columns, filterModel)

    let allRows: T[] = []
    const maxPages = Math.ceil(MAX_EXPORT_ROWS / EXPORT_PAGE_SIZE)

    for (let page = 1; page <= maxPages; page += 1) {
      const result = await fetchPage({ page, perPage: EXPORT_PAGE_SIZE, sortBy, sortDir, filters })
      allRows = allRows.concat(result.rows)
      if (result.rows.length === 0 || allRows.length >= result.total) break
    }

    return allRows
  }

  async function handleExportCsv() {
    if (!exportFileName) return
    setExportError(null)
    setIsExportingCsv(true)
    try {
      const rows = await fetchAllRowsForExport()
      const { headers, body } = buildExportTable(columns, rows)
      downloadTextFile(buildCsvContent(headers, body), `${exportFileName}.csv`, 'text/csv;charset=utf-8')
    } catch (error) {
      setExportError(getApiErrorMessage(error, 'Não foi possível exportar o CSV agora.'))
    } finally {
      setIsExportingCsv(false)
    }
  }

  async function handleExportPdf() {
    if (!exportFileName) return
    setExportError(null)
    setIsExportingPdf(true)
    try {
      const rows = await fetchAllRowsForExport()
      const { headers, body } = buildExportTable(columns, rows)
      // `isExportingPdf` só volta a `false` no `afterprint` (ver efeito acima) —
      // o botão fica desabilitado durante todo o fluxo, não só na busca dos dados.
      setPrintExport({ title: exportFileName, generatedAt: new Date().toLocaleString('pt-BR'), headers, rows: body })
    } catch (error) {
      setExportError(getApiErrorMessage(error, 'Não foi possível exportar o PDF agora.'))
      setIsExportingPdf(false)
    }
  }

  return (
    <Box>
      {exportFileName && (
        <Stack
          direction="row"
          spacing={1}
          sx={{ mb: 1.5, flexWrap: 'wrap', justifyContent: { xs: 'stretch', sm: 'flex-end' } }}
        >
          <Button
            variant="outlined"
            size="small"
            startIcon={isExportingCsv ? <CircularProgress size={16} /> : <FileDownloadOutlinedIcon fontSize="small" />}
            disabled={isExportingCsv || isExportingPdf}
            onClick={() => void handleExportCsv()}
            sx={{ minHeight: 44, flex: { xs: 1, sm: 'initial' } }}
          >
            {isExportingCsv ? 'Exportando...' : 'Exportar CSV'}
          </Button>
          <Button
            variant="outlined"
            size="small"
            startIcon={isExportingPdf ? <CircularProgress size={16} /> : <PictureAsPdfOutlinedIcon fontSize="small" />}
            disabled={isExportingCsv || isExportingPdf}
            onClick={() => void handleExportPdf()}
            sx={{ minHeight: 44, flex: { xs: 1, sm: 'initial' } }}
          >
            {isExportingPdf ? 'Exportando...' : 'Exportar PDF'}
          </Button>
        </Stack>
      )}

      {exportError && (
        <Alert severity="error" variant="outlined" sx={{ mb: 1.5 }}>
          {exportError}
        </Alert>
      )}

      {loadError ? (
        <Alert
          severity="error"
          variant="outlined"
          sx={{ mb: 1.5 }}
          action={
            <Button color="inherit" size="small" onClick={handleRetry}>
              Tentar de novo
            </Button>
          }
        >
          {loadError}
        </Alert>
      ) : (
        <Box sx={{ width: '100%', overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
          <Box
            sx={{
              width: `max(100%, ${gridMinWidth}px)`,
            }}
          >
            <Box
              sx={{
                height: height ?? DEFAULT_GRID_HEIGHT,
                minHeight: MIN_GRID_HEIGHT,
                width: '100%',
                // Quando a carga server-side falha (ex.: acesso negado, erro 500,
                // timeout), o ag-Grid não deve continuar montado mostrando linhas
                // skeleton ou dando a sensação de "loading infinito". Nesse
                // estado, só a mensagem de erro acima fica visível.
                // Barra de paginação padronizada: o ag-Grid mistura texto, selects,
                // resumo e ícones em elementos inline com alturas distintas; sem um
                // alinhamento explícito, as setas acabam "caídas" visualmente em
                // relação ao restante do painel. Centraliza tudo e dá o mesmo box
                // de interação para os controles.
                '& .ag-paging-panel': {
                  minHeight: 56,
                  flexWrap: 'wrap',
                  gap: 1,
                  alignItems: 'center',
                  justifyContent: 'space-between',
                },
                '& .ag-paging-row-summary-panel, & .ag-paging-page-summary-panel, & .ag-paging-page-size': {
                  display: 'inline-flex',
                  alignItems: 'center',
                  minHeight: 44,
                },
                '& .ag-paging-button': {
                  minWidth: 44,
                  minHeight: 44,
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  borderRadius: '10px',
                },
                '& .ag-paging-button .ag-icon': {
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  lineHeight: 1,
                },
                '& .ag-paging-description, & .ag-paging-number': {
                  display: 'inline-flex',
                  alignItems: 'center',
                  minHeight: 32,
                },
                '& .ag-paging-page-summary-panel > *': {
                  marginInline: '0.2rem',
                },
                '& .ag-paging-page-summary-panel .ag-paging-description:first-of-type': {
                  marginRight: '0.45rem',
                },
                '& .ag-paging-page-summary-panel .ag-paging-number + .ag-paging-description': {
                  marginLeft: '0.45rem',
                  marginRight: '0.45rem',
                },
              }}
            >
              <AgGridReact
                theme={maskatsGridTheme}
                // Locale oficial pt-BR do próprio ag-Grid (`@ag-grid-community/locale`,
                // mesma versão 36.0.0 do core) — cobre menu de filtro, paginação,
                // aria-labels etc. Não existe fallback manual porque a tradução
                // oficial já é completa; overlays custom (loading/empty) já eram
                // texto próprio em pt-BR desde que foram criados.
                localeText={AG_GRID_LOCALE_BR}
                columnDefs={colDefs}
                rowModelType="infinite"
                datasource={datasource}
                cacheBlockSize={pageSize}
                maxBlocksInCache={10}
                infiniteInitialRowCount={pageSize}
                pagination
                paginationPageSize={pageSize}
                getRowId={(params) => {
                  // Mesma ressalva do cellRenderer: linhas ainda não carregadas
                  // pelo Infinite Row Model chegam com `data` undefined.
                  const data = params.data as T | undefined
                  return data ? String(data[rowIdField]) : `pending-${Math.random()}`
                }}
                onGridReady={handleGridReady}
                noRowsOverlayComponent={ServerGridEmptyOverlay}
                noRowsOverlayComponentParams={emptyState}
                loadingOverlayComponent={ServerGridLoadingOverlay}
                animateRows
              />
            </Box>
          </Box>
        </Box>
      )}

      {printExport && (
        <ServerGridPrintExport
          title={printExport.title}
          generatedAt={printExport.generatedAt}
          headers={printExport.headers}
          rows={printExport.rows}
        />
      )}
    </Box>
  )
}
