import {
  Alert,
  Box,
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
import { SubscriptionStatusBadge } from './SubscriptionStatusBadge'
import * as subscriptionService from '../../services/subscriptionService'
import { getApiErrorMessage } from '../../types/api'
import type { PaginationMeta } from '../../types/pagination'
import type { SubscriptionHistoryItem } from '../../types/subscription'
import { formatDateBR } from '../../utils/format'
import { BILLING_PERIOD_LABELS, type BillingPeriod } from '../../utils/subscriptionPricing'

const PER_PAGE = 10

function billingPeriodLabel(period: string): string {
  return BILLING_PERIOD_LABELS[period as BillingPeriod] ?? period
}

function formatDateTime(value: string | null): string {
  return value ? formatDateBR(value) : '—'
}

/**
 * Histórico completo de assinaturas do tenant ao longo do tempo
 * (`GET /subscription/history`, não só a atual) — mesmo padrão de
 * paginação de `SubscriptionInvoiceHistory`.
 */
export function SubscriptionHistoryList() {
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<SubscriptionHistoryItem[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setIsLoading(true)
    setError(null)

    subscriptionService
      .getSubscriptionHistory(page, PER_PAGE)
      .then((result) => {
        if (cancelled) return
        setItems(result.items)
        setPagination(result.pagination)
      })
      .catch((loadError: unknown) => {
        if (cancelled) return
        setError(getApiErrorMessage(loadError, 'Não foi possível carregar o histórico de assinaturas agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [page])

  if (isLoading && items.length === 0) {
    return (
      <Stack spacing={1}>
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} variant="rounded" height={40} />
        ))}
      </Stack>
    )
  }

  if (error && items.length === 0) {
    return <Alert severity="error">{error}</Alert>
  }

  if (items.length === 0) {
    return (
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', py: 1 }}>
        Nenhuma assinatura registrada até o momento.
      </Typography>
    )
  }

  return (
    <Stack spacing={1.5}>
      <TableContainer sx={{ overflowX: 'auto' }}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Plano</TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Ciclo</TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Situação</TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Período</TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Contratada em</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {items.map((item) => (
              <TableRow key={item.uuid}>
                <TableCell>{item.plan?.name ?? '—'}</TableCell>
                <TableCell>{billingPeriodLabel(item.billing_period)}</TableCell>
                <TableCell>
                  <SubscriptionStatusBadge status={item.status} />
                </TableCell>
                <TableCell>
                  {formatDateTime(item.current_period_start)} até {formatDateTime(item.current_period_end)}
                </TableCell>
                <TableCell>{formatDateTime(item.created_at)}</TableCell>
              </TableRow>
            ))}
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
