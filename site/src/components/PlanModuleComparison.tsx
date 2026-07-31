import CheckRoundedIcon from '@mui/icons-material/CheckRounded'
import RemoveRoundedIcon from '@mui/icons-material/RemoveRounded'
import { Box, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'
import { MODULES } from '../data/modules'
import { PLANS } from '../data/plans'

/**
 * Comparativo módulo × plano — reaproveita `data/modules.ts` (fonte única,
 * mapeada 1:1 das functionalities do seeder) e `data/plans.ts` (moduleKeys
 * por plano). Nenhum módulo/recurso inventado aqui.
 */
export function PlanModuleComparison() {
  return (
    <Box
      sx={{
        overflowX: 'auto',
        borderRadius: 'var(--mk-radius-lg)',
        border: '1px solid var(--mk-border)',
        backgroundColor: 'var(--mk-surface)',
      }}
    >
      <Table sx={{ minWidth: 560 }}>
        <TableHead>
          <TableRow>
            <TableCell sx={{ fontWeight: 700, color: 'var(--mk-text)' }}>Módulo</TableCell>
            {PLANS.map((plan) => (
              <TableCell key={plan.slug} align="center" sx={{ fontWeight: 700, color: 'var(--mk-text)' }}>
                {plan.name}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {MODULES.map((mod) => (
            <TableRow key={mod.key}>
              <TableCell>
                <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--mk-text)' }}>{mod.title}</Typography>
                <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>{mod.description}</Typography>
              </TableCell>
              {PLANS.map((plan) => {
                const included = plan.moduleKeys.includes(mod.key)
                return (
                  <TableCell key={plan.slug} align="center">
                    {included ? (
                      <CheckRoundedIcon sx={{ fontSize: 20, color: 'var(--mk-success)' }} titleAccess={`Incluso no plano ${plan.name}`} />
                    ) : (
                      <RemoveRoundedIcon sx={{ fontSize: 18, color: 'var(--mk-border)' }} titleAccess={`Não incluso no plano ${plan.name}`} />
                    )}
                  </TableCell>
                )
              })}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Box>
  )
}
