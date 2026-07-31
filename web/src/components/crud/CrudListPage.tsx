import AddIcon from '@mui/icons-material/Add'
import { Alert, Box, Button, Pagination, Paper, Skeleton, Stack } from '@mui/material'
import type { ReactNode } from 'react'
import { EmptyState } from '../layout/EmptyState'
import { PageHeader, type PageHeaderBreadcrumb } from '../layout/PageHeader'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { PaginationMeta } from '../../types/pagination'
import { PAGE_CONTAINER_SX, SECTION_CARD_PADDING_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

interface CrudListPageProps {
  title: string
  subtitle: string
  breadcrumbs?: PageHeaderBreadcrumb[]
  createLabel?: string
  onCreate?: () => void
  canCreate?: boolean
  /** Ação extra ao lado do botão de criação principal (ex.: "Convidar usuário") — renderizada antes dele. */
  secondaryAction?: ReactNode
  /** Linha de busca/filtros acima da tabela — cada tela define o que precisa. */
  toolbar?: ReactNode
  error: string | null
  onRetry: () => void
  isLoading: boolean
  /** `false` fixo em telas que usam `ServerDataGrid` — o próprio grid trata seu estado vazio (`emptyState` do `ServerDataGrid`), não duplicar aqui. */
  isEmpty: boolean
  emptyIcon?: ReactNode
  emptyTitle?: string
  emptyDescription?: string
  emptyAction?: ReactNode
  /** Paginação MUI própria — não usar em telas com `ServerDataGrid` (paginação já embutida no grid). */
  pagination?: PaginationMeta | null
  onPageChange?: (page: number) => void
  skeletonRows?: number
  /** Tabela/grid já montada (ag-Grid ou outro) — renderizada quando há dados. */
  children: ReactNode
}

/**
 * Casca reutilizável de toda tela de listagem (Cliente, Produto,
 * Categoria/Tipo de produto etc.): cabeçalho + CTA de criação, slot de
 * filtros, e o card com estado de erro/loading/vazio/dados + paginação.
 * A tabela em si (ag-Grid, colunas específicas de cada domínio) fica a
 * cargo de quem usa — só o "chrome" ao redor é compartilhado.
 */
export function CrudListPage({
  title,
  subtitle,
  breadcrumbs,
  createLabel,
  onCreate,
  canCreate = true,
  secondaryAction,
  toolbar,
  error,
  onRetry,
  isLoading,
  isEmpty,
  emptyIcon,
  emptyTitle,
  emptyDescription,
  emptyAction,
  pagination,
  onPageChange,
  skeletonRows = 6,
  children,
}: CrudListPageProps) {
  return (
    <Box sx={PAGE_CONTAINER_SX}>
      <PageHeader
        title={title}
        subtitle={subtitle}
        breadcrumbs={breadcrumbs}
        action={
          secondaryAction || (createLabel && onCreate && canCreate) ? (
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25} sx={{ width: { xs: '100%', sm: 'auto' } }}>
              {secondaryAction}
              {createLabel && onCreate && canCreate && (
                <Button
                  variant="contained"
                  startIcon={<AddIcon />}
                  onClick={onCreate}
                  sx={{ minHeight: UI_SIZE.pageAction, width: { xs: '100%', sm: 'auto' }, borderRadius: UI_RADIUS.md }}
                >
                  {createLabel}
                </Button>
              )}
            </Stack>
          ) : undefined
        }
      />

      {toolbar && (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 2.75 }}>
          {toolbar}
        </Stack>
      )}

      <Paper
        variant="outlined"
        sx={{
          ...SECTION_CARD_PADDING_SX,
          ...ELEVATED_SURFACE_SX,
          background: 'var(--pt-surface-raised-bg)',
        }}
      >
        {error && (
          <Alert
            severity="error"
            variant="outlined"
            action={
              <Button color="inherit" size="small" onClick={onRetry}>
                Tentar de novo
              </Button>
            }
          >
            {error}
          </Alert>
        )}

        {!error && isLoading && (
          <Stack spacing={1}>
            {Array.from({ length: skeletonRows }).map((_, index) => (
              <Skeleton key={index} variant="rounded" height={48} sx={{ borderRadius: '14px' }} />
            ))}
          </Stack>
        )}

        {!error && !isLoading && isEmpty && emptyTitle && emptyDescription && (
          <EmptyState icon={emptyIcon} title={emptyTitle} description={emptyDescription} action={emptyAction} />
        )}

        {!error && !isLoading && !isEmpty && (
          <>
            {children}

            {pagination && onPageChange && pagination.last_page > 1 && (
              <Stack sx={{ mt: 2.5, alignItems: 'center' }}>
                <Pagination
                  page={pagination.current_page}
                  count={pagination.last_page}
                  onChange={(_, value) => onPageChange(value)}
                  color="primary"
                  shape="rounded"
                  size="large"
                />
              </Stack>
            )}
          </>
        )}
      </Paper>
    </Box>
  )
}
