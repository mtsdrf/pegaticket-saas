import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import { Box, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { ReportAlert } from '../../types/report'

interface AlertsCardProps {
  alerts: ReportAlert[] | null
  isLoading: boolean
}

const CARD_SX = {
  p: { xs: 2, sm: 3 },
  ...ELEVATED_SURFACE_SX,
} as const

/**
 * Alertas básicos do Home (roadmap Fase A1) — pagamento, fila manual e
 * anomalias operacionais calculadas on-the-fly (sem model configurável). Não usa
 * PeriodFilter — é um card operacional "agora", mesmo espírito de
 * OperationSnapshotCard, dentro de uma tela que já tem filtro de período
 * ativo por padrão (regra transversal de filtro obrigatório da Fase A1).
 */
export function AlertsCard({ alerts, isLoading }: AlertsCardProps) {
  if (isLoading) {
    return (
      <Paper variant="outlined" className="pt-reveal" sx={CARD_SX}>
        <Skeleton variant="text" width={160} height={28} />
        <Skeleton variant="rounded" height={64} sx={{ mt: 1.5, borderRadius: 'var(--pt-radius-md)' }} />
      </Paper>
    )
  }

  if (!alerts || alerts.length === 0) {
    return (
      <Paper variant="outlined" className="pt-reveal" sx={CARD_SX}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
          <Box
            sx={{
              width: 40,
              height: 40,
              ...SOFT_PANEL_SX,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
              background: 'color-mix(in srgb, var(--pt-success) 14%, transparent)',
              color: 'var(--pt-success)',
            }}
          >
            <TaskAltOutlinedIcon fontSize="small" />
          </Box>
          <Box>
            <Typography sx={{ fontWeight: 600, fontSize: 15, color: 'var(--pt-text)' }}>
              Nenhum alerta no momento
            </Typography>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
              Pagamentos e fila operacional estão dentro do esperado.
            </Typography>
          </Box>
        </Box>
      </Paper>
    )
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={CARD_SX}>
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 1.5 }}>
        Alertas ({alerts.length})
      </Typography>

      <Stack spacing={1}>
        {alerts.map((alert, index) => {
          const isCritical = alert.severity === 'critical'
          const color = isCritical ? 'var(--pt-danger)' : 'var(--pt-warning)'
          const Icon = isCritical ? ErrorOutlineOutlinedIcon : WarningAmberOutlinedIcon

          return (
            <Box
              key={`${alert.type}-${index}`}
              sx={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 1.25,
                p: 1.25,
                ...SOFT_PANEL_SX,
                background: `color-mix(in srgb, ${color} 8%, var(--pt-surface-soft))`,
                borderColor: `color-mix(in srgb, ${color} 24%, var(--pt-border))`,
              }}
            >
              <Icon sx={{ fontSize: 20, color, flexShrink: 0, mt: 0.25 }} />
              <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontWeight: 600, fontSize: 13.5, color: 'var(--pt-text)' }}>
                  {alert.title}
                </Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>{alert.message}</Typography>
              </Box>
            </Box>
          )
        })}
      </Stack>
    </Paper>
  )
}
