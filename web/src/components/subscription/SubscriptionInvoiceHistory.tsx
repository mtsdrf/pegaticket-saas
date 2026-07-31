import {
  Alert,
  Box,
  Button,
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
import { SubscriptionInvoicePixDialog } from './SubscriptionInvoicePixDialog'
import * as subscriptionService from '../../services/subscriptionService'
import { getApiErrorMessage } from '../../types/api'
import type { PaginationMeta } from '../../types/pagination'
import type { SubscriptionInvoice, SubscriptionInvoicePayment } from '../../types/subscription'
import { formatCurrency, formatDateBR } from '../../utils/format'

const PER_PAGE = 10

const INVOICE_STATUS_LABELS: Record<string, string> = {
  paid: 'Paga',
  pending: 'Pendente',
  open: 'Em aberto',
  overdue: 'Vencida',
  canceled: 'Cancelada',
  refunded: 'Estornada',
  failed: 'Falhou',
  divergent: 'Valor não confirmado',
}

/** Status que merecem destaque visual de alerta na lista (cor + texto explicativo abaixo da tabela). */
const ATTENTION_STATUSES = new Set(['divergent', 'failed', 'overdue'])
const PIX_CHARGEABLE_STATUSES = new Set(['open', 'overdue', 'failed'])

/**
 * Histórico paginado de faturas (`GET /subscription/invoices`), usado pela
 * tela única de assinatura (`SubscriptionPage`, dono e demais perfis com
 * `subscription:read`) — mesmo padrão de paginação de `OverdueTab`
 * (`crudService.listPaginated` + `PaginationMeta`).
 */
export function SubscriptionInvoiceHistory() {
  const [page, setPage] = useState(1)
  const [invoices, setInvoices] = useState<SubscriptionInvoice[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pixDialogOpen, setPixDialogOpen] = useState(false)
  const [pixInvoice, setPixInvoice] = useState<SubscriptionInvoice | null>(null)
  const [pixPayment, setPixPayment] = useState<SubscriptionInvoicePayment | null>(null)
  const [pixLoading, setPixLoading] = useState(false)
  const [pixError, setPixError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setIsLoading(true)
    setError(null)

    subscriptionService
      .getInvoices(page, PER_PAGE)
      .then((result) => {
        if (cancelled) return
        setInvoices(result.items)
        setPagination(result.pagination)
      })
      .catch((loadError: unknown) => {
        if (cancelled) return
        setError(getApiErrorMessage(loadError, 'Não foi possível carregar o histórico de faturas agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [page])

  if (isLoading && invoices.length === 0) {
    return (
      <Stack spacing={1}>
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} variant="rounded" height={40} />
        ))}
      </Stack>
    )
  }

  if (error && invoices.length === 0) {
    return <Alert severity="error">{error}</Alert>
  }

  if (invoices.length === 0) {
    return (
      <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', py: 1 }}>
        Nenhuma fatura emitida até o momento.
      </Typography>
    )
  }

  const hasDivergentInvoice = invoices.some((invoice) => invoice.status === 'divergent')

  async function handleOpenPix(invoice: SubscriptionInvoice) {
    setPixInvoice(invoice)
    setPixPayment(null)
    setPixError(null)
    setPixDialogOpen(true)
    setPixLoading(true)

    try {
      const payment = await subscriptionService.createInvoicePixCharge(invoice.uuid)
      setPixPayment(payment)
    } catch (loadError) {
      setPixError(getApiErrorMessage(loadError, 'Não foi possível gerar a cobrança Pix desta fatura agora.'))
    } finally {
      setPixLoading(false)
    }
  }

  return (
    <Stack spacing={1.5}>
      {hasDivergentInvoice && (
        <Alert severity="warning" variant="outlined">
          Uma ou mais cobranças abaixo têm valor não confirmado. Isso não significa que algo deu errado com a sua
          empresa — nossa equipe vai entrar em contato para confirmar o pagamento.
        </Alert>
      )}

      <TableContainer sx={{ overflowX: 'auto' }}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Competência</TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Vencimento</TableCell>
              <TableCell align="right" sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>
                Valor
              </TableCell>
              <TableCell sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Status</TableCell>
              <TableCell align="right" sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>Ação</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {invoices.map((invoice) => {
              const needsAttention = ATTENTION_STATUSES.has(invoice.status)
              const canPayWithPix = PIX_CHARGEABLE_STATUSES.has(invoice.status)
              return (
                <TableRow key={invoice.uuid}>
                  <TableCell>{invoice.competence_period}</TableCell>
                  <TableCell>{invoice.due_date ? formatDateBR(invoice.due_date) : '—'}</TableCell>
                  <TableCell align="right">{formatCurrency(invoice.amount_net)}</TableCell>
                  <TableCell>
                    {needsAttention ? (
                      <Chip
                        size="small"
                        label={INVOICE_STATUS_LABELS[invoice.status] ?? invoice.status}
                        sx={{
                          fontWeight: 600,
                          bgcolor: 'color-mix(in srgb, var(--mk-warning) 16%, transparent)',
                          color: 'var(--mk-warning)',
                        }}
                      />
                    ) : (
                      INVOICE_STATUS_LABELS[invoice.status] ?? invoice.status
                    )}
                  </TableCell>
                  <TableCell align="right">
                    {canPayWithPix ? (
                      <Button size="small" variant="outlined" onClick={() => void handleOpenPix(invoice)}>
                        Pagar com Pix
                      </Button>
                    ) : (
                      '—'
                    )}
                  </TableCell>
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

      <SubscriptionInvoicePixDialog
        invoice={pixInvoice}
        open={pixDialogOpen}
        payment={pixPayment}
        error={pixError}
        loading={pixLoading}
        onClose={() => {
          setPixDialogOpen(false)
          setPixInvoice(null)
          setPixPayment(null)
          setPixError(null)
          setPixLoading(false)
        }}
      />
    </Stack>
  )
}
