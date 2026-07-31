import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  IconButton,
  MenuItem,
  Snackbar,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { ACCESS } from '../../access/requirements'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useInitialLoadGate } from '../../hooks/useInitialLoadGate'
import * as paymentAdminService from '../../services/paymentAdminService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { PaymentIssue, PaymentIssueType } from '../../types/paymentAdmin'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

const TYPE_OPTIONS: { value: PaymentIssueType | ''; label: string }[] = [
  { value: '', label: 'Todos os tipos' },
  { value: 'payment_divergent', label: 'Pagamento divergente' },
  { value: 'idempotency_ambiguous', label: 'Idempotência ambígua' },
  { value: 'invoice_disputed', label: 'Fatura contestada' },
  { value: 'webhook_failed', label: 'Falha no processamento' },
]

const TYPE_LABELS: Record<PaymentIssueType, string> = {
  payment_divergent: 'Pagamento divergente',
  idempotency_ambiguous: 'Idempotência ambígua',
  invoice_disputed: 'Fatura contestada',
  webhook_failed: 'Falha no processamento',
}

/**
 * Painel de pendências de pagamento/assinatura (roadmap 2026-07-24) —
 * cross-tenant, exclusivo do staff interno da PegaTicket. Segue a mesma
 * convenção visual de `AuditLogListPage`/`ReconciliationPage`: filtro no
 * toolbar (backend só aceita `type`, sem filtro/ordenação por coluna, ver
 * `ListPaymentIssuesRequest`), `ServerDataGrid` para listagem, e ação de
 * reprocessar por linha com confirmação (dispara efeito real no provedor de
 * pagamento).
 */
export function PaymentIssuesListPage() {
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => paymentAdminService.listPaymentIssues({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [type, setType] = useState<PaymentIssueType | ''>('')

  const [reprocessTarget, setReprocessTarget] = useState<PaymentIssue | null>(null)
  const [isReprocessing, setIsReprocessing] = useState(false)
  const [reprocessError, setReprocessError] = useState<string | null>(null)
  const [feedback, setFeedback] = useState<{ severity: 'success' | 'error'; message: string } | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<PaymentIssue>> => {
      const result = await paymentAdminService.listPaymentIssues({ type: type || undefined, page, per_page: perPage })
      return { rows: result.items, total: result.pagination.total }
    },
    [type],
  )

  async function handleConfirmReprocess() {
    if (!reprocessTarget) return
    setIsReprocessing(true)
    setReprocessError(null)
    try {
      await paymentAdminService.reprocessPaymentIssue(reprocessTarget.issue_type, reprocessTarget.reference)
      setReprocessTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
      setFeedback({ severity: 'success', message: 'Pendência reprocessada com sucesso.' })
    } catch (err) {
      setReprocessError(getApiErrorMessage(err, 'Não foi possível reprocessar esta pendência agora. Tente novamente em instantes.'))
    } finally {
      setIsReprocessing(false)
    }
  }

  const canReprocess = can(ACCESS.adminPaymentIssuesUpdate)

  const columns = useMemo<ServerGridColumn<PaymentIssue>[]>(
    () => [
      {
        field: 'occurred_at',
        headerName: 'Ocorrido em',
        width: 170,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => formatDateTimeBR(row.occurred_at),
      },
      {
        field: 'issue_type',
        headerName: 'Tipo',
        width: 200,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => TYPE_LABELS[row.issue_type] ?? row.issue_type,
        exportValue: (row) => TYPE_LABELS[row.issue_type] ?? row.issue_type,
      },
      {
        field: 'tenant',
        headerName: 'Empresa',
        width: 200,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => row.tenant?.name ?? '—',
        exportValue: (row) => row.tenant?.name ?? '',
      },
      {
        field: 'amount',
        headerName: 'Valor',
        width: 130,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (row.amount !== null ? formatCurrency(row.amount) : '—'),
        exportValue: (row) => (row.amount !== null ? formatCurrency(row.amount) : ''),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 150,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => <Chip size="small" label={row.status} sx={{ fontWeight: 600, color: 'var(--pt-text)', ...SOFT_PANEL_SX }} />,
        exportValue: (row) => row.status,
      },
      {
        field: 'reference',
        headerName: 'Referência',
        width: 160,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (
          <Typography sx={{ fontFamily: 'monospace', fontSize: 12 }} title={row.reference}>
            {row.reference}
          </Typography>
        ),
      },
      {
        field: 'reprocessable',
        headerName: 'Ações',
        width: 110,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) =>
          row.reprocessable && canReprocess ? (
            <Tooltip title="Reprocessar" arrow>
              <IconButton
                size="small"
                onClick={() => {
                  setReprocessError(null)
                  setReprocessTarget(row)
                }}
                sx={{ minWidth: 44, minHeight: 44 }}
              >
                <ReplayOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          ) : (
            '—'
          ),
      },
    ],
    [canReprocess],
  )

  return (
    <>
      <CrudListPage
        title="Pendências de pagamento"
        subtitle="Acompanhe e resolva itens travados de pagamento e assinatura entre todas as empresas."
        toolbar={
          <TextField
            select
            label="Tipo"
            size="small"
            value={type}
            onChange={(event) => setType(event.target.value as PaymentIssueType | '')}
            sx={{ minWidth: 240 }}
          >
            {TYPE_OPTIONS.map((option) => (
              <MenuItem key={option.value || 'all'} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </TextField>
        }
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 1080 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="reference"
              exportFileName="pendencias-pagamento"
              onGridReady={(api) => { gridApiRef.current = api }}
              emptyState={{
                icon: <ReportProblemOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhuma pendência encontrada',
                description: 'Não há itens travados de pagamento ou assinatura no momento.',
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <Dialog open={reprocessTarget !== null} onClose={isReprocessing ? undefined : () => setReprocessTarget(null)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 600 }}>Reprocessar pendência</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ color: 'var(--pt-text)' }}>
            Tem certeza que deseja reprocessar esta pendência
            {reprocessTarget?.tenant ? (
              <>
                {' '}
                da empresa <strong>{reprocessTarget.tenant.name}</strong>
              </>
            ) : null}
            ? Essa ação tenta confirmar o pagamento junto ao provedor agora.
          </DialogContentText>
          {reprocessError && (
            <Alert severity="error" sx={{ mt: 2 }}>
              {reprocessError}
            </Alert>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
          <Button onClick={() => setReprocessTarget(null)} disabled={isReprocessing} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
            Cancelar
          </Button>
          <Button
            onClick={() => void handleConfirmReprocess()}
            disabled={isReprocessing}
            color="primary"
            variant="contained"
            sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
          >
            {isReprocessing ? 'Reprocessando…' : 'Reprocessar'}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar
        open={feedback !== null}
        autoHideDuration={5000}
        onClose={() => setFeedback(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        {feedback ? (
          <Alert severity={feedback.severity} onClose={() => setFeedback(null)} sx={{ width: '100%' }}>
            {feedback.message}
          </Alert>
        ) : undefined}
      </Snackbar>
    </>
  )
}
