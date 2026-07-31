import { Box, Paper, Skeleton, Stack, Typography } from '@mui/material'

import type { ReactNode } from 'react'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

interface RankingItem {
  title: string
  value: string
  meta?: string
  /** Chip/selo opcional ao lado do título (ex.: rótulo RFM nas Análises). */
  badge?: ReactNode
}

interface RankingListCardProps {
  title: string
  subtitle: string
  items: RankingItem[] | null
  isLoading: boolean
  emptyTitle: string
  emptyDescription: string
}

export function RankingListCard({
  title,
  subtitle,
  items,
  isLoading,
  emptyTitle,
  emptyDescription,
}: RankingListCardProps) {
  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{
        p: { xs: 2, sm: 3 },
        ...ELEVATED_SURFACE_SX,
        // Altura fixa (não só máxima) — sem isso, um card com poucos itens
        // fica visivelmente menor que o vizinho na mesma linha, mesmo com
        // display:grid stretch (o Paper só estica até a maior altura de
        // CONTEÚDO da linha, que varia conforme a quantidade de itens de
        // cada card). Com altura fixa, todos os RankingListCard da tela
        // ficam do mesmo tamanho, sempre — a lista rola internamente
        // quando não cabe, o card nunca cresce/encolhe.
        height: 440,
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25, flexShrink: 0 }}>{title}</Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2, flexShrink: 0 }}>{subtitle}</Typography>

      {isLoading ? (
        <Stack spacing={1.25} sx={{ flex: 1, minHeight: 0, overflow: 'hidden' }}>
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: 'var(--pt-radius-md)', flexShrink: 0 }} />
          ))}
        </Stack>
      ) : !items || items.length === 0 ? (
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
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>{emptyTitle}</Typography>
          <Typography sx={{ fontSize: 13.5 }}>{emptyDescription}</Typography>
        </Box>
      ) : (
        <Box
          sx={{
            flex: 1,
            minHeight: 0,
            overflowY: 'auto',
            pr: 0.5,
            // Independente do scroll da página — sem isso, um card com
            // lista longa "rouba" o gesto de rolagem em telas touch antes
            // de chegar à borda, e o usuário acha que travou.
            overscrollBehavior: 'contain',
          }}
        >
          <Stack spacing={1}>
            {items.map((item, index) => (
              <Box
                key={`${item.title}-${index}`}
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: 1.5,
                  p: 1.25,
                  ...SOFT_PANEL_SX,
                }}
              >
                <Box sx={{ minWidth: 0 }}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75, flexWrap: 'wrap' }}>
                    <Typography sx={{ fontWeight: 600, fontSize: 14, color: 'var(--pt-text)' }}>{item.title}</Typography>
                    {item.badge ?? null}
                  </Box>
                  {item.meta ? (
                    <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{item.meta}</Typography>
                  ) : null}
                </Box>
                <Typography sx={{ fontWeight: 700, fontSize: 14, color: 'var(--pt-text)', flexShrink: 0 }}>
                  {item.value}
                </Typography>
              </Box>
            ))}
          </Stack>
        </Box>
      )}
    </Paper>
  )
}
