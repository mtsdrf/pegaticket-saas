import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import { Box, Button, Chip, IconButton, Paper, Stack, Tooltip, Typography } from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as taxRuleService from '../../services/taxRuleService'
import { getApiErrorMessage } from '../../types/api'
import type { TaxRule } from '../../types/taxRule'
import { TAX_TYPE_LABELS } from '../../types/taxRule'

function renderScope(rule: TaxRule): string[] {
  if (!rule.scope) return ['Regra geral']

  const entries: string[] = []
  if (rule.scope.cfop?.length) entries.push(`CFOP: ${rule.scope.cfop.join(', ')}`)
  if (rule.scope.ncm?.length) entries.push(`NCM: ${rule.scope.ncm.join(', ')}`)
  if (rule.scope.uf_origin?.length) entries.push(`UF origem: ${rule.scope.uf_origin.join(', ')}`)
  if (rule.scope.uf_dest?.length) entries.push(`UF destino: ${rule.scope.uf_dest.join(', ')}`)
  return entries.length > 0 ? entries : ['Regra geral']
}

export function TaxRuleListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()

  const [rules, setRules] = useState<TaxRule[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TaxRule | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    taxRuleService
      .listTaxRules()
      .then(setRules)
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as regras tributárias agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const orderedRules = useMemo(
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
      await taxRuleService.deleteTaxRule(deleteTarget.uuid)
      setDeleteTarget(null)
      setRules((current) => current.filter((rule) => rule.uuid !== deleteTarget.uuid))
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível excluir esta regra tributária agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <>
      <CrudListPage
        title="Regras tributárias"
        subtitle="Cadastre alíquotas e vigências por tipo de tributo para preparar a empresa para integrações e operações fiscais."
        breadcrumbs={[{ label: 'Configurações', to: '/configuracoes' }, { label: 'Regras tributárias' }]}
        createLabel="Nova regra"
        canCreate={can(ACCESS.taxRulesCreate)}
        onCreate={() => navigate('/configuracoes/regras-tributarias/nova')}
        error={loadError}
        onRetry={load}
        isLoading={isLoading}
        isEmpty={!isLoading && orderedRules.length === 0}
        emptyIcon={<ReceiptLongOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
        emptyTitle="Nenhuma regra tributária cadastrada ainda"
        emptyDescription="Cadastre regras por tributo e vigência para organizar a base fiscal da empresa."
        emptyAction={
          can(ACCESS.taxRulesCreate) ? (
            <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/configuracoes/regras-tributarias/nova')}>
              Cadastrar primeira regra
            </Button>
          ) : undefined
        }
      >
        <Stack spacing={1.5}>
          {orderedRules.map((rule) => (
            <Paper
              key={rule.uuid}
              variant="outlined"
              sx={{
                p: 2,
                ...ELEVATED_SURFACE_SX,
              }}
            >
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                <Box sx={{ minWidth: 0, flex: 1 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', mb: 0.75 }}>
                    <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 15, fontWeight: 700 }}>{TAX_TYPE_LABELS[rule.tax_type]}</Typography>
                    <ActiveChip isActive={rule.is_active} activeLabel="Ativa" inactiveLabel="Inativa" />
                    <Chip label={`${rule.rate_percent}%`} size="small" variant="outlined" />
                  </Stack>

                  <Stack spacing={0.5}>
                    {renderScope(rule).map((line) => (
                      <Typography key={line} sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                        {line}
                      </Typography>
                    ))}
                    <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                      Vigência: {rule.valid_from ? new Date(rule.valid_from).toLocaleDateString('pt-BR') : 'imediata'} até{' '}
                      {rule.valid_to ? new Date(rule.valid_to).toLocaleDateString('pt-BR') : 'sem data final'}
                    </Typography>
                  </Stack>
                </Box>

                <Stack direction="row" spacing={0.5} sx={{ alignSelf: { xs: 'flex-end', md: 'flex-start' } }}>
                  {can(ACCESS.taxRulesUpdate) && (
                    <Tooltip title="Editar regra" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Editar regra ${TAX_TYPE_LABELS[rule.tax_type]}`}
                        onClick={() => navigate(`/configuracoes/regras-tributarias/${rule.uuid}/editar`)}
                        sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-primary)' } }}
                      >
                        <EditOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                  {can(ACCESS.taxRulesDelete) && (
                    <Tooltip title="Excluir regra" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Excluir regra ${TAX_TYPE_LABELS[rule.tax_type]}`}
                        onClick={() => {
                          setDeleteError(null)
                          setDeleteTarget(rule)
                        }}
                        sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-danger)' } }}
                      >
                        <DeleteOutlineIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                </Stack>
              </Stack>
            </Paper>
          ))}
        </Stack>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir regra tributária"
        itemLabel={deleteTarget ? `${TAX_TYPE_LABELS[deleteTarget.tax_type]} ${deleteTarget.rate_percent}%` : null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
