import AddIcon from '@mui/icons-material/Add'
import ChecklistOutlinedIcon from '@mui/icons-material/ChecklistOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import RuleFolderOutlinedIcon from '@mui/icons-material/RuleFolderOutlined'
import { Alert, Box, Button, Chip, IconButton, LinearProgress, Paper, Stack, Tooltip, Typography } from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as fiscalOperationProfileService from '../../services/fiscalOperationProfileService'
import * as fiscalReadinessService from '../../services/fiscalReadinessService'
import { getApiErrorMessage } from '../../types/api'
import type { FiscalReadiness } from '../../types/fiscalReadiness'
import {
  FISCAL_DOCUMENT_TYPE_LABELS,
  FISCAL_OPERATION_NATURE_LABELS,
  type FiscalOperationProfile,
} from '../../types/fiscalOperationProfile'

function renderScope(profile: FiscalOperationProfile): string[] {
  if (!profile.scope) return ['Perfil geral']

  const entries: string[] = []
  if (profile.scope.order_origin?.length) entries.push(`Origens: ${profile.scope.order_origin.join(', ')}`)
  if (profile.scope.fulfillment_type?.length) entries.push(`Entrega: ${profile.scope.fulfillment_type.join(', ')}`)
  if (profile.scope.destination_type?.length) entries.push(`Destino: ${profile.scope.destination_type.join(', ')}`)
  return entries.length > 0 ? entries : ['Perfil geral']
}

export function FiscalOperationProfileListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()

  const [profiles, setProfiles] = useState<FiscalOperationProfile[]>([])
  const [readiness, setReadiness] = useState<FiscalReadiness | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<FiscalOperationProfile | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    Promise.all([
      fiscalOperationProfileService.listFiscalOperationProfiles(),
      fiscalReadinessService.getFiscalReadiness(),
    ])
      .then(([profileData, readinessData]) => {
        setProfiles(profileData)
        setReadiness(readinessData)
      })
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados fiscais agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const orderedProfiles = useMemo(
    () =>
      [...profiles].sort((a, b) => {
        if (a.is_active !== b.is_active) return a.is_active ? -1 : 1
        return a.name.localeCompare(b.name, 'pt-BR')
      }),
    [profiles],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)
    try {
      await fiscalOperationProfileService.deleteFiscalOperationProfile(deleteTarget.uuid)
      setDeleteTarget(null)
      load()
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível excluir este perfil fiscal agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <>
      <CrudListPage
        title="Perfis fiscais"
        subtitle="Defina como cada operação da empresa deve caminhar no módulo fiscal, com CFOP base e tipo de documento preferido."
        breadcrumbs={[{ label: 'Configurações', to: '/configuracoes' }, { label: 'Perfis fiscais' }]}
        createLabel="Novo perfil"
        canCreate={can(ACCESS.taxRulesCreate)}
        onCreate={() => navigate('/configuracoes/perfis-fiscais/novo')}
        error={loadError}
        onRetry={load}
        isLoading={isLoading}
        isEmpty={!isLoading && orderedProfiles.length === 0}
        emptyIcon={<RuleFolderOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
        emptyTitle="Nenhum perfil fiscal cadastrado ainda"
        emptyDescription="Cadastre perfis para separar venda, serviço, devolução e outras operações antes da emissão real."
        emptyAction={
          can(ACCESS.taxRulesCreate) ? (
            <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/configuracoes/perfis-fiscais/novo')}>
              Cadastrar primeiro perfil
            </Button>
          ) : undefined
        }
      >
        {readiness && (
          <Paper variant="outlined" sx={{ p: 2.25, mb: 2, ...ELEVATED_SURFACE_SX }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
              <Box sx={{ minWidth: 0, flex: 1 }}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
                  <ChecklistOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
                  <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 16, fontWeight: 700 }}>Prontidão fiscal da empresa</Typography>
                  <Chip
                    size="small"
                    color={readiness.status === 'ready' ? 'success' : 'warning'}
                    label={readiness.status === 'ready' ? 'Pronta' : 'Atenção'}
                  />
                </Stack>
                <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 1.5 }}>
                  Acompanhe o que ainda falta para a empresa sair do cadastro fiscal básico e chegar preparada para cálculo e emissão.
                </Typography>
                <LinearProgress
                  variant="determinate"
                  value={readiness.score_percent}
                  color={readiness.status === 'ready' ? 'success' : 'warning'}
                  sx={{ height: 8, borderRadius: 999, mb: 1 }}
                />
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                  {readiness.score_percent}% dos pré-requisitos fiscais concluídos.
                </Typography>
              </Box>
            </Stack>

            <Stack spacing={1} sx={{ mt: 2 }}>
              {readiness.checks.map((check) => (
                <Alert key={check.key} severity={check.status === 'ok' ? 'success' : 'warning'} variant="outlined">
                  <strong>{check.label}:</strong> {check.details}
                </Alert>
              ))}
            </Stack>
          </Paper>
        )}

        <Stack spacing={1.5}>
          {orderedProfiles.map((profile) => (
            <Paper key={profile.uuid} variant="outlined" sx={{ p: 2.1, ...ELEVATED_SURFACE_SX }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                <Box sx={{ minWidth: 0, flex: 1 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', mb: 0.75 }}>
                    <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 15, fontWeight: 700 }}>{profile.name}</Typography>
                    <ActiveChip isActive={profile.is_active} activeLabel="Ativo" inactiveLabel="Inativo" />
                    <Chip size="small" variant="outlined" label={FISCAL_OPERATION_NATURE_LABELS[profile.operation_nature]} />
                    <Chip size="small" variant="outlined" label={FISCAL_DOCUMENT_TYPE_LABELS[profile.document_type]} />
                    {profile.default_cfop ? <Chip size="small" variant="outlined" label={`CFOP ${profile.default_cfop}`} /> : null}
                  </Stack>

                  <Stack spacing={0.5}>
                    {renderScope(profile).map((line) => (
                      <Typography key={line} sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                        {line}
                      </Typography>
                    ))}
                    {profile.description ? (
                      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>{profile.description}</Typography>
                    ) : null}
                  </Stack>
                </Box>

                <Stack direction="row" spacing={0.5} sx={{ alignSelf: { xs: 'flex-end', md: 'flex-start' } }}>
                  {can(ACCESS.taxRulesUpdate) && (
                    <Tooltip title="Editar perfil" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Editar perfil ${profile.name}`}
                        onClick={() => navigate(`/configuracoes/perfis-fiscais/${profile.uuid}/editar`)}
                        sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-primary)' } }}
                      >
                        <EditOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                  {can(ACCESS.taxRulesDelete) && (
                    <Tooltip title="Excluir perfil" arrow>
                      <IconButton
                        size="small"
                        aria-label={`Excluir perfil ${profile.name}`}
                        onClick={() => {
                          setDeleteError(null)
                          setDeleteTarget(profile)
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
        title="Excluir perfil fiscal"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
