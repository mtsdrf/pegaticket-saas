import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import { Alert, Box, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as portalCouponService from '../../services/portalCouponService'
import { getApiErrorMessage } from '../../types/api'
import type { PortalCouponRedemption } from '../../types/portal'
import { formatDateTimeBR } from '../../utils/format'
import { PortalShell } from './PortalShell'

function LoadingSkeleton() {
  return (
    <Stack spacing={1.25}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={76} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
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
      <ConfirmationNumberOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 16, mb: 0.75 }}>Nenhum voucher utilizado ainda</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
        Os cupons que você usar nas lojas aparecem aqui, com a data de uso.
      </Typography>
    </Paper>
  )
}

function VoucherCard({ voucher }: { voucher: PortalCouponRedemption }) {
  return (
    <Paper
      elevation={0}
      sx={{ p: 1.75, ...ELEVATED_SURFACE_SX }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5 }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14.5, fontWeight: 700, wordBreak: 'break-word' }}>
            {voucher.coupon_code ?? 'Cupom'}
          </Typography>
          {voucher.tenant_name && (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', wordBreak: 'break-word' }}>
              {voucher.tenant_name}
            </Typography>
          )}
        </Box>
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', flexShrink: 0, textAlign: 'right' }}>
          {formatDateTimeBR(voucher.redeemed_at)}
        </Typography>
      </Stack>
    </Paper>
  )
}

/** "Meus vouchers" (roadmap Loja) — histórico read-only de cupons já usados, cross-tenant. */
export function PortalVouchersPage() {
  const [vouchers, setVouchers] = useState<PortalCouponRedemption[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    setIsLoading(true)
    setErrorMessage(null)
    portalCouponService
      .listPortalCouponRedemptions()
      .then((result) => setVouchers(result))
      .catch((error: unknown) => {
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar seus vouchers agora. Tente novamente.'))
      })
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <PortalShell title="Meus vouchers" subtitle="Cupons que você já usou nas lojas.">
      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && vouchers && vouchers.length === 0 && <EmptyState />}

      {!isLoading && !errorMessage && vouchers && vouchers.length > 0 && (
        <Stack spacing={1.25}>
          {vouchers.map((voucher, index) => (
            <VoucherCard key={`${voucher.order_uuid ?? 'voucher'}-${index}`} voucher={voucher} />
          ))}
        </Stack>
      )}
    </PortalShell>
  )
}
