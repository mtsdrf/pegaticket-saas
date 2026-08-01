import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import { Box, Chip, IconButton, MenuItem, TextField, Tooltip } from '@mui/material'
import { useCallback, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as ticketService from '../../services/ticketService'
import { TICKET_STATUS_LABELS, TICKET_STATUS_OPTIONS, type Ticket, type TicketStatus } from '../../types/ticket'
import { getApiErrorMessage } from '../../types/api'
import { formatDateTimeBR } from '../../utils/format'

const STATUS_TONE: Record<TicketStatus, { bg: string; fg: string }> = {
  pendente: { bg: 'color-mix(in srgb, var(--pt-muted) 14%, transparent)', fg: 'var(--pt-muted)' },
  ativo: { bg: 'color-mix(in srgb, var(--pt-info) 14%, transparent)', fg: 'var(--pt-info)' },
  utilizado: { bg: 'color-mix(in srgb, var(--pt-success) 14%, transparent)', fg: 'var(--pt-success)' },
  cancelado: { bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)', fg: 'var(--pt-danger)' },
  estornado: { bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)', fg: 'var(--pt-danger)' },
  bloqueado: { bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)', fg: 'var(--pt-danger)' },
  expirado: { bg: 'color-mix(in srgb, var(--pt-muted) 14%, transparent)', fg: 'var(--pt-muted)' },
}

/**
 * Listagem de ingressos (staff) — filtro por evento (via busca livre,
 * `TicketFilters.search` já cobre nome/documento/código no backend) e
 * status. Reenvio (`POST /tickets/{ticket}/resend`) só registra o evento
 * de auditoria hoje — sem e-mail real ainda — então a ação apenas confirma
 * o registro, sem prometer envio de e-mail ao usuário.
 */
export function TicketListPage() {
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()

  const [status, setStatus] = useState<TicketStatus | ''>('')
  const [resendingUuid, setResendingUuid] = useState<string | null>(null)
  const [resendError, setResendError] = useState<string | null>(null)
  const [resendedUuids, setResendedUuids] = useState<Set<string>>(new Set())

  const handleResend = useCallback(async (ticket: Ticket) => {
    setResendError(null)
    setResendingUuid(ticket.uuid)
    try {
      await ticketService.resendTicket(ticket.uuid)
      setResendedUuids((current) => new Set(current).add(ticket.uuid))
    } catch (err) {
      setResendError(getApiErrorMessage(err, 'Não foi possível registrar o reenvio agora.'))
    } finally {
      setResendingUuid(null)
    }
  }, [])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Ticket>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await ticketService.listTickets({
        ...filters,
        status: status || undefined,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, status],
  )

  const columns = useMemo<ServerGridColumn<Ticket>[]>(
    () => [
      { field: 'code', headerName: 'Código', width: 120, filterType: 'text' },
      {
        field: 'attendee_name',
        headerName: 'Participante',
        filterType: 'text',
        cellRenderer: (row) => row.attendee_name ?? '—',
        exportValue: (row) => row.attendee_name ?? '',
      },
      {
        field: 'event_name',
        headerName: 'Evento',
        filterType: 'none',
        cellRenderer: (row) => row.event?.name ?? '—',
        exportValue: (row) => row.event?.name ?? '',
      },
      {
        field: 'ticket_type_name',
        headerName: 'Tipo de ingresso',
        filterType: 'none',
        cellRenderer: (row) => row.ticket_type?.name ?? '—',
        exportValue: (row) => row.ticket_type?.name ?? '',
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 140,
        filterType: 'none',
        cellRenderer: (row) => {
          const tone = STATUS_TONE[row.status]
          return (
            <Chip
              size="small"
              label={TICKET_STATUS_LABELS[row.status] ?? row.status}
              sx={{ fontWeight: 600, bgcolor: tone.bg, color: tone.fg }}
            />
          )
        },
        exportValue: (row) => TICKET_STATUS_LABELS[row.status] ?? row.status,
      },
      {
        field: 'issued_at',
        headerName: 'Emitido em',
        width: 160,
        filterType: 'none',
        cellRenderer: (row) => (row.issued_at ? formatDateTimeBR(row.issued_at) : '—'),
        exportValue: (row) => (row.issued_at ? formatDateTimeBR(row.issued_at) : ''),
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 100,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) =>
          can(ACCESS.ticketsResend) ? (
            <Tooltip title={resendedUuids.has(row.uuid) ? 'Reenvio registrado' : 'Registrar reenvio'} arrow>
              <span>
                <IconButton
                  size="small"
                  aria-label={`Reenviar ingresso ${row.code}`}
                  disabled={resendingUuid === row.uuid}
                  onClick={() => void handleResend(row)}
                  sx={{
                    minWidth: 44,
                    minHeight: 44,
                    color: resendedUuids.has(row.uuid) ? 'var(--pt-success)' : 'var(--pt-muted)',
                    '&:hover': { color: 'var(--pt-primary)' },
                  }}
                >
                  <ReplayOutlinedIcon fontSize="small" />
                </IconButton>
              </span>
            </Tooltip>
          ) : null,
      },
    ],
    [can, handleResend, resendedUuids, resendingUuid],
  )

  return (
    <CrudListPage
      title="Ingressos"
      subtitle="Consulte os ingressos emitidos e registre reenvios quando necessário."
      canCreate={false}
      toolbar={
        <TextField
          select
          label="Status"
          size="small"
          value={status}
          onChange={(event) => setStatus(event.target.value as TicketStatus | '')}
          sx={{ minWidth: 220 }}
        >
          <MenuItem value="">Todos os status</MenuItem>
          {TICKET_STATUS_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>
      }
      error={resendError}
      onRetry={() => setResendError(null)}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 860 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            exportFileName="ingressos"
            emptyState={{
              icon: <ConfirmationNumberOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhum ingresso emitido ainda',
              description: 'Ingressos aparecem aqui automaticamente após a confirmação de um pedido.',
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )
}
