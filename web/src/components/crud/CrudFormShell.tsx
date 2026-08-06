import { Alert, Box, Button, Paper, Skeleton, Stack } from '@mui/material'
import type { FormEvent, ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { PageHeader, type PageHeaderBreadcrumb } from '../layout/PageHeader'
import { FORM_FIELD_SURFACE_SX } from '../../styles/formFieldStyles'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { CONTENT_CONTAINER_SX, PAGE_CONTAINER_SX, SECTION_CARD_PADDING_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

interface CrudFormShellProps {
  backLabel: string
  backTo: string
  title: string
  subtitle: string
  breadcrumbs?: PageHeaderBreadcrumb[]
  loadError?: string | null
  isLoadingRecord?: boolean
  formError?: string | null
  isSubmitting: boolean
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  onCancel?: () => void
  submitLabel?: string
  submittingLabel?: string
  children: ReactNode
}

/**
 * Casca reutilizável de toda tela de formulário (create/edit): link de
 * voltar, título/subtítulo, card com skeleton de carregamento (modo
 * edição) ou erro de carga, alerta de erro de submit, e as ações
 * Cancelar/Salvar. Os campos em si ficam a cargo de quem usa — desde um
 * `SchemaFormPage` genérico até um formulário totalmente customizado
 * (ex.: Produto, com upload de imagem e selects em cascata).
 */
export function CrudFormShell({
  backLabel,
  backTo,
  title,
  subtitle,
  breadcrumbs,
  loadError,
  isLoadingRecord,
  formError,
  isSubmitting,
  onSubmit,
  onCancel,
  submitLabel = 'Salvar',
  submittingLabel = 'Salvando…',
  children,
}: CrudFormShellProps) {
  const navigate = useNavigate()
  const handleCancel = onCancel ?? (() => navigate(backTo))

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: { xs: '100%', sm: 900, lg: 1100 } }}>
      <PageHeader
        title={title}
        subtitle={subtitle}
        breadcrumbs={breadcrumbs}
        backLink={{ label: backLabel, to: backTo }}
      />

      <Paper
        variant="outlined"
        sx={{
          ...SECTION_CARD_PADDING_SX,
          ...ELEVATED_SURFACE_SX,
          ...FORM_FIELD_SURFACE_SX,
          background: 'var(--pt-surface-raised-bg)',
        }}
      >
        {loadError && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {loadError}
          </Alert>
        )}

        {isLoadingRecord ? (
          <Stack spacing={2}>
            {Array.from({ length: 5 }).map((_, index) => (
              <Skeleton key={index} variant="rounded" height={44} />
            ))}
          </Stack>
        ) : (
          !loadError && (
            <Box component="form" onSubmit={onSubmit} noValidate>
              {formError && (
                <Alert severity="error" sx={{ mb: 2.5 }}>
                  {formError}
                </Alert>
              )}

              <Box sx={{ ...CONTENT_CONTAINER_SX, gap: 3 }}>{children}</Box>

              <Stack
                direction={{ xs: 'column-reverse', sm: 'row' }}
                spacing={1.5}
                sx={{ mt: 3.5, justifyContent: { xs: 'stretch', sm: 'flex-end' } }}
              >
                <Button
                  color="inherit"
                  onClick={handleCancel}
                  disabled={isSubmitting}
                  sx={{
                    flex: { xs: 1, sm: '0 0 auto' },
                    minHeight: UI_SIZE.control,
                    ...SOFT_PANEL_SX,
                    color: 'var(--pt-text)',
                    borderRadius: UI_RADIUS.md,
                  }}
                >
                  Cancelar
                </Button>
                <Button
                  type="submit"
                  variant="contained"
                  disabled={isSubmitting}
                  sx={{
                    flex: { xs: 1, sm: '0 0 auto' },
                    minWidth: { sm: 148 },
                    minHeight: UI_SIZE.controlLarge,
                    borderRadius: UI_RADIUS.md,
                  }}
                >
                  {isSubmitting ? submittingLabel : submitLabel}
                </Button>
              </Stack>
            </Box>
          )
        )}
      </Paper>
    </Box>
  )
}
