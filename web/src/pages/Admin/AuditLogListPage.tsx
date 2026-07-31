import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Box, Dialog, DialogActions, DialogContent, DialogTitle, Button, IconButton, Stack, Tooltip, Typography, useMediaQuery } from '@mui/material'
import { useTheme } from '@mui/material/styles'
import { useCallback, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useInitialLoadGate } from '../../hooks/useInitialLoadGate'
import * as auditLogService from '../../services/auditLogService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { AuditLog } from '../../types/admin'
import { formatDateTimeBR } from '../../utils/format'

/** Rótulo em português do evento cru vindo da API (`created|updated|deleted`, ver `AuditLogResource`). Eventos desconhecidos caem no valor original — não bloqueia exibição de eventos futuros ainda não mapeados aqui. */
const EVENT_LABELS_PT: Record<string, string> = {
  created: 'Criado',
  updated: 'Atualizado',
  deleted: 'Excluído',
}

/** `App\Models\Order\Order` -> `Order` — só a última parte do FQCN é útil pra quem lê a tela, o namespace completo só faz sentido em log técnico. */
function shortAuditableType(auditableType: string | null): string {
  if (!auditableType) return '—'
  const segments = auditableType.split('\\')
  return segments[segments.length - 1]
}

export function AuditLogListPage() {
  const muiTheme = useTheme()
  const isMobile = useMediaQuery(muiTheme.breakpoints.down('sm'))
  const isLoading = useInitialLoadGate(() => auditLogService.listAuditLogs({ per_page: 1 }))
  const [detailTarget, setDetailTarget] = useState<AuditLog | null>(null)

  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<AuditLog>> => {
    const result = await auditLogService.listAuditLogs({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])

  const columns = useMemo<ServerGridColumn<AuditLog>[]>(() => {
    const cols: ServerGridColumn<AuditLog>[] = [
      {
        field: 'created_at',
        headerName: 'Data/hora',
        width: 170,
        // Sem filtro de coluna nesta v1: o backend só aceita intervalo
        // (`created_at_min`/`created_at_max`, ver `ListAuditLogRequest`), não
        // "contains" — `serverGridTypes.ts` ainda não tem `filterType: 'date'`
        // pronto, e mandar um filtro de texto simples aqui geraria um
        // parâmetro (`created_at`) que o backend ignora silenciosamente
        // (pior que não ter filtro: parece funcionar e não funciona).
        // Ordenação continua disponível (é inclusive o sort padrão do backend).
        sortable: true,
        filterType: 'none',
        cellRenderer: (row) => formatDateTimeBR(row.created_at),
      },
      { field: 'user_name', headerName: 'Usuário', width: 180, filterType: 'text', cellRenderer: (row) => row.user_name ?? 'Sistema' },
      {
        field: 'event',
        headerName: 'Evento',
        width: 130,
        filterType: 'text',
        cellRenderer: (row) => EVENT_LABELS_PT[row.event] ?? row.event,
        exportValue: (row) => EVENT_LABELS_PT[row.event] ?? row.event,
      },
      {
        field: 'auditable_type',
        headerName: 'Recurso',
        width: 160,
        filterType: 'text',
        cellRenderer: (row) => shortAuditableType(row.auditable_type),
        exportValue: (row) => shortAuditableType(row.auditable_type),
      },
    ]

    if (!isMobile) {
      cols.push(
        {
          field: 'route',
          headerName: 'Método/Rota',
          width: 220,
          sortable: false,
          filterType: 'none',
          cellRenderer: (row) => [row.method, row.route].filter(Boolean).join(' ') || '—',
          exportValue: (row) => [row.method, row.route].filter(Boolean).join(' ') || '—',
        },
        { field: 'ip', headerName: 'IP', width: 140, sortable: false, filterType: 'none', cellRenderer: (row) => row.ip ?? '—' },
      )
    }

    cols.push({
      field: 'uuid',
      headerName: 'Ações',
      width: 100,
      sortable: false,
      filterType: 'none',
      exportable: false,
      cellRenderer: (row) => (
        <Tooltip title="Ver detalhes" arrow>
          <IconButton size="small" onClick={() => setDetailTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}>
            <VisibilityOutlinedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      ),
    })

    return cols
  }, [isMobile])

  return (
    <>
      <CrudListPage
        title="Auditoria"
        subtitle="Consulte o histórico de alterações registradas no sistema."
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: isMobile ? 460 : 900 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="auditoria"
              emptyState={{
                icon: <HistoryOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhum registro de auditoria',
                description: 'Ainda não há alterações registradas no sistema.',
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <Dialog open={detailTarget !== null} onClose={() => setDetailTarget(null)} fullWidth maxWidth="md">
        <DialogTitle>Detalhes da auditoria</DialogTitle>
        <DialogContent dividers>
          {detailTarget && (
            <Stack spacing={2}>
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
                  {formatDateTimeBR(detailTarget.created_at)} · {detailTarget.user_name ?? 'Sistema'} ·{' '}
                  {EVENT_LABELS_PT[detailTarget.event] ?? detailTarget.event} · {shortAuditableType(detailTarget.auditable_type)}
                </Typography>
              </Stack>

              <JsonBlock label="Valores anteriores" value={detailTarget.old_values} />
              <JsonBlock label="Valores novos" value={detailTarget.new_values} />
              <JsonBlock label="Metadados" value={detailTarget.meta} />
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDetailTarget(null)}>Fechar</Button>
        </DialogActions>
      </Dialog>
    </>
  )
}

function JsonBlock({ label, value }: { label: string; value: Record<string, unknown> | null }) {
  return (
    <Box>
      <Typography variant="subtitle2" sx={{ mb: 0.5 }}>
        {label}
      </Typography>
      <Box
        component="pre"
        sx={{
          m: 0,
          p: 1.5,
          ...SOFT_PANEL_SX,
          fontFamily: 'monospace',
          fontSize: '0.8125rem',
          whiteSpace: 'pre-wrap',
          wordBreak: 'break-word',
          overflow: 'auto',
          maxHeight: 260,
        }}
      >
        {value ? JSON.stringify(value, null, 2) : 'Nenhum valor registrado.'}
      </Box>
    </Box>
  )
}
