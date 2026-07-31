import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import CircleOutlinedIcon from '@mui/icons-material/CircleOutlined'
import CloseIcon from '@mui/icons-material/Close'
import RocketLaunchOutlinedIcon from '@mui/icons-material/RocketLaunchOutlined'
import { Box, IconButton, LinearProgress, Paper, Stack, Typography } from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { OnboardingChecklist } from '../../types/onboarding'

interface OnboardingChecklistCardProps {
  checklist: OnboardingChecklist
  onDismiss: () => void
}

export function OnboardingChecklistCard({ checklist, onDismiss }: OnboardingChecklistCardProps) {
  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{
        p: { xs: 2.25, sm: 3 },
        ...ELEVATED_SURFACE_SX,
        borderColor: 'color-mix(in srgb, var(--pt-primary) 24%, var(--pt-border))',
        background: 'color-mix(in srgb, var(--pt-primary) 6%, var(--pt-surface))',
        position: 'relative',
      }}
    >
      <IconButton
        aria-label="Dispensar checklist de implantação"
        onClick={onDismiss}
        size="small"
        sx={{ position: 'absolute', top: 8, right: 8, minWidth: 44, minHeight: 44 }}
      >
        <CloseIcon fontSize="small" />
      </IconButton>

      <Stack spacing={2} sx={{ pr: { xs: 4, sm: 0 } }}>
        <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
          <Box
            sx={{
              width: 44,
              height: 44,
              borderRadius: '16px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              bgcolor: 'color-mix(in srgb, var(--pt-primary) 16%, transparent)',
              color: 'var(--pt-primary)',
              flexShrink: 0,
            }}
          >
            <RocketLaunchOutlinedIcon />
          </Box>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 16.5, fontWeight: 700, color: 'var(--pt-text)', mb: 0.25 }}>
              Vamos configurar sua empresa
            </Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Complete estes passos para colocar a operação no ar. {checklist.completed} de {checklist.total}
              concluídos.
            </Typography>
          </Box>
        </Box>

        <LinearProgress
          variant="determinate"
          value={(checklist.completed / checklist.total) * 100}
          sx={{
            height: 6,
            borderRadius: 3,
            bgcolor: 'var(--pt-surface-soft)',
            '& .MuiLinearProgress-bar': { borderRadius: 3, bgcolor: 'var(--pt-primary)' },
          }}
        />

        <Stack spacing={1}>
          {checklist.steps.map((step) => {
            const done = step.completed
            return (
              <Box
                key={step.key}
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: 1,
                  py: 0.5,
                }}
              >
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, minWidth: 0 }}>
                  {done ? (
                    <CheckCircleIcon sx={{ fontSize: 20, color: 'var(--pt-success, #2e7d32)', flexShrink: 0 }} />
                  ) : (
                    <CircleOutlinedIcon sx={{ fontSize: 20, color: 'var(--pt-muted)', flexShrink: 0 }} />
                  )}
                  <Typography
                    sx={{
                      fontSize: 14,
                      color: done ? 'var(--pt-muted)' : 'var(--pt-text)',
                      textDecoration: done ? 'line-through' : 'none',
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {step.label}
                  </Typography>
                </Box>
                {!done && (
                  <Typography
                    component={RouterLink}
                    to={step.to}
                    sx={{
                      fontSize: 13,
                      fontWeight: 600,
                      color: 'var(--pt-primary)',
                      whiteSpace: 'nowrap',
                      flexShrink: 0,
                      textDecoration: 'none',
                      minHeight: 44,
                      display: 'flex',
                      alignItems: 'center',
                      '&:hover': { textDecoration: 'underline' },
                    }}
                  >
                    {step.link_label}
                  </Typography>
                )}
              </Box>
            )
          })}
        </Stack>
      </Stack>
    </Paper>
  )
}
