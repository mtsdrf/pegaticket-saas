import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import PendingActionsOutlinedIcon from '@mui/icons-material/PendingActionsOutlined'
import SellOutlinedIcon from '@mui/icons-material/SellOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import { Box, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { MetricCard } from './MetricCard'
import * as operationSnapshotService from '../../services/operationSnapshotService'
import type { OperationSnapshot } from '../../types/operationSnapshot'
import { formatCurrency } from '../../utils/format'

const POLL_INTERVAL_MS = 30_000

/**
 * Dashboard operacional em tempo quase real (roadmap Fase 2). Faz seu
 * próprio polling (30s), independente do relatório por período do resto do
 * Dashboard — pensado pra refletir caixa/fila/check-in de agora, não uma
 * janela de datas escolhida pelo usuário.
 */
export function OperationSnapshotCard() {
  const [snapshot, setSnapshot] = useState<OperationSnapshot | null>(null)

  useEffect(() => {
    let cancelled = false

    function load() {
      operationSnapshotService
        .getOperationSnapshot()
        .then((data) => {
          if (!cancelled) setSnapshot(data)
        })
        .catch(() => undefined)
    }

    load()
    const interval = window.setInterval(load, POLL_INTERVAL_MS)

    return () => {
      cancelled = true
      window.clearInterval(interval)
    }
  }, [])

  if (!snapshot) return null

  const cashLabel = snapshot.cash_session
    ? snapshot.cash_session.expected_cash_amount !== null
      ? formatCurrency(snapshot.cash_session.expected_cash_amount)
      : formatCurrency(snapshot.cash_session.opening_amount)
    : 'Fechado'

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
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', lg: 'repeat(4,1fr)' },
          gap: 1.5,
        }}
      >
        <MetricCard
          icon={PointOfSaleOutlinedIcon}
          label="Caixa"
          value={cashLabel}
          tone={snapshot.cash_session ? 'primary' : 'warning'}
          index={0}
        />
        <MetricCard
          icon={PendingActionsOutlinedIcon}
          label="Aguardando aprovação"
          value={String(snapshot.sales_pending_approval_count)}
          tone={snapshot.sales_pending_approval_count > 0 ? 'warning' : 'primary'}
          index={1}
        />
        <MetricCard
          icon={SellOutlinedIcon}
          label="Vendido hoje"
          value={formatCurrency(snapshot.sales_today.total_amount)}
          caption={`${snapshot.sales_today.count} venda(s)`}
          tone="accent"
          index={2}
        />
        <MetricCard
          icon={QrCodeScannerOutlinedIcon}
          label="Check-ins hoje"
          value={String(snapshot.checkins_today.total)}
          caption={snapshot.checkins_today.warning > 0 ? `${snapshot.checkins_today.warning} com alerta` : undefined}
          tone={snapshot.checkins_today.warning > 0 ? 'warning' : 'accent'}
          index={3}
        />
        <MetricCard
          icon={ErrorOutlineOutlinedIcon}
          label="Erro de checkout"
          value={`${snapshot.checkout.error_rate_percent}%`}
          caption={`${snapshot.checkout.completed}/${snapshot.checkout.started} concluídos (${snapshot.checkout.window_hours}h)`}
          tone={snapshot.checkout.error_rate_percent > 30 ? 'warning' : 'primary'}
          index={4}
        />
        {snapshot.virtual_queue.waiting + snapshot.virtual_queue.admitted > 0 && (
          <MetricCard
            icon={GroupsOutlinedIcon}
            label="Fila virtual"
            value={String(snapshot.virtual_queue.waiting)}
            caption={`${snapshot.virtual_queue.admitted} admitido(s) agora`}
            tone="accent"
            index={5}
          />
        )}
      </Box>
    </Box>
  )
}
