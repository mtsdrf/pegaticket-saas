import {
  Box,
  Chip,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { AbcClass, AbcItem } from '../../types/analytics'
import { formatCurrency, formatPercentage } from '../../utils/format'

/** Escala ordinal na cor primária (A = mais forte), letra sempre visível. */
const CLASS_MIX: Record<AbcClass, number> = { A: 100, B: 62, C: 34 }

function AbcClassChip({ curveClass }: { curveClass: AbcClass }) {
  const mix = CLASS_MIX[curveClass]
  return (
    <Chip
      label={curveClass}
      size="small"
      sx={{
        height: 22,
        minWidth: 32,
        fontSize: 11.5,
        fontWeight: 700,
        color: `color-mix(in srgb, var(--pt-primary) ${mix}%, var(--pt-muted))`,
        bgcolor: `color-mix(in srgb, var(--pt-primary) ${mix * 0.14}%, transparent)`,
        border: `1px solid color-mix(in srgb, var(--pt-primary) ${mix * 0.35}%, transparent)`,
      }}
    />
  )
}

interface AbcTableCardProps {
  title: string
  subtitle: string
  items: AbcItem[] | null
  isLoading: boolean
  emptyTitle: string
  emptyDescription: string
}

/** Curva ABC (produtos ou clientes): tabela com classe, receita e percentuais. */
export function AbcTableCard({ title, subtitle, items, isLoading, emptyTitle, emptyDescription }: AbcTableCardProps) {
  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
    >
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>{title}</Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>{subtitle}</Typography>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={38} sx={{ borderRadius: 'var(--pt-radius-md)' }} />
          ))}
        </Stack>
      ) : !items || items.length === 0 ? (
        <Box
          sx={{
            minHeight: 220,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>{emptyTitle}</Typography>
          <Typography sx={{ fontSize: 13.5 }}>{emptyDescription}</Typography>
        </Box>
      ) : (
        // A curva pode trazer TODOS os itens do tenant (centenas) — o
        // scroll fica contido no card, nunca estica a página.
        <Box sx={{ overflow: 'auto', maxHeight: 480 }}>
          <Table
            size="small"
            stickyHeader
            sx={{
              minWidth: 520,
              '& td, & th': { borderColor: 'var(--pt-border)' },
              '& th': { bgcolor: 'var(--pt-surface)' },
            }}
          >
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Nome</TableCell>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Classe</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                  Receita
                </TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                  Participação
                </TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                  Acumulado
                </TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((item, index) => (
                <TableRow key={`${item.name}-${index}`} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.name}</TableCell>
                  <TableCell>
                    <AbcClassChip curveClass={item.curve_class} />
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.revenue)}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatPercentage(item.participation_percentage)}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatPercentage(item.cumulative_percentage)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}
    </Paper>
  )
}
