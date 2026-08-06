import AddOutlinedIcon from '@mui/icons-material/AddOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PlayArrowOutlinedIcon from '@mui/icons-material/PlayArrowOutlined'
import SaveOutlinedIcon from '@mui/icons-material/SaveOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  Divider,
  FormControlLabel,
  IconButton,
  MenuItem,
  Pagination,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAuth } from '../../hooks/useAuth'
import * as customReportService from '../../services/customReportService'
import { PAGE_CONTAINER_SX, UI_RADIUS } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type {
  CustomReportBuilderState,
  CustomReportDataSource,
  CustomReportDefinition,
  CustomReportExecutionResult,
  CustomReportFieldOption,
  CustomReportSchemaEntry,
} from '../../types/customReports'
import { CUSTOM_REPORT_LIMITS } from '../../types/customReports'
import { buildCsvContent, downloadTextFile } from '../../utils/gridExport'

const DATA_SOURCE_LABELS: Record<CustomReportDataSource, string> = {
  sales: 'Vendas',
  payments: 'Pagamentos',
  checkins: 'Check-in',
  finance: 'Financeiro',
  crm: 'Clientes (CRM)',
}

const EMPTY_BUILDER: CustomReportBuilderState = {
  data_source: '',
  dimensions: [],
  metrics: [],
  calculated_metrics: [],
  filters: {},
}

/**
 * Construtor de relatórios personalizados (roadmap 5.6). Fonte de dados,
 * dimensões e métricas mostradas aqui vêm SEMPRE do schema retornado pelo
 * backend (`GET /custom-report-definitions/schema`, espelho de
 * `App\Support\Report\CustomReportFieldWhitelist`) — a tela nunca deixa o
 * usuário digitar nome de coluna livre, só escolher entre chaves já
 * whitelisted. Sem fonte de dados escolhida, nada é executado.
 */
