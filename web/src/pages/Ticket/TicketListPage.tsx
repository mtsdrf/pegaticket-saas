import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import { IconButton, Tooltip } from '@mui/material'
import { useCallback, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import { StatusChip, type StatusChipTone } from '../../components/crud/StatusChip'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as ticketService from '../../services/ticketService'
import { TICKET_STATUS_LABELS, type Ticket, type TicketStatus } from '../../types/ticket'
import { getApiErrorMessage } from '../../types/api'
import { formatDateTimeBR } from '../../utils/format'

const STATUS_TONE: Record<TicketStatus, StatusChipTone> = {
  pendente: 'neutral',
  ativo: 'info',
  utilizado: 'success',
  cancelado: 'danger',
  estornado: 'danger',
  bloqueado: 'danger',
  expirado: 'neutral',
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
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
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
        filterType: 'text',
        cellRenderer: (row) => row.event?.name ?? '—',
        exportValue: (row) => row.event?.name ?? '',
      },
      {
        field: 'ticket_type_name',
        headerName: 'Tipo de ingresso',
        filterType: 'text',
        cellRenderer: (row) => row.ticket_type?.name ?? '—',
        exportValue: (row) => row.ticket_type?.name ?? '',
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 140,
        filterType: 'text',
        cellRenderer: (row) => <StatusChip status={row.status} label={TICKET_STATUS_LABELS[row.status] ?? row.status} tone={STATUS_TONE[row.status]} />,
        exportValue: (row) => TICKET_STATUS_LABELS[row.status] ?? row.status,
      },
      {
        field: 'issued_at',
        headerName: 'Emitido em',
        width: 160,
        filterType: 'text',
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
      error={resendError}
      onRetry={() => setResendError(null)}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <ServerDataGrid
        columns={columns}
        fetchPage={fetchPage}
        rowIdField="uuid"
        exportFileName="ingressos"
        emptyState={{
          icon: <ConfirmationNumberOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
          title: 'Nenhum ingresso emitido ainda',
          description: 'Ingressos aparecem aqui automaticamente após a confirmação de uma venda.',
        }}
      />
    </CrudListPage>
  )
}
