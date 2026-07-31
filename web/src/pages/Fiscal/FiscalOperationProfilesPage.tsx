import AssignmentTurnedInOutlinedIcon from '@mui/icons-material/AssignmentTurnedInOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineOutlined'
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
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
import { useNavigate } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { getFiscalReadiness } from '../../services/fiscalReadinessService'
import {
  deleteFiscalOperationProfile,
  listFiscalOperationProfiles,
} from '../../services/fiscalOperationProfileService'
import { CONTENT_CONTAINER_SX, SECTION_CARD_PADDING_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { FiscalReadinessCheck, FiscalReadinessStatus } from '../../types/fiscalReadiness'
import type { FiscalOperationProfile } from '../../types/fiscalOperationProfile'
import { FISCAL_DOCUMENT_TYPE_LABELS } from '../../types/fiscalOperationProfile'

function readinessVisual(status: FiscalReadinessStatus) {
  if (status === 'ok') {
    return { icon: TaskAltOutlinedIcon, color: 'success.main', chip: 'success' as const, label: 'Pronto' }
  }

  if (status === 'warning' || status === 'attention') {
    return { icon: WarningAmberOutlinedIcon, color: 'warning.main', chip: 'warning' as const, label: 'Atenção' }
  }

  return { icon: ErrorOutlineIcon, color: 'error.main', chip: 'error' as const, label: 'Bloqueio' }
}

function renderScope(profile: FiscalOperationProfile): string[] {
  const scope = profile.scope ?? {}
  const items: string[] = []

  if (scope.order_origin?.length) items.push(`Origem: ${scope.order_origin.join(', ')}`)
  if (scope.fulfillment_type?.length) items.push(`Atendimento: ${scope.fulfillment_type.join(', ')}`)
  if (scope.destination_type?.length) items.push(`Destino: ${scope.destination_type.join(', ')}`)

  return items
}

function ReadinessCheckRow({ check }: { check: FiscalReadinessCheck }) {
  const visual = readinessVisual(check.status)
  const Icon = visual.icon

  return (
    <Paper variant="outlined" sx={{ ...SECTION_CARD_PADDING_SX, ...SOFT_PANEL_SX }}>
      <Stack direction="row" spacing={1.5} sx={{ alignItems: 'flex-start' }}>
        <Icon sx={{ color: visual.color, mt: 0.25 }} />
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontWeight: 700 }}>{check.label}</Typography>
          <Typography sx={{ color: 'var(--pt-muted)' }}>{check.details}</Typography>
        </Box>
      </Stack>
    </Paper>
  )
}

