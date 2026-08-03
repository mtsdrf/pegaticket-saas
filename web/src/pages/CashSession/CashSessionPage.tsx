import { Alert, Box, Button, Chip, Skeleton, Stack, TextField, Typography } from '@mui/material'
import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import { useEffect, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import * as cashSessionService from '../../services/cashSessionService'
import type { CashSession } from '../../types/cashSession'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

export function CashSessionPage() {
  const { hasPermission } = useAuth()
  const canOpen = hasPermission(ACCESS.cashSessionsOpen)
  const canClose = hasPermission(ACCESS.cashSessionsClose)

  const [session, setSession] = useState<CashSession | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [openingAmount, setOpeningAmount] = useState('')
  const [openingNotes, setOpeningNotes] = useState('')
  const [closingAmount, setClosingAmount] = useState('')
  const [closingNotes, setClosingNotes] = useState('')

  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    cashSessionService
      .getCurrentCashSession()
      .then(setSession)
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o caixa agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(load, [])

  async function handleOpen() {
    setFormError(null)
    setSuccessMessage(null)
    setIsSubmitting(true)
    try {
      const opened = await cashSessionService.openCashSession({
        opening_amount: Number(openingAmount.replace(',', '.')) || 0,
        opening_notes: openingNotes.trim() || null,
      })
      setSession(opened)
      setOpeningAmount('')
      setOpeningNotes('')
      setSuccessMessage('Caixa aberto com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível abrir o caixa agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleClose() {
    setFormError(null)
    setSuccessMessage(null)
    setIsSubmitting(true)
    try {
      const closed = await cashSessionService.closeCashSession({
        closing_amount: Number(closingAmount.replace(',', '.')) || 0,
        closing_notes: closingNotes.trim() || null,
      })
      setSession(closed)
      setClosingAmount('')
      setClosingNotes('')
      setSuccessMessage('Caixa fechado com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível fechar o caixa agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  const isOpen = session?.status === 'aberto'

  return (
    <CrudListPage
      title="Caixa"
      subtitle="Abra o caixa no início do turno e feche com a contagem no final."
      isLoading={isLoading}
      error={loadError}
      onRetry={load}
      isEmpty={false}
    >
      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}
      {successMessage && (
        <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
          {successMessage}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={220} />
      ) : isOpen && session ? (
        <Stack spacing={3}>
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <Chip label="Caixa aberto" color="success" sx={{ fontWeight: 600 }} />
            <Typography sx={{ color: 'var(--pt-muted)', fontSize: 13.5 }}>
              Aberto em {formatDateTimeBR(session.opened_at)}
              {session.opened_by_name ? ` por ${session.opened_by_name}` : ''}
            </Typography>
          </Stack>

          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)' },
              gap: 1.5,
            }}
          >
            <MetricCard
              icon={PointOfSaleOutlinedIcon}
              label="Valor de abertura"
              value={formatCurrency(session.opening_amount)}
              tone="primary"
              index={0}
            />
            <MetricCard
              icon={PointOfSaleOutlinedIcon}
              label="Esperado em dinheiro agora"
              value={session.expected_cash_amount ? formatCurrency(session.expected_cash_amount) : '—'}
              tone="accent"
              index={1}
            />
          </Box>

          {canClose && (
            <Stack spacing={2} sx={{ maxWidth: 420 }}>
              <Typography sx={{ fontWeight: 600, fontSize: 16 }}>Fechar caixa</Typography>
              <TextField
                label="Valor contado ao fechar"
                type="number"
                value={closingAmount}
                onChange={(event) => setClosingAmount(event.target.value)}
                slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
              />
              <TextField
                label="Observações (opcional)"
                value={closingNotes}
                onChange={(event) => setClosingNotes(event.target.value)}
                multiline
                minRows={2}
              />
              <Button
                variant="contained"
                disabled={isSubmitting || closingAmount === ''}
                onClick={() => void handleClose()}
                sx={{ minHeight: 44, alignSelf: 'flex-start' }}
              >
                {isSubmitting ? 'Fechando…' : 'Fechar caixa'}
              </Button>
            </Stack>
          )}
        </Stack>
      ) : (
        <Stack spacing={2}>
          <Chip label="Nenhum caixa aberto" sx={{ alignSelf: 'flex-start' }} />
          {canOpen && (
            <Stack spacing={2} sx={{ maxWidth: 420 }}>
              <Typography sx={{ fontWeight: 600, fontSize: 16 }}>Abrir caixa</Typography>
              <TextField
                label="Valor inicial (troco)"
                type="number"
                value={openingAmount}
                onChange={(event) => setOpeningAmount(event.target.value)}
                slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
              />
              <TextField
                label="Observações (opcional)"
                value={openingNotes}
                onChange={(event) => setOpeningNotes(event.target.value)}
                multiline
                minRows={2}
              />
              <Button
                variant="contained"
                disabled={isSubmitting || openingAmount === ''}
                onClick={() => void handleOpen()}
                sx={{ minHeight: 44, alignSelf: 'flex-start' }}
              >
                {isSubmitting ? 'Abrindo…' : 'Abrir caixa'}
              </Button>
            </Stack>
          )}
        </Stack>
      )}
    </CrudListPage>
  )
}
