import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { Box, Paper, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

interface PlanUpgradeNoticeProps {
  /** Nome amigável do recurso bloqueado (ex.: "as análises avançadas"). */
  featureLabel?: string
  /** Ação opcional (ex.: botão de contato/upgrade) exibida abaixo do texto. */
  action?: ReactNode
}

/**
 * Aviso amigável para recurso bloqueado por plano (`PLAN_UPGRADE_REQUIRED`).
 * Usar sempre que `isPlanUpgradeRequired(error)` (types/api.ts) for true,
 * em vez de renderizar o Alert genérico de erro.
 */
export function PlanUpgradeNotice({ featureLabel, action }: PlanUpgradeNoticeProps) {
  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{
        p: { xs: 3, sm: 5 },
        ...ELEVATED_SURFACE_SX,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        textAlign: 'center',
        gap: 1.5,
      }}
    >
      <Box
        sx={{
          width: 64,
          height: 64,
          borderRadius: '18px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          bgcolor: 'color-mix(in srgb, var(--pt-accent) 14%, transparent)',
          color: 'var(--pt-accent)',
        }}
      >
        <WorkspacePremiumOutlinedIcon sx={{ fontSize: 32 }} />
      </Box>

      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: { xs: 17, sm: 19 }, color: 'var(--pt-text)' }}>
        Este recurso não está incluído no seu plano
      </Typography>

      <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', maxWidth: 480 }}>
        {featureLabel
          ? `O plano atual da sua empresa não libera ${featureLabel}. `
          : 'O plano atual da sua empresa não libera esta funcionalidade. '}
        Fale com o administrador da plataforma para atualizar o plano e desbloquear este recurso.
      </Typography>

      {action ? <Box sx={{ mt: 1 }}>{action}</Box> : null}
    </Paper>
  )
}
