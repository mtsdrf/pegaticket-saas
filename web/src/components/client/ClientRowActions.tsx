import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import { IconButton, Stack, Tooltip } from '@mui/material'
import type { Client } from '../../types/client'

interface ClientRowActionsProps {
  client: Client
  onEdit: (client: Client) => void
  onDeleteRequest: (client: Client) => void
  onViewOrders: (client: Client) => void
  canEdit?: boolean
  canDelete?: boolean
}

export function ClientRowActions({
  client,
  onEdit,
  onDeleteRequest,
  onViewOrders,
  canEdit = true,
  canDelete = true,
}: ClientRowActionsProps) {
  return (
    <Stack direction="row" spacing={0.5} sx={{ height: '100%', alignItems: 'center' }}>
      <Tooltip title="Ver pedidos do cliente" arrow>
        <IconButton
          size="small"
          aria-label={`Ver pedidos de ${client.name}`}
          onClick={() => onViewOrders(client)}
          sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
        >
          <ReceiptLongOutlinedIcon fontSize="small" />
        </IconButton>
      </Tooltip>
      {canEdit ? (
        <Tooltip title="Editar cliente" arrow>
          <IconButton
            size="small"
            aria-label={`Editar ${client.name}`}
            onClick={() => onEdit(client)}
            sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
          >
            <EditOutlinedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      ) : null}
      {canDelete ? (
        <Tooltip title="Excluir cliente" arrow>
          <IconButton
            size="small"
            aria-label={`Excluir ${client.name}`}
            onClick={() => onDeleteRequest(client)}
            sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
          >
            <DeleteOutlineIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      ) : null}
    </Stack>
  )
}
