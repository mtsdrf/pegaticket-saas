import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  InputAdornment,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { OfflineSyncActivityPanel, type OfflineActivityEntry } from '../../components/shared/OfflineSyncActivityPanel'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { usePdvOffline } from '../../hooks/usePdvOffline'
import * as pdvService from '../../services/pdvService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { CashMovementType, CashSession } from '../../types/pdv'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { usePdvSession } from './PdvSessionContext'

const MOVEMENT_LABELS: Record<CashMovementType, string> = {
  supply: 'Suprimento (entrada)',
  withdrawal: 'Sangria (saída)',
}

export function PdvCloseSessionPage() {
  const navigate = useNavigate()
  const { activeTenantUuid } = useAuth()
  const { session, isOfflineSnapshot, reload } = usePdvSession()
  const { can } = useAccessControl()
  const canMovement = can(ACCESS.pdvMovement)
  const canClose = can(ACCESS.pdvClose)
  const actionsBlockedOffline = isOfflineSnapshot
  const {
    queuedSales,
    unsyncedCount,
    pendingQueueCount,
    queueErrorCount,
    isSyncing,
    syncQueuedSales,
  } = usePdvOffline(activeTenantUuid, session.uuid)
  const closeBlockedByPendingSync = unsyncedCount > 0
  const offlineActivityEntries: OfflineActivityEntry[] = [...queuedSales]
    .sort((a, b) => b.created_at.localeCompare(a.created_at))
    .slice(0, 6)
    .map((sale) => ({
      id: sale.local_sale_uuid,
      title: `Venda ${sale.receipt_order.codigo}`,
      subtitle: sale.client_name ? `Cliente: ${sale.client_name}` : 'Consumidor final',
      description: `${sale.receipt_order.items.length} item${sale.receipt_order.items.length === 1 ? '' : 's'} · ${formatCurrency(sale.receipt_order.total_amount)}`,
      status: sale.status,
      meta:
        sale.status === 'synced' && sale.synced_at
          ? `Sincronizada em ${formatDateTimeBR(sale.synced_at)}.`
          : sale.status === 'error' && sale.last_error
            ? sale.last_error
            : `Registrada em ${formatDateTimeBR(sale.created_at)}.`,
    }))

  // Movimento (sangria/suprimento)
  const [movementType, setMovementType] = useState<CashMovementType>('withdrawal')
  const [movementAmount, setMovementAmount] = useState('')
  const [movementReason, setMovementReason] = useState('')
  const [movementError, setMovementError] = useState<string | null>(null)
  const [movementSuccess, setMovementSuccess] = useState<string | null>(null)
  const [isSavingMovement, setSavingMovement] = useState(false)

  // Fechamento
  const [declaredAmount, setDeclaredAmount] = useState('')
  const [closeError, setCloseError] = useState<string | null>(null)
  const [isClosing, setClosing] = useState(false)
  const [closed, setClosed] = useState<CashSession | null>(null)

  async function handleMovement(event: FormEvent) {
    event.preventDefault()
    const amount = Number(movementAmount)
    if (!Number.isFinite(amount) || amount <= 0) {
      setMovementError('Informe um valor maior que zero.')
      return
    }
    setSavingMovement(true)
    setMovementError(null)
    setMovementSuccess(null)
    try {
      await pdvService.registerCashMovement(session.uuid, {
        type: movementType,
        amount,
        reason: movementReason.trim() || undefined,
      })
      setMovementAmount('')
      setMovementReason('')
      setMovementSuccess('Movimentação registrada.')
      await reload()
    } catch (error) {
      setMovementError(getApiErrorMessage(error, 'Não foi possível registrar a movimentação agora.'))
    } finally {
      setSavingMovement(false)
    }
  }

  async function handleClose(event: FormEvent) {
    event.preventDefault()
    if (closeBlockedByPendingSync) {
      setCloseError('Ainda existem vendas offline pendentes ou com falha. Sincronize tudo antes de fechar o caixa.')
      return
    }
    const amount = Number(declaredAmount)
    if (!Number.isFinite(amount) || amount < 0) {
      setCloseError('Informe o valor contado no caixa.')
      return
    }
    setClosing(true)
    setCloseError(null)
    try {
      const result = await pdvService.closeCashSession(session.uuid, { closing_amount_declared: amount })
      setClosed(result)
    } catch (error) {
      setCloseError(getApiErrorMessage(error, 'Não foi possível fechar o caixa agora.'))
    } finally {
      setClosing(false)
    }
  }

  // Tela de resumo pós-fechamento — o backend é quem calcula o esperado/diferença.
  if (closed) {
    const difference = closed.difference ?? 0
    return (
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 480, py: { xs: 2, sm: 6 } }}>
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 3, sm: 4 } }}>
          <Stack spacing={2.5}>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
              Caixa fechado
            </Typography>
            <Stack spacing={1.25}>
              <SummaryRow label="Valor esperado" value={formatCurrency(closed.closing_amount_expected ?? 0)} />
              <SummaryRow label="Valor contado" value={formatCurrency(closed.closing_amount_declared ?? 0)} />
              <Divider />
              <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                  Diferença
                </Typography>
                <Chip
                  color={difference === 0 ? 'success' : difference > 0 ? 'info' : 'error'}
                  label={`${difference > 0 ? '+' : ''}${formatCurrency(difference)}`}
                />
              </Stack>
            </Stack>
            <Button variant="contained" size="large" onClick={() => void reload()}>
              Voltar ao PDV
            </Button>
          </Stack>
        </Paper>
      </Box>
    )
  }

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 720 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 2 }}>
        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/pdv')}>
          Voltar à venda
        </Button>
      </Stack>

      <Stack spacing={2}>
        {closeBlockedByPendingSync ? (
          <Alert
            severity="warning"
            action={(
              <Button color="inherit" size="small" onClick={() => void syncQueuedSales()} disabled={isSyncing}>
                {isSyncing ? 'Sincronizando…' : 'Sincronizar agora'}
              </Button>
            )}
          >
            {queueErrorCount > 0
              ? `Existem ${unsyncedCount} venda${unsyncedCount === 1 ? '' : 's'} offline ainda não reconciliada${unsyncedCount === 1 ? '' : 's'}, incluindo ${queueErrorCount} com falha. O caixa só pode ser fechado depois da reconciliação completa.`
              : `Existem ${pendingQueueCount} venda${pendingQueueCount === 1 ? '' : 's'} offline aguardando envio ao servidor. O caixa só pode ser fechado depois da sincronização completa.`}
          </Alert>
        ) : null}

        {/* Resumo da sessão */}
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.5 }}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
            Caixa aberto{session.cash_register ? ` — ${session.cash_register.name}` : ''}
          </Typography>
          <Stack spacing={1}>
            <SummaryRow label="Troco inicial" value={formatCurrency(session.opening_amount)} />
            {session.opened_at ? <SummaryRow label="Aberto em" value={formatDateTimeBR(session.opened_at)} /> : null}
          </Stack>

          {session.movements && session.movements.length > 0 ? (
            <>
              <Divider sx={{ my: 1.5 }} />
              <Typography variant="caption" sx={{ color: 'var(--mk-muted)', fontWeight: 600 }}>
                Movimentações ({session.movements.length})
              </Typography>
              <Stack spacing={0.75} sx={{ mt: 1 }}>
                {session.movements.map((movement) => (
                  <Stack key={movement.uuid} direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2">
                      {MOVEMENT_LABELS[movement.type]}
                      {movement.reason ? <span style={{ color: 'var(--mk-muted)' }}> · {movement.reason}</span> : null}
                    </Typography>
                    <Typography
                      variant="body2"
                      sx={{ fontWeight: 600, color: movement.type === 'supply' ? 'var(--mk-success)' : 'var(--mk-danger)' }}
                    >
                      {movement.type === 'supply' ? '+' : '−'}
                      {formatCurrency(movement.amount)}
                    </Typography>
                  </Stack>
                ))}
              </Stack>
            </>
          ) : null}
        </Paper>

        <OfflineSyncActivityPanel
          title="Atividade offline deste caixa"
          description="Antes de fechar o caixa, confirme aqui se todas as vendas locais já foram reconciliadas com o servidor."
          entries={offlineActivityEntries}
          emptyMessage="Nenhuma venda offline registrada neste caixa até agora."
        />

        {/* Sangria / suprimento */}
        <Paper
          component="form"
          onSubmit={handleMovement}
          sx={{ ...ELEVATED_SURFACE_SX, p: 2.5 }}
        >
          <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
            Sangria / suprimento
          </Typography>
          {actionsBlockedOffline ? (
            <Alert severity="warning" sx={{ mb: 1.5 }}>
              O caixa está em modo offline com base no snapshot salvo. Sangria, suprimento e fechamento exigem conexão.
            </Alert>
          ) : null}
          {!canMovement ? <Alert severity="warning" sx={{ mb: 1.5 }}>Sem permissão para movimentar o caixa.</Alert> : null}
          {movementError ? <Alert severity="error" sx={{ mb: 1.5 }}>{movementError}</Alert> : null}
          {movementSuccess ? <Alert severity="success" sx={{ mb: 1.5 }}>{movementSuccess}</Alert> : null}
          <Stack spacing={1.5} direction={{ xs: 'column', sm: 'row' }}>
            <TextField
              select
              label="Tipo"
              value={movementType}
              onChange={(event) => setMovementType(event.target.value as CashMovementType)}
              sx={{ minWidth: 200 }}
              disabled={!canMovement || actionsBlockedOffline}
            >
              {(Object.keys(MOVEMENT_LABELS) as CashMovementType[]).map((type) => (
                <MenuItem key={type} value={type}>
                  {MOVEMENT_LABELS[type]}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              label="Valor"
              type="number"
              value={movementAmount}
              onChange={(event) => setMovementAmount(event.target.value)}
              disabled={!canMovement || actionsBlockedOffline}
              slotProps={{
                htmlInput: { min: 0, step: '0.01' },
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
              }}
            />
          </Stack>
          <TextField
            label="Motivo (opcional)"
            value={movementReason}
            onChange={(event) => setMovementReason(event.target.value)}
            fullWidth
            sx={{ mt: 1.5 }}
            disabled={!canMovement || actionsBlockedOffline}
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
          <Button type="submit" variant="outlined" sx={{ mt: 2 }} disabled={!canMovement || actionsBlockedOffline || isSavingMovement}>
            {isSavingMovement ? 'Registrando…' : 'Registrar movimentação'}
          </Button>
        </Paper>

        {/* Fechamento */}
        <Paper
          component="form"
          onSubmit={handleClose}
          sx={{ ...ELEVATED_SURFACE_SX, p: 2.5 }}
        >
          <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 0.5 }}>
            Fechar caixa
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)', mb: 1.5 }}>
            Informe o valor em dinheiro contado no caixa. O sistema calcula o esperado e a diferença ao confirmar.
          </Typography>
          {actionsBlockedOffline ? (
            <Alert severity="warning" sx={{ mb: 1.5 }}>
              O fechamento do caixa precisa ser concluído online para validar os totais do servidor.
            </Alert>
          ) : null}
          {closeBlockedByPendingSync ? (
            <Alert severity="warning" sx={{ mb: 1.5 }}>
              Fechamento bloqueado até que todas as vendas offline deste caixa sejam sincronizadas e fiquem sem falhas.
            </Alert>
          ) : null}
          {!canClose ? <Alert severity="warning" sx={{ mb: 1.5 }}>Sem permissão para fechar o caixa.</Alert> : null}
          {closeError ? <Alert severity="error" sx={{ mb: 1.5 }}>{closeError}</Alert> : null}
          <TextField
            label="Valor contado"
            type="number"
            value={declaredAmount}
            onChange={(event) => setDeclaredAmount(event.target.value)}
            disabled={!canClose || actionsBlockedOffline}
            slotProps={{
              htmlInput: { min: 0, step: '0.01' },
              input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
            }}
          />
          <Box>
            <Button
              type="submit"
              variant="contained"
              color="error"
              sx={{ mt: 2 }}
              disabled={!canClose || actionsBlockedOffline || closeBlockedByPendingSync || isClosing}
            >
              {isClosing ? 'Fechando…' : 'Fechar caixa'}
            </Button>
          </Box>
        </Paper>
      </Stack>
    </Box>
  )
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
      <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 600 }}>
        {value}
      </Typography>
    </Stack>
  )
}
