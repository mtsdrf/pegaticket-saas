import { Box, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { ReceivablesAgingPoint } from '../../types/report'
import { formatCurrency } from '../../utils/format'

interface ReceivablesAgingCardProps {
  buckets: ReceivablesAgingPoint[] | null
  isLoading: boolean
}

/** Mesma altura fixa (440) e estrutura flex de `RankingListCard` — os três cards dessa linha (aging + 2 curvas ABC) precisam ficar exatamente do mesmo tamanho. */
const CARD_SX = {
  p: { xs: 2, sm: 3 },
  ...ELEVATED_SURFACE_SX,
  height: 440,
  display: 'flex',
  flexDirection: 'column',
} as const

export function ReceivablesAgingCard({ buckets, isLoading }: ReceivablesAgingCardProps) {
  if (isLoading) {
    return (
      <Paper variant="outlined" className="mk-reveal" sx={CARD_SX}>
        <Skeleton variant="text" width={200} height={28} sx={{ flexShrink: 0 }} />
        <Skeleton variant="text" width={260} height={22} sx={{ mb: 2, flexShrink: 0 }} />
        <Stack spacing={1.25} sx={{ flex: 1, minHeight: 0, overflow: 'hidden' }}>
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={54} sx={{ borderRadius: 'var(--mk-radius-md)', flexShrink: 0 }} />
          ))}
        </Stack>
      </Paper>
    )
  }

  if (!buckets || buckets.every((bucket) => bucket.count === 0)) {
    return (
      <Paper variant="outlined" className="mk-reveal" sx={CARD_SX}>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--mk-text)', mb: 0.25, flexShrink: 0 }}>
          Aging de recebíveis
        </Typography>
        <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', mb: 2, flexShrink: 0 }}>
          Distribuição financeira entre valores a vencer e vencidos.
        </Typography>
        <Box
          sx={{
            flex: 1,
            minHeight: 0,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexDirection: 'column',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--mk-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', fontSize: 14.5 }}>
            Nenhum recebível em aberto agora
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>
            Quando houver pedidos ou parcelas abertas, a distribuição aparece aqui.
          </Typography>
        </Box>
      </Paper>
    )
  }

  return (
    <Paper variant="outlined" className="mk-reveal" sx={CARD_SX}>
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--mk-text)', mb: 0.25, flexShrink: 0 }}>
        Aging de recebíveis
      </Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', mb: 2, flexShrink: 0 }}>
        Distribuição financeira entre valores a vencer e vencidos.
      </Typography>

      <Box sx={{ flex: 1, minHeight: 0, overflowY: 'auto', pr: 0.5, overscrollBehavior: 'contain' }}>
        <Stack spacing={1}>
          {buckets.map((bucket) => (
            <Box
              key={bucket.bucket}
              sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 1.5,
                p: 1.25,
                ...SOFT_PANEL_SX,
                background:
                  bucket.bucket === 'current'
                    ? SOFT_PANEL_SX.background
                    : 'color-mix(in srgb, var(--mk-warning) 8%, var(--mk-surface-soft))',
              }}
            >
              <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontWeight: 600, fontSize: 14, color: 'var(--mk-text)' }}>{bucket.label}</Typography>
                <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                  {bucket.count} título{bucket.count === 1 ? '' : 's'}
                </Typography>
              </Box>
              <Typography sx={{ fontWeight: 700, fontSize: 14, color: 'var(--mk-text)', flexShrink: 0 }}>
                {formatCurrency(bucket.amount)}
              </Typography>
            </Box>
          ))}
        </Stack>
      </Box>
    </Paper>
  )
}