export function CustomReportsPage() {
  const { hasPermission } = useAuth()
  const canCreate = hasPermission({ functionality: 'custom_reports', action: 'create', scope: 'tenant' })
  const canDelete = hasPermission({ functionality: 'custom_reports', action: 'delete', scope: 'tenant' })

  const [schema, setSchema] = useState<CustomReportSchemaEntry[] | null>(null)
  const [schemaError, setSchemaError] = useState<string | null>(null)

  const [builder, setBuilder] = useState<CustomReportBuilderState>(EMPTY_BUILDER)
  const [reportName, setReportName] = useState('')

  const [result, setResult] = useState<CustomReportExecutionResult | null>(null)
  const [page, setPage] = useState(1)
  const [perPage] = useState(20)
  const [isRunning, setIsRunning] = useState(false)
  const [runError, setRunError] = useState<string | null>(null)

  const [isSaving, setIsSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saveSuccess, setSaveSuccess] = useState(false)

  const [savedDefinitions, setSavedDefinitions] = useState<CustomReportDefinition[]>([])
  const [isLoadingSaved, setIsLoadingSaved] = useState(true)

  useEffect(() => {
    customReportService
      .getSchema()
      .then(setSchema)
      .catch((error: unknown) => setSchemaError(getApiErrorMessage(error, 'Não foi possível carregar os campos disponíveis.')))
  }, [])

  const loadSaved = useCallback(() => {
    setIsLoadingSaved(true)
    customReportService
      .listDefinitions()
      .then(({ items }) => setSavedDefinitions(items))
      .catch(() => setSavedDefinitions([]))
      .finally(() => setIsLoadingSaved(false))
  }, [])

  useEffect(() => {
    loadSaved()
  }, [loadSaved])

  const activeSchema = useMemo(
    () => schema?.find((entry) => entry.data_source === builder.data_source) ?? null,
    [schema, builder.data_source],
  )

  function resetResult() {
    setResult(null)
    setRunError(null)
    setSaveSuccess(false)
  }

  function handleDataSourceChange(value: CustomReportDataSource | '') {
    setBuilder({ ...EMPTY_BUILDER, data_source: value })
    resetResult()
  }

  function toggleDimension(key: string) {
    setBuilder((current) => {
      const has = current.dimensions.includes(key)
      if (has) return { ...current, dimensions: current.dimensions.filter((d) => d !== key) }
      if (current.dimensions.length >= CUSTOM_REPORT_LIMITS.maxDimensions) return current
      return { ...current, dimensions: [...current.dimensions, key] }
    })
    resetResult()
  }

  function toggleMetric(key: string) {
    setBuilder((current) => {
      const has = current.metrics.includes(key)
      if (has) return { ...current, metrics: current.metrics.filter((m) => m !== key) }
      if (current.metrics.length >= CUSTOM_REPORT_LIMITS.maxMetrics) return current
      return { ...current, metrics: [...current.metrics, key] }
    })
    resetResult()
  }

  function addCalculatedMetric() {
    setBuilder((current) => {
      if (current.calculated_metrics.length >= CUSTOM_REPORT_LIMITS.maxCalculatedMetrics) return current
      return { ...current, calculated_metrics: [...current.calculated_metrics, { name: '', formula: '' }] }
    })
  }

  function updateCalculatedMetric(index: number, field: 'name' | 'formula', value: string) {
    setBuilder((current) => ({
      ...current,
      calculated_metrics: current.calculated_metrics.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
    }))
  }

  function removeCalculatedMetric(index: number) {
    setBuilder((current) => ({
      ...current,
      calculated_metrics: current.calculated_metrics.filter((_, i) => i !== index),
    }))
  }

  function updateFilter(key: string, value: string) {
    setBuilder((current) => ({
      ...current,
      filters: { ...current.filters, [key]: value === '' ? undefined : value },
    }))
  }

  const canRun = builder.data_source !== '' && builder.metrics.length > 0

  async function handlePreview(targetPage = 1) {
    if (!canRun) return
    setIsRunning(true)
    setRunError(null)
    try {
      const executed = await customReportService.previewReport(builder, targetPage, perPage)
      setResult(executed)
      setPage(targetPage)
    } catch (error) {
      setResult(null)
      setRunError(getApiErrorMessage(error, 'Não foi possível executar o relatório. Verifique os campos escolhidos.'))
    } finally {
      setIsRunning(false)
    }
  }

  async function handleSave() {
    if (!canRun || !reportName.trim()) return
    setIsSaving(true)
    setSaveError(null)
    setSaveSuccess(false)
    try {
      await customReportService.createDefinition(reportName.trim(), builder)
      setSaveSuccess(true)
      setReportName('')
      loadSaved()
    } catch (error) {
      setSaveError(getApiErrorMessage(error, 'Não foi possível salvar o relatório. Verifique os campos e fórmulas.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleRunSaved(definition: CustomReportDefinition) {
    setBuilder({
      data_source: definition.data_source,
      dimensions: definition.dimensions,
      metrics: definition.metrics,
      calculated_metrics: definition.calculated_metrics,
      filters: definition.filters,
    })
    setReportName(definition.name)
    setIsRunning(true)
    setRunError(null)
    try {
      const executed = await customReportService.executeDefinition(definition.uuid, 1, perPage)
      setResult(executed)
      setPage(1)
    } catch (error) {
      setResult(null)
      setRunError(getApiErrorMessage(error, 'Não foi possível executar o relatório salvo.'))
    } finally {
      setIsRunning(false)
    }
  }

  async function handleDeleteSaved(definition: CustomReportDefinition) {
    if (!window.confirm(`Remover o relatório "${definition.name}"?`)) return
    try {
      await customReportService.deleteDefinition(definition.uuid)
      loadSaved()
    } catch {
      // best-effort, sem toast dedicado nesta fase
    }
  }

  function handleExportCsv() {
    if (!result || result.rows.length === 0) return
    const columns = Object.keys(result.rows[0])
    const headers = columns
    const rows = result.rows.map((row) => columns.map((col) => (row[col] === null || row[col] === undefined ? '' : String(row[col]))))
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-personalizado.csv', 'text/csv;charset=utf-8')
  }

  const dimensionOptions: CustomReportFieldOption[] = activeSchema?.dimensions ?? []
  const metricOptions: CustomReportFieldOption[] = activeSchema?.metrics ?? []
  const allMetricNames = builder.metrics

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1400 }}>
      <PageHeader
        title="Relatórios personalizados"
        subtitle="Monte relatórios combinando fonte de dados, dimensões, métricas e fórmulas calculadas."
      />

      {schemaError && <Alert severity="error" sx={{ mb: 2.5 }}>{schemaError}</Alert>}

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 }, mb: 3 }}>
        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
          1. Fonte de dados
        </Typography>

        <TextField
          select
          label="Fonte de dados"
          value={builder.data_source}
          onChange={(e) => handleDataSourceChange(e.target.value as CustomReportDataSource)}
          size="small"
          sx={{ minWidth: 260, mb: 3 }}
          disabled={!schema}
        >
          <MenuItem value="">
            <em>Selecione</em>
          </MenuItem>
          {(schema ?? []).map((entry) => (
            <MenuItem key={entry.data_source} value={entry.data_source}>
              {DATA_SOURCE_LABELS[entry.data_source] ?? entry.data_source}
            </MenuItem>
          ))}
        </TextField>

        {builder.data_source !== '' && (
          <>
            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1 }}>
              2. Dimensões (até {CUSTOM_REPORT_LIMITS.maxDimensions})
            </Typography>
            <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1, mb: 3 }}>
              {dimensionOptions.map((option) => (
                <FormControlLabel
                  key={option.key}
                  control={
                    <Checkbox
                      size="small"
                      checked={builder.dimensions.includes(option.key)}
                      onChange={() => toggleDimension(option.key)}
                      disabled={
                        !builder.dimensions.includes(option.key) && builder.dimensions.length >= CUSTOM_REPORT_LIMITS.maxDimensions
                      }
                    />
                  }
                  label={option.label}
                />
              ))}
            </Stack>

            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1 }}>
              3. Métricas (ao menos 1, até {CUSTOM_REPORT_LIMITS.maxMetrics})
            </Typography>
            <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1, mb: 3 }}>
              {metricOptions.map((option) => (
                <FormControlLabel
                  key={option.key}
                  control={
                    <Checkbox
                      size="small"
                      checked={builder.metrics.includes(option.key)}
                      onChange={() => toggleMetric(option.key)}
                      disabled={!builder.metrics.includes(option.key) && builder.metrics.length >= CUSTOM_REPORT_LIMITS.maxMetrics}
                    />
                  }
                  label={option.label}
                />
              ))}
            </Stack>

            <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                4. Métricas calculadas (até {CUSTOM_REPORT_LIMITS.maxCalculatedMetrics})
              </Typography>
              <Button
                size="small"
                startIcon={<AddOutlinedIcon />}
                onClick={addCalculatedMetric}
                disabled={builder.calculated_metrics.length >= CUSTOM_REPORT_LIMITS.maxCalculatedMetrics || allMetricNames.length === 0}
              >
                Adicionar fórmula
              </Button>
            </Stack>

            {allMetricNames.length === 0 && (
              <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 2 }}>
                Selecione ao menos uma métrica acima para poder criar uma fórmula.
              </Typography>
            )}

            <Stack spacing={1.5} sx={{ mb: 3 }}>
              {builder.calculated_metrics.map((calc, index) => (
                <Stack key={index} direction="row" spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                  <TextField
                    label="Nome"
                    size="small"
                    value={calc.name}
                    onChange={(e) => updateCalculatedMetric(index, 'name', e.target.value)}
                    sx={{ width: 180 }}
                    placeholder="ex: ticket_medio"
                  />
                  <TextField
                    label="Fórmula"
                    size="small"
                    value={calc.formula}
                    onChange={(e) => updateCalculatedMetric(index, 'formula', e.target.value)}
                    sx={{ flex: 1 }}
                    placeholder={`ex: ${allMetricNames[0] ?? 'metrica'} / ${allMetricNames[1] ?? 'outra_metrica'}`}
                    helperText={`Variáveis disponíveis: ${allMetricNames.join(', ')}`}
                  />
                  <IconButton size="small" onClick={() => removeCalculatedMetric(index)} aria-label="Remover fórmula">
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
            </Stack>

            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1 }}>
              5. Filtros
            </Typography>
            <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 2, mb: 3 }}>
              <TextField
                label="Data inicial"
                type="date"
                size="small"
                slotProps={{ inputLabel: { shrink: true } }}
                value={builder.filters.date_from ?? ''}
                onChange={(e) => updateFilter('date_from', e.target.value)}
              />
              <TextField
                label="Data final"
                type="date"
                size="small"
                slotProps={{ inputLabel: { shrink: true } }}
                value={builder.filters.date_to ?? ''}
                onChange={(e) => updateFilter('date_to', e.target.value)}
              />
              {dimensionOptions
                .filter((option) => builder.dimensions.includes(option.key))
                .map((option) => (
                  <TextField
                    key={option.key}
                    label={`Filtrar por ${option.label}`}
                    size="small"
                    value={builder.filters[option.key] ?? ''}
                    onChange={(e) => updateFilter(option.key, e.target.value)}
                  />
                ))}
            </Stack>

            <Divider sx={{ my: 2 }} />

            <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1.5, alignItems: 'center' }}>
              <Button
                variant="contained"
                startIcon={isRunning ? <CircularProgress size={16} color="inherit" /> : <PlayArrowOutlinedIcon />}
                onClick={() => handlePreview(1)}
                disabled={!canRun || isRunning}
                sx={{ borderRadius: UI_RADIUS.sm }}
              >
                Pré-visualizar
              </Button>

              {canCreate && (
                <>
                  <TextField
                    label="Nome do relatório"
                    size="small"
                    value={reportName}
                    onChange={(e) => setReportName(e.target.value)}
                    sx={{ minWidth: 240 }}
                  />
                  <Button
                    variant="outlined"
                    startIcon={isSaving ? <CircularProgress size={16} /> : <SaveOutlinedIcon />}
                    onClick={handleSave}
                    disabled={!canRun || !reportName.trim() || isSaving}
                    sx={{ borderRadius: UI_RADIUS.sm }}
                  >
                    Salvar
                  </Button>
                </>
              )}
            </Stack>

            {saveError && (
              <Alert severity="error" sx={{ mt: 2 }}>
                {saveError}
              </Alert>
            )}
            {saveSuccess && (
              <Alert severity="success" sx={{ mt: 2 }}>
                Relatório salvo com sucesso.
              </Alert>
            )}
          </>
        )}
      </Paper>

      {runError && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {runError}
        </Alert>
      )}

      {result && (
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 }, mb: 3 }}>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
              Resultado ({result.pagination.total} {result.pagination.total === 1 ? 'linha' : 'linhas'})
            </Typography>
            <Button
              size="small"
              startIcon={<FileDownloadOutlinedIcon />}
              onClick={handleExportCsv}
              disabled={result.rows.length === 0}
            >
              Exportar CSV
            </Button>
          </Stack>

          {result.rows.length === 0 ? (
            <Typography color="text.secondary">Nenhum resultado para os filtros escolhidos.</Typography>
          ) : (
            <Box sx={{ overflowX: 'auto' }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    {Object.keys(result.rows[0]).map((column) => (
                      <TableCell key={column} sx={{ fontWeight: 700 }}>
                        {column}
                      </TableCell>
                    ))}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {result.rows.map((row, index) => (
                    <TableRow key={index}>
                      {Object.keys(row).map((column) => (
                        <TableCell key={column}>{row[column] === null ? '—' : String(row[column])}</TableCell>
                      ))}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </Box>
          )}

          {result.pagination.last_page > 1 && (
            <Stack sx={{ mt: 2.5, alignItems: 'center' }}>
              <Pagination
                page={page}
                count={result.pagination.last_page}
                onChange={(_, value) => handlePreview(value)}
                color="primary"
                shape="rounded"
              />
            </Stack>
          )}
        </Paper>
      )}

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 } }}>
        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
          Relatórios salvos
        </Typography>

        {isLoadingSaved ? (
          <CircularProgress size={24} />
        ) : savedDefinitions.length === 0 ? (
          <Typography color="text.secondary">Nenhum relatório salvo ainda.</Typography>
        ) : (
          <Stack spacing={1}>
            {savedDefinitions.map((definition) => (
              <Stack
                key={definition.uuid}
                direction="row"
                sx={{
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  p: 1.5,
                  borderRadius: UI_RADIUS.sm,
                  border: '1px solid var(--pt-border)',
                }}
              >
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <Typography sx={{ fontWeight: 600 }}>{definition.name}</Typography>
                  <Chip size="small" label={DATA_SOURCE_LABELS[definition.data_source] ?? definition.data_source} />
                </Stack>
                <Stack direction="row" spacing={1}>
                  <IconButton size="small" onClick={() => handleRunSaved(definition)} aria-label="Executar">
                    <PlayArrowOutlinedIcon fontSize="small" />
                  </IconButton>
                  {canDelete && (
                    <IconButton size="small" onClick={() => handleDeleteSaved(definition)} aria-label="Remover">
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  )}
                </Stack>
              </Stack>
            ))}
          </Stack>
        )}
      </Paper>
    </Box>
  )
}

