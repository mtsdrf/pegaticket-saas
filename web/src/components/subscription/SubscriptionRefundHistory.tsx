import {
  Alert,
  Box,
  Chip,
  Pagination,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import * as subscriptionService from '../../services/subscriptionService'
import { getApiErrorMessage } from '../../types/api'
import type { PaginationMeta } from '../../types/pagination'
import type { RefundItem } from '../../types/subscription'
import { formatCurrency, formatDateBR } from '../../utils/format'

const PER_PAGE = 10

const REFUND_STATUS_LABELS: Record<string, string> = {
  requested: 'Solicitado',
  processing: 'Em processamento',
  done: 'Concluído',
  failed: 'Não concluído',
  approved: 'Concluído',
  rejected: 'Não concluído',
  in_process: 'Em processamento',
}

const REFUND_TYPE_LABELS: Record<string, string> = {
  total: 'Integral',
  partial: 'Parcial',
}

/** Status que ainda dependem de acompanhamento — recebem destaque visual. */
const PENDING_STATUSES = new Set(['requested', 'processing', 'in_process'])
const FAILED_STATUSES = new Set(['failed', 'rejected'])

/**
 * Histórico unificado de estornos do tenant (`GET /subscription/refunds`) —
 * reúne venda paga cancelada, arrependimento de assinatura e
 * contestação/chargeback numa única lista, cada um identificado pelo texto
 * já descritivo de `reason`. Só visualização: solicitar um novo estorno
 * continua nos fluxos próprios (cancelamento de venda, arrependimento).
 */
export function SubscriptionRefundHistory() {
  const [page, setPage] = useState(1)
  const [refunds, setRefunds] = useState<RefundItem[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setIsLoading(true)
    setError(null)

    subscriptionService
      .listRefunds(page, PER_PAGE)
      .then((result) => {
        if (cancelled) return
        setRefunds(result.items)
        setPagination(result.pagination)
      })
      .catch((loadError: unknown) => {
        if (cancelled) return
        setError(getApiErrorMessage(loadError, 'Não foi possível carregar o histórico de reembolsos agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [page])

  if (isLoading && refunds.length === 0) {
    return (
      <Stack spacing={1}>
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} variant="rounded" height={40} />
        ))}
      </Stack>
    )
  }

  if (error && refunds.length === 0) {
    return <Alert severity="error">{error}</Alert>
  }

  if (refunds.length === 0) {
    return (
      <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', py: 1 }}>
        Nenhum reembolso registrado até o momento.
      </Typography>
    )
  }

  return (
    <Stack spacing={1.5}>
      <TableContainer sx={{ overflowX: 'auto' }}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Data</TableCell>
              <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Motivo</TableCell>
              <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Tipo</TableCell>
              <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                Valor
              </TableCell>
              <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Status</TableCell>
              <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Protocolo</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {refunds.map((refund) => {
              const isPending = PENDING_STATUSES.has(refund.status)
              const isFailed = FAILED_STATUSES.has(refund.status)
              return (
                <TableRow key={refund.uuid}>
                  <TableCell>{refund.created_at ? formatDateBR(refund.created_at) : '—'}</TableCell>
                  <TableCell>{refund.reason ?? '—'}</TableCell>
                  <TableCell>{REFUND_TYPE_LABELS[refund.type] ?? refund.type}</TableCell>
                  <TableCell align="right">{formatCurrency(refund.amount)}</TableCell>
                  <TableCell>
                    {isPending || isFailed ? (
                      <Chip
                        size="small"
                        label={REFUND_STATUS_LABELS[refund.status] ?? refund.status}
                        sx={{
                          fontWeight: 600,
                          bgcolor: isFailed
                            ? 'color-mix(in srgb, var(--pt-danger) 16%, transparent)'
                            : 'color-mix(in srgb, var(--pt-warning) 16%, transparent)',
                          color: isFailed ? 'var(--pt-danger)' : 'var(--pt-warning)',
                        }}
                      />
                    ) : (
                      REFUND_STATUS_LABELS[refund.status] ?? refund.status
                    )}
                  </TableCell>
                  <TableCell sx={{ fontFamily: 'monospace', fontSize: 12.5 }}>{refund.protocol}</TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </TableContainer>

      {pagination && pagination.last_page > 1 && (
        <Box sx={{ display: 'flex', justifyContent: 'center' }}>
          <Pagination
            page={pagination.current_page}
            count={pagination.last_page}
            onChange={(_, value) => setPage(value)}
            color="primary"
            shape="rounded"
          />
        </Box>
      )}
    </Stack>
  )
}
