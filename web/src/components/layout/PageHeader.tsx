import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import { Box, Breadcrumbs, Button, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

export interface PageHeaderBreadcrumb {
  label: string
  to?: string
}

interface PageHeaderProps {
  title: string
  subtitle?: string
  /** Trilha acima do título (ex.: Produtos > Categorias) — só aparece quando informada. */
  breadcrumbs?: PageHeaderBreadcrumb[]
  /** Link "Voltar" acima do título — usado por telas de formulário, mutuamente exclusivo com breadcrumbs na prática. */
  backLink?: { label: string; to: string }
  /** Ação primária (ex.: botão "Novo X") — fica alinhada à direita em telas >= sm. */
  action?: ReactNode
  /** Sublinhado em gradiente da marca — só o Dashboard usa hoje. */
  accent?: boolean
}

/**
 * Cabeçalho de página reutilizável — título/subtítulo/ação primária
 * (usado por toda listagem) e, opcionalmente, breadcrumbs ou link de
 * voltar (usado por formulários e telas aninhadas). Centraliza o que
 * antes estava duplicado em `CrudListPage`, `CrudFormShell`,
 * `ClientListPage` e `DashboardPage`.
 */
export function PageHeader({ title, subtitle, breadcrumbs, backLink, action, accent }: PageHeaderProps) {
  return (
    <Box sx={{ mb: { xs: 3, sm: 4 } }}>
      {breadcrumbs && breadcrumbs.length > 0 && (
        <Breadcrumbs
          separator={<ChevronRightIcon sx={{ fontSize: 16 }} />}
          sx={{ mb: 1.25, fontSize: 13.5, color: 'var(--pt-muted)' }}
        >
          {breadcrumbs.map((crumb, index) =>
            crumb.to && index < breadcrumbs.length - 1 ? (
              <Box
                key={crumb.label}
                component={RouterLink}
                to={crumb.to}
                sx={{ color: 'var(--pt-muted)', textDecoration: 'none', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                {crumb.label}
              </Box>
            ) : (
              <Typography key={crumb.label} sx={{ fontSize: 13.5, color: 'var(--pt-text)', fontWeight: 500 }}>
                {crumb.label}
              </Typography>
            ),
          )}
        </Breadcrumbs>
      )}

      {backLink && !(breadcrumbs && breadcrumbs.length > 0) && (
        <Stack direction="row" sx={{ mb: 1 }}>
          <Button
            startIcon={<ArrowBackIcon />}
            component={RouterLink}
            to={backLink.to}
            color="inherit"
            sx={{
              ml: -1,
              minHeight: UI_SIZE.compactControl,
              borderRadius: UI_RADIUS.md,
              color: 'var(--pt-muted)',
              '&:hover': {
                backgroundColor: 'color-mix(in srgb, var(--pt-primary) 8%, transparent)',
                color: 'var(--pt-primary)',
              },
            }}
          >
            {backLink.label}
          </Button>
        </Stack>
      )}

      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={2}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' } }}
      >
        <Box sx={{ minWidth: 0 }}>
          <Typography
            variant="h5"
            component="h1"
            sx={{
              fontFamily: '"Sora", "Inter", sans-serif',
              fontWeight: 700,
              fontSize: { xs: 28, sm: 32, lg: 36 },
              letterSpacing: '-0.03em',
              lineHeight: 1.08,
              mb: 0.75,
            }}
          >
            {title}
          </Typography>
          {subtitle && (
            <Typography
              variant="body1"
              sx={{ color: 'var(--pt-muted)', fontSize: { xs: 14.5, sm: 15.5 }, maxWidth: 760 }}
            >
              {subtitle}
            </Typography>
          )}
          {accent && (
            <Box
              aria-hidden="true"
              sx={{
                mt: 2,
                width: 72,
                height: 4,
                borderRadius: 999,
                boxShadow: '0 10px 22px color-mix(in srgb, var(--pt-primary) 24%, transparent)',
                background: 'linear-gradient(90deg, var(--pt-primary) 0%, var(--pt-secondary) 52%, var(--pt-accent) 100%)',
              }}
            />
          )}
        </Box>

        {action && (
          <Box sx={{ alignSelf: { xs: 'stretch', sm: 'center' } }}>{action}</Box>
        )}
      </Stack>
    </Box>
  )
}
