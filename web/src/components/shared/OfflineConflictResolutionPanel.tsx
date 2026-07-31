import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import RefreshIcon from '@mui/icons-material/Refresh'
import SyncIcon from '@mui/icons-material/Sync'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import {
  Alert,
  Box,
  Button,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { BalcaoOfflineLocalComanda } from '../../types/balcao'

interface OfflineConflictResolutionPanelProps {
  conflicts: BalcaoOfflineLocalComanda[]
  isBusy?: boolean
  onRefreshSnapshot: () => void | Promise<void>
  onSync: () => void | Promise<void>
  onReviewConflict: (localComandaUuid: string) => void
  onDiscardConflict: (localComandaUuid: string) => void | Promise<void>
  onDiscardAllConflicts?: () => void | Promise<void>
}

export function OfflineConflictResolutionPanel({
  conflicts,
  isBusy = false,
  onRefreshSnapshot,
  onSync,
  onReviewConflict,
  onDiscardConflict,
  onDiscardAllConflicts,
}: OfflineConflictResolutionPanelProps) {
  if (conflicts.length === 0) return null

  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}>
      <Stack spacing={1.5}>
        <Box>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
            Painel de reconciliação offline
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            O sistema identificou diferenças entre este dispositivo e o estado atual do servidor. Revise cada caso antes de continuar operando.
          </Typography>
        </Box>

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ flexWrap: 'wrap' }}>
          <Button variant="outlined" startIcon={<RefreshIcon />} onClick={() => void onRefreshSnapshot()} disabled={isBusy}>
            Atualizar base
          </Button>
          <Button variant="outlined" startIcon={<SyncIcon />} onClick={() => void onSync()} disabled={isBusy}>
            Tentar sincronizar novamente
          </Button>
          {onDiscardAllConflicts ? (
            <Button
              variant="text"
              color="error"
              startIcon={<DeleteOutlineIcon />}
              onClick={() => void onDiscardAllConflicts()}
              disabled={isBusy}
            >
              Descartar conflitos locais
            </Button>
          ) : null}
        </Stack>

        <Stack spacing={1}>
          {conflicts.map((conflict) => (
            <Alert
              key={conflict.local_comanda_uuid}
              severity="warning"
              variant="outlined"
              action={(
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                  <Button
                    color="inherit"
                    size="small"
                    startIcon={<VisibilityOutlinedIcon />}
                    onClick={() => onReviewConflict(conflict.local_comanda_uuid)}
                  >
                    Revisar
                  </Button>
                  <Button
                    color="inherit"
                    size="small"
                    startIcon={<DeleteOutlineIcon />}
                    onClick={() => void onDiscardConflict(conflict.local_comanda_uuid)}
                  >
                    Descartar local
                  </Button>
                </Stack>
              )}
              sx={{
                alignItems: { xs: 'flex-start', sm: 'center' },
                '& .MuiAlert-action': {
                  pt: { xs: 1.25, sm: 0.5 },
                  pl: 1,
                },
              }}
            >
              <Typography variant="body2" sx={{ fontWeight: 700 }}>
                {conflict.table?.label ?? conflict.label ?? 'Comanda local'}
              </Typography>
              <Typography variant="body2">
                {conflict.conflict_reason ?? 'Conflito de sincronização identificado. Atualize a base e revise manualmente.'}
              </Typography>
            </Alert>
          ))}
        </Stack>
      </Stack>
    </Paper>
  )
}
