import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import GavelOutlinedIcon from '@mui/icons-material/GavelOutlined'
import {
  Alert,
  Box,
  Chip,
  CircularProgress,
  IconButton,
  Paper,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { deleteTaxRule, listTaxRules } from '../../services/taxRuleService'
import { CONTENT_CONTAINER_SX, SECTION_CARD_PADDING_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { TaxRule } from '../../types/taxRule'
import { TAX_TYPE_LABELS } from '../../types/taxRule'

function formatPercent(value: number): string {
  return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`
}

function formatDate(value: string | null): string {
  if (!value) return 'Sem limite'
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

function formatScope(rule: TaxRule): string[] {
  const scope = rule.scope ?? {}
  const chips: string[] = []

  if (scope.cfop?.length) chips.push(`CFOP: ${scope.cfop.join(', ')}`)
  if (scope.ncm?.length) chips.push(`NCM: ${scope.ncm.join(', ')}`)
  if (scope.uf_origin?.length) chips.push(`UF origem: ${scope.uf_origin.join(', ')}`)
  if (scope.uf_dest?.length) chips.push(`UF destino: ${scope.uf_dest.join(', ')}`)

  return chips
}

export function TaxRulesPage() {
  const [rules, setRules] = useState<TaxRule[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TaxRule | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    let cancelled = false

    setIsLoading(true)
    setLoadError(null)

    listTaxRules()
      .then((data) => {
        if (cancelled) return
        setRules(data)
      })
      .catch((error) => {
        if (cancelled) return
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as regras tributárias agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const sortedRules = useMemo(
    () =>
      [...rules].sort((a, b) => {
        if (a.is_active !== b.is_active) return a.is_active ? -1 : 1
        return (b.created_at ?? '').localeCompare(a.created_at ?? '')
      }),
    [rules],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return

    setIsDeleting(true)
    setDeleteError(null)

    try {
      await deleteTaxRule(deleteTarget.uuid)
      setRules((current) => current.filter((rule) => rule.uuid !== deleteTarget.uuid))
      setDeleteTarget(null)
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível excluir a regra tributária agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <Box sx={CONTENT_CONTAINER_SX}>
      <PageHeader
        title="Regras tributárias"
        subtitle="Cadastre alíquotas e vigências para preparar o contexto fiscal da empresa por CFOP, NCM e origem."
        breadcrumbs={[{ label: 'Configurações', to: '/configuracoes' }, { label: 'Regras tributárias' }]}
      />

      <Paper variant="outlined" sx={{ ...SECTION_CARD_PADDING_SX, ...ELEVATED_SURFACE_SX }}>
        {isLoading ? (
          <Stack sx={{ minHeight: 240, alignItems: 'center', justifyContent: 'center' }}>
            <CircularProgress size={28} />
          </Stack>
        ) : loadError ? (
          <Alert severity="error">{loadError}</Alert>
        ) : sortedRules.length === 0 ? (
          <EmptyState
            icon={<GavelOutlinedIcon sx={{ fontSize: 34 }} />}
            title="Nenhuma regra tributária cadastrada ainda"
            description="Quando a empresa começar a montar suas regras fiscais, elas aparecerão aqui por vigência e escopo."
          />
        ) : (
          <Stack spacing={2}>
            {sortedRules.map((rule) => {
              const scopeChips = formatScope(rule)
              const taxLabel = TAX_TYPE_LABELS[rule.tax_type] ?? rule.tax_type.toUpperCase()

              return (
                <Paper
                  key={rule.uuid}
                  variant="outlined"
                  sx={{ ...SECTION_CARD_PADDING_SX, ...SOFT_PANEL_SX }}
                >
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
                    <Box sx={{ minWidth: 0 }}>
                      <Stack direction="row" spacing={1} useFlexGap sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                        <Typography sx={{ fontSize: 18, fontWeight: 700 }}>{taxLabel}</Typography>
                        <Chip
                          size="small"
                          label={rule.is_active ? 'Ativa' : 'Inativa'}
                          color={rule.is_active ? 'success' : 'default'}
                        />
                      </Stack>

                      <Typography sx={{ mt: 1, fontSize: 28, fontWeight: 800 }}>
                        {formatPercent(rule.rate_percent)}
                      </Typography>

                      <Typography sx={{ mt: 1, color: 'var(--pt-muted)' }}>
                        Vigência: {formatDate(rule.valid_from)} até {formatDate(rule.valid_to)}
                      </Typography>

                      {scopeChips.length > 0 ? (
                        <Stack direction="row" spacing={1} useFlexGap sx={{ mt: 1.5, flexWrap: 'wrap' }}>
                          {scopeChips.map((label) => (
                            <Chip key={label} size="small" label={label} />
                          ))}
                        </Stack>
                      ) : (
                        <Typography sx={{ mt: 1.5, color: 'var(--pt-muted)' }}>
                          Regra global, sem filtro adicional de escopo.
                        </Typography>
                      )}
                    </Box>

                    <Stack direction="row" sx={{ justifyContent: 'flex-end', alignSelf: { xs: 'flex-start', sm: 'flex-start' } }}>
                      <Tooltip title={`Excluir regra ${taxLabel}`} arrow>
                        <IconButton
                          aria-label={`Excluir regra ${taxLabel}`}
                          color="error"
                          onClick={() => {
                            setDeleteError(null)
                            setDeleteTarget(rule)
                          }}
                        >
                          <DeleteOutlineIcon />
                        </IconButton>
                      </Tooltip>
                    </Stack>
                  </Stack>
                </Paper>
              )
            })}
          </Stack>
        )}
      </Paper>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir regra tributária"
        itemLabel={deleteTarget ? TAX_TYPE_LABELS[deleteTarget.tax_type] ?? deleteTarget.tax_type.toUpperCase() : null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => {
          if (isDeleting) return
          setDeleteTarget(null)
          setDeleteError(null)
        }}
        onConfirm={() => void handleConfirmDelete()}
      />
    </Box>
  )
}
