import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import PendingActionsOutlinedIcon from '@mui/icons-material/PendingActionsOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import { Box, Typography } from '@mui/material'
import { MetricCard } from './MetricCard'
import type { OperationSnapshot } from '../../types/operationSnapshot'
import { formatCurrency } from '../../utils/format'
interface OperationSnapshotCardProps {
  snapshot: OperationSnapshot | null
  hideValues?: boolean
}

/**
 * Dashboard operacional em tempo quase real (roadmap Fase 2), já com o
 * snapshot carregado pela página para poder reutilizar os mesmos dados em
 * cards contextuais da home sem polling duplicado.
 */
export function OperationSnapshotCard({ snapshot, hideValues = false }: OperationSnapshotCardProps) {
  if (!snapshot) return null

  const cashLabel = snapshot.cash_session
    ? snapshot.cash_session.expected_cash_amount !== null
      ? formatCurrency(snapshot.cash_session.expected_cash_amount)
      : formatCurrency(snapshot.cash_session.opening_amount)
    : 'Fechado'

  const metricCards = [
    <MetricCard
      key="cash"
      icon={PointOfSaleOutlinedIcon}
      label="Caixa"
      value={cashLabel}
      tone={snapshot.cash_session ? 'primary' : 'warning'}
      index={0}
      hideValue={hideValues}
    />,
    <MetricCard
      key="pending-approval"
      icon={PendingActionsOutlinedIcon}
      label="Aguardando aprovação"
      value={String(snapshot.sales_pending_approval_count)}
      tone={snapshot.sales_pending_approval_count > 0 ? 'warning' : 'primary'}
      index={1}
      hideValue={hideValues}
    />,
    <MetricCard
      key="checkins-today"
      icon={QrCodeScannerOutlinedIcon}
      label="Check-ins hoje"
      value={String(snapshot.checkins_today.total)}
      caption={snapshot.checkins_today.warning > 0 ? `${snapshot.checkins_today.warning} com alerta` : undefined}
      tone={snapshot.checkins_today.warning > 0 ? 'warning' : 'accent'}
      index={2}
      hideValue={hideValues}
    />,
    <MetricCard
      key="checkout-error"
      icon={ErrorOutlineOutlinedIcon}
      label="Erro de checkout"
      value={`${snapshot.checkout.error_rate_percent}%`}
      caption={`${snapshot.checkout.completed}/${snapshot.checkout.started} concluídos (${snapshot.checkout.window_hours}h)`}
      tone={snapshot.checkout.error_rate_percent > 30 ? 'warning' : 'primary'}
      index={3}
      hideValue={hideValues}
    />,
  ]

  if (snapshot.virtual_queue.waiting + snapshot.virtual_queue.admitted > 0) {
    metricCards.push(
      <MetricCard
        key="virtual-queue"
        icon={GroupsOutlinedIcon}
        label="Fila virtual"
        value={String(snapshot.virtual_queue.waiting)}
        caption={`${snapshot.virtual_queue.admitted} admitido(s) agora`}
        tone="accent"
        index={4}
        hideValue={hideValues}
      />,
    )
  }

  return (
    <Box>
      <Typography
        sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
      >
        Operação agora
      </Typography>
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: {
            xs: 'repeat(1,1fr)',
            sm: 'repeat(2,1fr)',
            lg: `repeat(${metricCards.length}, minmax(0, 1fr))`,
          },
          gap: 1.5,
        }}
      >
        {metricCards}
      </Box>
    </Box>
  )
}
