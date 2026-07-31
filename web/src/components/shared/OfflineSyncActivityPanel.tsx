import AccessTimeIcon from '@mui/icons-material/AccessTime'
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineOutlined'
import SyncIcon from '@mui/icons-material/Sync'
import TaskAltIcon from '@mui/icons-material/TaskAlt'
import WarningAmberIcon from '@mui/icons-material/WarningAmber'
import {
  Box,
  Chip,
  Divider,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

export type OfflineActivityStatus = 'pending' | 'syncing' | 'synced' | 'error' | 'conflict'

export interface OfflineActivityEntry {
  id: string
  title: string
  subtitle?: string | null
  description?: string | null
  status: OfflineActivityStatus
  meta?: string | null
}

interface OfflineSyncActivityPanelProps {
  title: string
  description: string
  entries: OfflineActivityEntry[]
  emptyMessage: string
}

const STATUS_META: Record<OfflineActivityStatus, { label: string; color: 'default' | 'warning' | 'success' | 'error' | 'info'; icon: typeof AccessTimeIcon }> = {
  pending: { label: 'Pendente', color: 'warning', icon: AccessTimeIcon },
  syncing: { label: 'Sincronizando', color: 'info', icon: SyncIcon },
  synced: { label: 'Sincronizado', color: 'success', icon: TaskAltIcon },
  error: { label: 'Falhou', color: 'error', icon: ErrorOutlineIcon },
  conflict: { label: 'Conflito', color: 'error', icon: WarningAmberIcon },
}

export function OfflineSyncActivityPanel({
  title,
  description,
  entries,
  emptyMessage,
}: OfflineSyncActivityPanelProps) {
  const pendingCount = entries.filter((entry) => entry.status === 'pending').length
  const syncingCount = entries.filter((entry) => entry.status === 'syncing').length
  const syncedCount = entries.filter((entry) => entry.status === 'synced').length
  const errorCount = entries.filter((entry) => entry.status === 'error').length
  const conflictCount = entries.filter((entry) => entry.status === 'conflict').length

  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}>
      <Stack spacing={1.5}>
        <Box>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
            {title}
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {description}
          </Typography>
        </Box>

        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
          <Chip size="small" variant="outlined" color="warning" label={`${pendingCount} pendente${pendingCount === 1 ? '' : 's'}`} />
          <Chip size="small" variant="outlined" color="info" label={`${syncingCount} sincronizando`} />
          <Chip size="small" variant="outlined" color="success" label={`${syncedCount} sincronizado${syncedCount === 1 ? '' : 's'}`} />
          <Chip size="small" variant="outlined" color="error" label={`${errorCount} falha${errorCount === 1 ? '' : 's'}`} />
          {conflictCount > 0 ? (
            <Chip size="small" variant="outlined" color="error" label={`${conflictCount} conflito${conflictCount === 1 ? '' : 's'}`} />
          ) : null}
        </Stack>

        {entries.length === 0 ? (
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {emptyMessage}
          </Typography>
        ) : (
          <Stack divider={<Divider />}>
            {entries.map((entry) => {
              const meta = STATUS_META[entry.status]
              const Icon = meta.icon
              return (
                <Stack
                  key={entry.id}
                  direction={{ xs: 'column', sm: 'row' }}
                  spacing={1.25}
                  sx={{ py: 1, justifyContent: 'space-between', alignItems: { xs: 'flex-start', sm: 'center' } }}
                >
                  <Box sx={{ minWidth: 0 }}>
                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                      {entry.title}
                    </Typography>
                    {entry.subtitle ? (
                      <Typography variant="caption" sx={{ display: 'block', color: 'var(--mk-muted)' }}>
                        {entry.subtitle}
                      </Typography>
                    ) : null}
                    {entry.description ? (
                      <Typography variant="body2" sx={{ mt: 0.5 }}>
                        {entry.description}
                      </Typography>
                    ) : null}
                    {entry.meta ? (
                      <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: 'var(--mk-muted)' }}>
                        {entry.meta}
                      </Typography>
                    ) : null}
                  </Box>

                  <Chip
                    size="small"
                    color={meta.color}
                    icon={<Icon />}
                    label={meta.label}
                    sx={{ flexShrink: 0 }}
                  />
                </Stack>
              )
            })}
          </Stack>
        )}
      </Stack>
    </Paper>
  )
}