export function FiscalOperationProfilesPage() {
  const navigate = useNavigate()
  const [profiles, setProfiles] = useState<FiscalOperationProfile[]>([])
  const [readinessScore, setReadinessScore] = useState<number | null>(null)
  const [readinessStatus, setReadinessStatus] = useState<FiscalReadinessStatus>('attention')
  const [readinessChecks, setReadinessChecks] = useState<FiscalReadinessCheck[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<FiscalOperationProfile | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    let cancelled = false

    setIsLoading(true)
    setLoadError(null)

    Promise.all([listFiscalOperationProfiles(), getFiscalReadiness()])
      .then(([profileData, readiness]) => {
        if (cancelled) return
        setProfiles(profileData)
        setReadinessScore(readiness.score_percent)
        setReadinessStatus(readiness.status)
        setReadinessChecks(readiness.checks)
      })
      .catch((error) => {
        if (cancelled) return
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os perfis fiscais agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const sortedProfiles = useMemo(
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
      await deleteFiscalOperationProfile(deleteTarget.uuid)
      setProfiles((current) => current.filter((profile) => profile.uuid !== deleteTarget.uuid))
      setDeleteTarget(null)
    } catch (error) {
      setDeleteError(getApiErrorMessage(error, 'Não foi possível excluir o perfil fiscal agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const visual = readinessVisual(readinessStatus)
  const ReadinessIcon = visual.icon

  return (
    <Box sx={CONTENT_CONTAINER_SX}>
      <PageHeader
        title="Perfis fiscais"
        subtitle="Organize a prontidão da empresa e os perfis operacionais que orientam a emissão futura por tipo de documento."
        breadcrumbs={[{ label: 'Configurações', to: '/configuracoes' }, { label: 'Perfis fiscais' }]}
      />

      <Paper variant="outlined" sx={{ ...SECTION_CARD_PADDING_SX, ...ELEVATED_SURFACE_SX }}>
        {isLoading ? (
          <Stack sx={{ minHeight: 260, alignItems: 'center', justifyContent: 'center' }}>
            <CircularProgress size={28} />
          </Stack>
        ) : loadError ? (
          <Alert severity="error">{loadError}</Alert>
        ) : (
          <Stack spacing={2.5}>
            <Paper variant="outlined" sx={{ ...SECTION_CARD_PADDING_SX, ...SOFT_PANEL_SX }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
                <Box sx={{ minWidth: 0 }}>
                  <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center' }}>
                    <ReadinessIcon sx={{ color: visual.color }} />
                    <Typography sx={{ fontSize: 18, fontWeight: 700 }}>Prontidão fiscal da empresa</Typography>
                    <Chip size="small" color={visual.chip} label={visual.label} />
                  </Stack>
                  <Typography sx={{ mt: 1, fontSize: 28, fontWeight: 800 }}>
                    {readinessScore ?? 0}%
                  </Typography>
                  <Typography sx={{ mt: 0.75, color: 'var(--pt-muted)' }}>
                    {readinessScore ?? 0}% dos pré-requisitos fiscais concluídos.
                  </Typography>
                </Box>
              </Stack>
            </Paper>

            <Stack spacing={1.5}>
              {readinessChecks.map((check) => (
                <ReadinessCheckRow key={check.key} check={check} />
              ))}
            </Stack>

            {sortedProfiles.length === 0 ? (
              <EmptyState
                icon={<AssignmentTurnedInOutlinedIcon sx={{ fontSize: 34 }} />}
                title="Nenhum perfil fiscal cadastrado ainda"
                description="Crie perfis por natureza de operação para organizar a preparação fiscal antes da emissão real."
              />
            ) : (
              <Stack spacing={2}>
                {sortedProfiles.map((profile) => {
                  const scopeItems = renderScope(profile)

                  return (
                    <Paper
                      key={profile.uuid}
                      variant="outlined"
                      sx={{ ...SECTION_CARD_PADDING_SX, ...SOFT_PANEL_SX }}
                    >
                      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
                        <Box sx={{ minWidth: 0 }}>
                          <Stack direction="row" spacing={1} useFlexGap sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                            <Typography sx={{ fontSize: 18, fontWeight: 700 }}>{profile.name}</Typography>
                            <Chip
                              size="small"
                              label={profile.is_active ? 'Ativo' : 'Inativo'}
                              color={profile.is_active ? 'success' : 'default'}
                            />
                            <Chip
                              size="small"
                              variant="outlined"
                              label={FISCAL_DOCUMENT_TYPE_LABELS[profile.document_type] ?? profile.document_type.toUpperCase()}
                            />
                          </Stack>

                          <Typography sx={{ mt: 1, color: 'var(--pt-muted)' }}>
                            {profile.description || 'Sem descrição adicional.'}
                          </Typography>

                          <Typography sx={{ mt: 1.25, fontWeight: 700 }}>
                            CFOP {profile.default_cfop ?? 'não definido'}
                          </Typography>

                          {scopeItems.length > 0 ? (
                            <Stack direction="row" spacing={1} useFlexGap sx={{ mt: 1.5, flexWrap: 'wrap' }}>
                              {scopeItems.map((item) => (
                                <Chip key={item} size="small" label={item} />
                              ))}
                            </Stack>
                          ) : null}
                        </Box>

                        <Stack direction="row" spacing={1} sx={{ alignSelf: { xs: 'flex-start', sm: 'flex-start' } }}>
                          <Tooltip title={`Editar perfil ${profile.name}`} arrow>
                            <IconButton
                              aria-label={`Editar perfil ${profile.name}`}
                              color="primary"
                              onClick={() => navigate(`/configuracoes/perfis-fiscais/${profile.uuid}/editar`)}
                            >
                              <EditOutlinedIcon />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={`Excluir perfil ${profile.name}`} arrow>
                            <IconButton
                              aria-label={`Excluir perfil ${profile.name}`}
                              color="error"
                              onClick={() => {
                                setDeleteError(null)
                                setDeleteTarget(profile)
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
          </Stack>
        )}
      </Paper>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir perfil fiscal"
        itemLabel={deleteTarget?.name ?? null}
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
