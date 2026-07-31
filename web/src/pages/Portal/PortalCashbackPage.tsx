import SavingsOutlinedIcon from '@mui/icons-material/SavingsOutlined'
import { Alert, Box, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { listCashbackBalances } from '../../services/cashbackService'
import type { CashbackBalanceByStore } from '../../types/cashback'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency, formatDateBR } from '../../utils/format'
import { PortalShell } from './PortalShell'

function LoadingSkeleton() {
  return (
    <Stack spacing={1.5}>
      {[0, 1].map((index) => (
        <Skeleton key={index} variant="rounded" height={104} sx={{ borderRadius: 'var(--mk-radius-xl)' }} />
      ))}
    </Stack>
  )
}

function EmptyState() {
  return (
    <Paper
      elevation={0}
      sx={{ p: 4, textAlign: 'center', ...ELEVATED_SURFACE_SX }}
    >
      <SavingsOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 16, mb: 0.75 }}>Nenhum cashback por aqui ainda</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
        Compre em lojas que participam do programa de cashback para começar a acumular saldo.
      </Typography>
    </Paper>
  )
}

function StoreCashbackCard({ store }: { store: CashbackBalanceByStore }) {
  return (
    <Paper
      elevation={0}
      sx={{ p: 1.75, ...ELEVATED_SURFACE_SX }}
    >
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14, fontWeight: 700 }}>{store.tenant_name}</Typography>
      <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)', mb: 1 }}>{store.program_name}</Typography>

      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'baseline' }}>
        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>Disponível para usar</Typography>
        <Typography sx={{ fontSize: 18, fontWeight: 700, color: 'var(--mk-primary)' }}>
          {formatCurrency(store.available_amount)}
        </Typography>
      </Stack>

      {store.pending_amount > 0 && (
        <Stack direction="row" sx={{ justifyContent: 'space-between', mt: 0.5 }}>
          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>Em carência</Typography>
          <Typography sx={{ fontSize: 13, fontWeight: 600 }}>{formatCurrency(store.pending_amount)}</Typography>
        </Stack>
      )}

      {store.next_expiration && (
        <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)', mt: 1 }}>
          {formatCurrency(store.next_expiration.amount)} expira em {formatDateBR(store.next_expiration.expires_at)}
        </Typography>
      )}
    </Paper>
  )
}

/** Extrato resumido de cashback (Delivery Fase 5) — saldo por loja com vínculo confirmado, mais recente/relevante primeiro (ordem já vem do backend). */
export function PortalCashbackPage() {
  const [stores, setStores] = useState<CashbackBalanceByStore[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    setIsLoading(true)
    setErrorMessage(null)

    listCashbackBalances()
      .then((result) => setStores(result.filter((store) => store.enabled)))
      .catch((error: unknown) => {
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar seu cashback agora. Tente novamente.'))
      })
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <PortalShell title="Cashback" subtitle="Seu saldo em cada loja que participa do programa.">
      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && stores && stores.length === 0 && <EmptyState />}

      {!isLoading && !errorMessage && stores && stores.length > 0 && (
        <Stack spacing={1.25}>
          {stores.map((store) => (
            <StoreCashbackCard key={store.tenant_uuid} store={store} />
          ))}
        </Stack>
      )}

      <Box sx={{ mt: 2.5 }}>
        <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)', textAlign: 'center' }}>
          O cashback é creditado depois que o pagamento do pedido é confirmado pela loja.
        </Typography>
      </Box>
    </PortalShell>
  )
}
