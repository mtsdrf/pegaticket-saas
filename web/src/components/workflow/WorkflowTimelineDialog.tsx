import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import type { WorkflowTransitionLog } from '../../types/workflow'
import { formatDateTimeBR } from '../../utils/format'

interface WorkflowTimelineDialogProps {
  open: boolean
  title: string
  subjectLabel: string
  loader: () => Promise<WorkflowTransitionLog[]>
  stageLabel: (stage: string | null) => string
  onClose: () => void
}

function transitionLabel(item: WorkflowTransitionLog, stageLabel: (stage: string | null) => string): string {
  const from = stageLabel(item.from_stage)
  const to = stageLabel(item.to_stage)

  if (item.from_stage && item.to_stage) return `${from} -> ${to}`
  if (item.to_stage) return `Entrada em ${to}`
  if (item.from_stage) return `Saída de ${from}`
  return 'Movimentação registrada'
}

function transitionTypeLabel(type: string): string {
  if (type === 'cancel') return 'Cancelamento'
  if (type === 'move') return 'Movimentação'
  if (type === 'create') return 'Criação'
  if (type === 'complete') return 'Conclusão'
  return type
}

export function WorkflowTimelineDialog({
  open,
  title,
  subjectLabel,
  loader,
  stageLabel,
  onClose,
}: WorkflowTimelineDialogProps) {
  const [items, setItems] = useState<WorkflowTransitionLog[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return

    let active = true
    setLoading(true)
    setError(null)

    loader()
      .then((result) => {
        if (!active) return
        setItems(result)
      })
      .catch(() => {
        if (!active) return
        setItems([])
        setError('Não foi possível carregar o histórico operacional agora.')
      })
      .finally(() => {
        if (!active) return
        setLoading(false)
      })

    return () => {
      active = false
    }
  }, [loader, open])

  return (
    <Dialog open={open} onClose={() => !loading && onClose()} fullWidth maxWidth="sm">
      <DialogTitle>{title}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
            Histórico de movimentações de {subjectLabel}, com usuário, horário e motivo quando informado.
          </Typography>

          {loading ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
              <CircularProgress size={28} />
            </Box>
          ) : null}

          {!loading && error ? (
            <Alert severity="warning" variant="outlined">
              {error}
            </Alert>
          ) : null}

          {!loading && !error && items.length === 0 ? (
            <Alert severity="info" variant="outlined">
              Ainda não há movimentações registradas para este item.
            </Alert>
          ) : null}

          {!loading && !error ? (
            <Stack spacing={1.2}>
              {items.map((item) => (
                <Box
                  key={item.uuid}
                  sx={{
                    p: 1.5,
                    borderRadius: '16px',
                    border: '1px solid var(--mk-border)',
                    bgcolor: 'color-mix(in srgb, var(--mk-surface) 96%, white)',
                  }}
                >
                  <Stack spacing={0.85}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
                      <Stack direction="row" spacing={0.8} sx={{ alignItems: 'center', minWidth: 0 }}>
                        <HistoryOutlinedIcon sx={{ fontSize: 18, color: 'var(--mk-primary)' }} />
                        <Typography sx={{ fontSize: 13.5, fontWeight: 800 }}>
                          {transitionLabel(item, stageLabel)}
                        </Typography>
                      </Stack>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', flexShrink: 0 }}>
                        {item.moved_at ? formatDateTimeBR(item.moved_at) : 'Horário não informado'}
                      </Typography>
                    </Stack>

                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.75 }}>
                      <Stack direction="row" spacing={0.6} sx={{ alignItems: 'center' }}>
                        <PersonOutlineOutlinedIcon sx={{ fontSize: 16, color: 'var(--mk-muted)' }} />
                        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                          {item.user?.name?.trim() || 'Sistema'}
                        </Typography>
                      </Stack>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                        {transitionTypeLabel(item.transition_type)}
                      </Typography>
                    </Stack>

                    {item.reason ? (
                      <Box
                        sx={{
                          px: 1.1,
                          py: 0.9,
                          borderRadius: '12px',
                          bgcolor: 'color-mix(in srgb, var(--mk-warning) 10%, var(--mk-surface))',
                          border: '1px solid color-mix(in srgb, var(--mk-warning) 18%, transparent)',
                        }}
                      >
                        <Typography sx={{ fontSize: 12, fontWeight: 700, color: 'var(--mk-warning)' }}>Motivo informado</Typography>
                        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-text)' }}>{item.reason}</Typography>
                      </Box>
                    ) : null}
                  </Stack>
                </Box>
              ))}
            </Stack>
          ) : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={loading}>
          Fechar
        </Button>
      </DialogActions>
    </Dialog>
  )
}
