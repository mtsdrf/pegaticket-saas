import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import GavelOutlinedIcon from '@mui/icons-material/GavelOutlined'
import OpenInNewOutlinedIcon from '@mui/icons-material/OpenInNewOutlined'
import PrivacyTipOutlinedIcon from '@mui/icons-material/PrivacyTipOutlined'
import PlaylistAddCheckCircleOutlinedIcon from '@mui/icons-material/PlaylistAddCheckCircleOutlined'
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { type ReactNode, useEffect, useMemo, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { ACCESS } from '../../../access/requirements'
import { useAccessControl } from '../../../hooks/useAccessControl'
import * as legalDocumentService from '../../../services/legalDocumentService'
import * as privacyRequestService from '../../../services/privacyRequestService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import * as tenantProfileService from '../../../services/tenantProfileService'
import { CARD_EQUAL_HEIGHT_SX, FORM_GRID_3_SX, UI_SIZE } from '../../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../../types/api'
import type { LegalDocument } from '../../../types/legal'
import type {
  CreatePrivacyRequestPayload,
  PrivacyRequest,
  PrivacyRequestChannel,
  PrivacyRequesterRole,
  PrivacyRequestStatus,
  PrivacyRequestType,
} from '../../../types/privacy'

/**
 * Bloco "Dados e Privacidade" — "Exportar meus dados" (roadmap A1.2),
 * extraído de `TenantSettingsPage` (2026-07-24). Botão isolado, gated por
 * `tenant-profile,export`. Throttle dedicado no backend (3/hora) — 429
 * tratado com mensagem própria, distinta do erro genérico.
 */
export function DataPrivacyBlock() {
  const { can } = useAccessControl()
  const canReadPrivacyRequests = can(ACCESS.tenantProfileRead)
  const canManagePrivacyRequests = can(ACCESS.tenantProfileUpdate)
  const canExportTenantData = can(ACCESS.tenantProfileExport)
  const [isExporting, setIsExporting] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)
  const [privacyRequests, setPrivacyRequests] = useState<PrivacyRequest[]>([])
  const [isLoadingPrivacyRequests, setIsLoadingPrivacyRequests] = useState(true)
  const [privacyRequestError, setPrivacyRequestError] = useState<string | null>(null)
  const [privacyRequestSuccess, setPrivacyRequestSuccess] = useState<string | null>(null)
  const [isSubmittingPrivacyRequest, setIsSubmittingPrivacyRequest] = useState(false)
  const [updatingRequestUuid, setUpdatingRequestUuid] = useState<string | null>(null)
  const [form, setForm] = useState<CreatePrivacyRequestPayload>({
    requester_name: '',
    requester_email: '',
    requester_role: 'empresa',
    request_type: 'acesso',
    channel: 'atendimento_interno',
    subject: '',
    description: '',
  })
  const [legalDocuments, setLegalDocuments] = useState<{ terms: LegalDocument | null; privacy: LegalDocument | null }>({
    terms: null,
    privacy: null,
  })
  const [isLoadingDocuments, setIsLoadingDocuments] = useState(true)
  const recentPrivacyRequests = useMemo(() => privacyRequests.slice(0, 8), [privacyRequests])

  useEffect(() => {
    let cancelled = false

    setIsLoadingDocuments(true)
    Promise.allSettled([
      legalDocumentService.getLegalDocument('terms'),
      legalDocumentService.getLegalDocument('privacy'),
    ])
      .then(([termsResult, privacyResult]) => {
        if (cancelled) return
        setLegalDocuments({
          terms: termsResult.status === 'fulfilled' ? termsResult.value : null,
          privacy: privacyResult.status === 'fulfilled' ? privacyResult.value : null,
        })
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoadingDocuments(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!canReadPrivacyRequests) {
      setPrivacyRequests([])
      setPrivacyRequestError(null)
      setIsLoadingPrivacyRequests(false)
      return
    }

    void loadPrivacyRequests()
  }, [canReadPrivacyRequests])

  async function loadPrivacyRequests() {
    setIsLoadingPrivacyRequests(true)
    setPrivacyRequestError(null)
    try {
      setPrivacyRequests(await privacyRequestService.listPrivacyRequests())
    } catch (error) {
      setPrivacyRequestError(getApiErrorMessage(error, 'Não foi possível carregar as solicitações de privacidade agora.'))
    } finally {
      setIsLoadingPrivacyRequests(false)
    }
  }

  async function handleExport() {
    setExportError(null)
    setIsExporting(true)
    try {
      await tenantProfileService.exportTenantData()
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 429) {
        setExportError('Limite de exportações atingido (3 por hora). Aguarde um pouco e tente novamente.')
      } else {
        setExportError(getApiErrorMessage(error, 'Não foi possível exportar os dados agora.'))
      }
    } finally {
      setIsExporting(false)
    }
  }

  async function handleCreatePrivacyRequest() {
    setPrivacyRequestError(null)
    setPrivacyRequestSuccess(null)
    setIsSubmittingPrivacyRequest(true)
    try {
      await privacyRequestService.createPrivacyRequest({
        ...form,
        requester_email: form.requester_email?.trim() || null,
        channel: form.channel ?? null,
      })
      setPrivacyRequestSuccess('Solicitação registrada. Agora você já tem um histórico interno para acompanhar esse atendimento.')
      setForm({
        requester_name: '',
        requester_email: '',
        requester_role: 'empresa',
        request_type: 'acesso',
        channel: 'atendimento_interno',
        subject: '',
        description: '',
      })
      await loadPrivacyRequests()
    } catch (error) {
      setPrivacyRequestError(getApiErrorMessage(error, 'Não foi possível registrar a solicitação de privacidade agora.'))
    } finally {
      setIsSubmittingPrivacyRequest(false)
    }
  }

  async function handleQuickStatusUpdate(uuid: string, status: PrivacyRequestStatus) {
    setPrivacyRequestError(null)
    setPrivacyRequestSuccess(null)
    setUpdatingRequestUuid(uuid)
    try {
      await privacyRequestService.updatePrivacyRequest(uuid, {
        status,
        resolution_notes:
          status === 'completed'
            ? 'Solicitação concluída operacionalmente.'
            : status === 'rejected'
              ? 'Solicitação encerrada sem atendimento integral. Avalie retenção legal, fiscal, contábil, contratual ou necessidade de preservação de evidência operacional antes de negar exclusão total.'
              : null,
      })
      setPrivacyRequestSuccess('Status da solicitação atualizado com sucesso.')
      await loadPrivacyRequests()
    } catch (error) {
      setPrivacyRequestError(getApiErrorMessage(error, 'Não foi possível atualizar o status da solicitação agora.'))
    } finally {
      setUpdatingRequestUuid(null)
    }
  }

  return (
    <Stack spacing={2}>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
        Este espaço reúne o mínimo operacional de privacidade do PegaTicket para a sua empresa: documentos públicos,
        orientação de atendimento e exportação dos principais dados do ambiente.
      </Typography>

      <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 1.15fr) minmax(0, 0.85fr)' } }}>
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <PrivacyTipOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                Papel da plataforma
              </Typography>
            </Stack>

            <Alert severity="info" variant="outlined">
              A PegaTicket atua como <strong>controladora</strong> dos dados da conta e dos usuários de acesso da sua
              empresa, e como <strong>operadora</strong> dos dados que a sua empresa cadastra no sistema sobre clientes,
              pedidos e rotinas operacionais.
            </Alert>

            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Solicitações de acesso, correção ou exclusão devem ser tratadas primeiro pelos canais oficiais da sua
              empresa. Quando houver retenção legal, fiscal, contábil, contratual ou necessidade de auditoria, a
              remoção pode não ser imediata.
            </Typography>
          </Stack>
        </Paper>

        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <GavelOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                Documentos públicos
              </Typography>
            </Stack>

            {isLoadingDocuments ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
                <CircularProgress size={22} />
              </Box>
            ) : (
              <Stack spacing={1}>
                <DocumentLinkCard
                  title="Termos de Uso"
                  path="/termos"
                  version={legalDocuments.terms?.version ?? null}
                  publishedAt={legalDocuments.terms?.published_at ?? null}
                />
                <DocumentLinkCard
                  title="Política de Privacidade"
                  path="/privacidade"
                  version={legalDocuments.privacy?.version ?? null}
                  publishedAt={legalDocuments.privacy?.published_at ?? null}
                />
              </Stack>
            )}
          </Stack>
        </Paper>
      </Box>

      <Box sx={{ ...FORM_GRID_3_SX, gridTemplateColumns: { xs: '1fr', lg: 'repeat(3, minmax(0, 1fr))' } }}>
        <InfoCard
          icon={<TaskAltOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />}
          title="Atendimento mínimo"
          body="Valide quem está solicitando, registre o escopo do pedido e responda sempre pelos canais oficiais da operação."
        />
        <InfoCard
          icon={<DownloadOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />}
          title="Exportação já disponível"
          body="Os principais dados da empresa podem ser exportados em pacote único. Use essa base como primeiro passo em pedidos de acesso."
        />
        <InfoCard
          icon={<PrivacyTipOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />}
          title="Limites atuais"
          body="Anonimização ampla e automação completa de requisições de titulares ainda não são self-service no produto."
        />
      </Box>

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
        <Stack spacing={1.5}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
            Checklist operacional antes de ativar a empresa
          </Typography>

          <Stack spacing={1}>
            {[
              'Confirmar que Termos de Uso e Política de Privacidade estão acessíveis.',
              'Confirmar que o cadastro público exige os dois aceites obrigatórios.',
              'Garantir que a empresa consegue acessar este bloco de Dados e Privacidade.',
              'Orientar o responsável sobre exportação, correção e limites de exclusão.',
              'Tratar qualquer solicitação fora do fluxo atual como atendimento assistido.',
            ].map((item) => (
              <Stack key={item} direction="row" spacing={1.25} sx={{ alignItems: 'flex-start' }}>
                <TaskAltOutlinedIcon sx={{ color: 'var(--pt-success)', fontSize: 18, mt: '2px' }} />
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>{item}</Typography>
              </Stack>
            ))}
          </Stack>
        </Stack>
      </Paper>

      {exportError ? (
        <Alert severity="error">
          {exportError}
        </Alert>
      ) : null}

      {privacyRequestError ? <Alert severity="error">{privacyRequestError}</Alert> : null}
      {privacyRequestSuccess ? <Alert severity="success">{privacyRequestSuccess}</Alert> : null}

      <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', xl: 'minmax(0, 0.95fr) minmax(0, 1.05fr)' } }}>
        {canManagePrivacyRequests ? (
          <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
            <Stack spacing={1.5}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <PlaylistAddCheckCircleOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                  Registrar solicitação de privacidade
                </Typography>
              </Stack>

              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                Use este registro quando a empresa, um usuário interno ou um titular final solicitar acesso, correção, exclusão,
                anonimização ou oposição. Isso cria um histórico operacional mínimo dentro do sistema.
              </Typography>

              <Stack spacing={1.5}>
                <TextField
                  label="Nome do solicitante"
                  value={form.requester_name}
                  onChange={(event) => setForm((current) => ({ ...current, requester_name: event.target.value }))}
                  fullWidth
                />
                <TextField
                  label="E-mail do solicitante"
                  type="email"
                  value={form.requester_email ?? ''}
                  onChange={(event) => setForm((current) => ({ ...current, requester_email: event.target.value }))}
                  fullWidth
                />
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                  <TextField
                    select
                    label="Quem está solicitando?"
                    value={form.requester_role}
                    onChange={(event) => setForm((current) => ({ ...current, requester_role: event.target.value as PrivacyRequesterRole }))}
                    fullWidth
                  >
                    <MenuItem value="empresa">Empresa contratante</MenuItem>
                    <MenuItem value="usuario_interno">Usuário interno</MenuItem>
                    <MenuItem value="titular_final">Titular final cadastrado</MenuItem>
                    <MenuItem value="outro">Outro</MenuItem>
                  </TextField>
                  <TextField
                    select
                    label="Tipo da solicitação"
                    value={form.request_type}
                    onChange={(event) => setForm((current) => ({ ...current, request_type: event.target.value as PrivacyRequestType }))}
                    fullWidth
                  >
                    <MenuItem value="acesso">Acesso / exportação</MenuItem>
                    <MenuItem value="correcao">Correção cadastral</MenuItem>
                    <MenuItem value="exclusao">Exclusão</MenuItem>
                    <MenuItem value="anonimizacao">Anonimização</MenuItem>
                    <MenuItem value="oposicao">Oposição / contestação</MenuItem>
                    <MenuItem value="outro">Outro</MenuItem>
                  </TextField>
                </Stack>
                <TextField
                  select
                  label="Canal de entrada"
                  value={form.channel ?? ''}
                  onChange={(event) => setForm((current) => ({ ...current, channel: event.target.value as PrivacyRequestChannel }))}
                  fullWidth
                >
                  <MenuItem value="email">E-mail</MenuItem>
                  <MenuItem value="whatsapp">WhatsApp</MenuItem>
                  <MenuItem value="telefone">Telefone</MenuItem>
                  <MenuItem value="atendimento_interno">Atendimento interno</MenuItem>
                  <MenuItem value="outro">Outro</MenuItem>
                </TextField>
                <TextField
                  label="Assunto"
                  value={form.subject}
                  onChange={(event) => setForm((current) => ({ ...current, subject: event.target.value }))}
                  fullWidth
                />
                <TextField
                  label="Descrição do pedido"
                  value={form.description}
                  onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                  fullWidth
                  multiline
                  minRows={4}
                />
              </Stack>

              <Button
                variant="contained"
                onClick={() => void handleCreatePrivacyRequest()}
                disabled={
                  isSubmittingPrivacyRequest
                  || !form.requester_name.trim()
                  || !form.subject.trim()
                  || !form.description.trim()
                }
                sx={{ alignSelf: 'flex-start', minHeight: UI_SIZE.control }}
              >
                {isSubmittingPrivacyRequest ? 'Registrando…' : 'Registrar solicitação'}
              </Button>
            </Stack>
          </Paper>
        ) : (
          <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
            <Stack spacing={1.5}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <PlaylistAddCheckCircleOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                  Registrar solicitação de privacidade
                </Typography>
              </Stack>

              <Alert severity="info" variant="outlined">
                Seu perfil atual pode consultar este guia, mas não pode registrar ou alterar solicitações de privacidade nesta empresa.
              </Alert>
            </Stack>
          </Paper>
        )}

        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <PrivacyTipOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                Solicitações recentes
              </Typography>
            </Stack>

            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Este quadro ajuda a empresa a acompanhar o que já foi recebido, o que ainda está em análise e o que já foi concluído no fluxo assistido.
            </Typography>

            {!canReadPrivacyRequests ? (
              <Alert severity="info" variant="outlined">
                Seu perfil atual não pode consultar o histórico de solicitações de privacidade desta empresa.
              </Alert>
            ) : isLoadingPrivacyRequests ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress size={22} />
              </Box>
            ) : recentPrivacyRequests.length === 0 ? (
              <Alert severity="info" variant="outlined">
                Nenhuma solicitação registrada ainda. Quando houver um pedido real, registre aqui para manter rastreabilidade mínima.
              </Alert>
            ) : (
              <Stack spacing={1.25}>
                {recentPrivacyRequests.map((request) => (
                  <Paper
                    key={request.uuid}
                    variant="outlined"
                    sx={{ p: 1.5, borderColor: 'var(--pt-border)', background: 'var(--pt-surface)' }}
                  >
                    <Stack spacing={1.1}>
                      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { xs: 'flex-start', sm: 'center' } }}>
                        <Box sx={{ minWidth: 0 }}>
                          <Typography sx={{ fontSize: 13.5, fontWeight: 700 }}>{request.subject}</Typography>
                          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                            {request.requester_name}
                            {request.requester_email ? ` · ${request.requester_email}` : ''}
                          </Typography>
                        </Box>
                        <Chip
                          size="small"
                          color={PRIVACY_STATUS_META[request.status].color}
                          label={PRIVACY_STATUS_META[request.status].label}
                        />
                      </Stack>

                      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>
                        {request.description}
                      </Typography>

                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                        {PRIVACY_REQUEST_TYPE_LABELS[request.request_type]} · {PRIVACY_REQUEST_ROLE_LABELS[request.requester_role]}
                        {request.channel ? ` · ${PRIVACY_REQUEST_CHANNEL_LABELS[request.channel]}` : ''}
                        {request.requested_at ? ` · registrado em ${new Date(request.requested_at).toLocaleString('pt-BR')}` : ''}
                      </Typography>

                      {request.resolution_notes ? (
                        <Alert severity={request.status === 'rejected' ? 'warning' : 'info'} variant="outlined">
                          {request.resolution_notes}
                        </Alert>
                      ) : null}

                      {request.status === 'rejected' ? (
                        <Alert severity="warning" variant="outlined">
                          Quando uma solicitação não puder ser atendida integralmente, a resposta da empresa deve explicar de forma clara se existe retenção legal, fiscal, contábil, contratual ou necessidade de manter evidências operacionais e de auditoria.
                        </Alert>
                      ) : null}

                      <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                        {canManagePrivacyRequests && request.status !== 'in_progress' ? (
                          <Button
                            size="small"
                            variant="outlined"
                            disabled={updatingRequestUuid === request.uuid}
                            onClick={() => void handleQuickStatusUpdate(request.uuid, 'in_progress')}
                          >
                            Marcar em análise
                          </Button>
                        ) : null}
                        {canManagePrivacyRequests && request.status !== 'completed' ? (
                          <Button
                            size="small"
                            variant="outlined"
                            color="success"
                            disabled={updatingRequestUuid === request.uuid}
                            onClick={() => void handleQuickStatusUpdate(request.uuid, 'completed')}
                          >
                            Marcar concluída
                          </Button>
                        ) : null}
                        {canManagePrivacyRequests && request.status !== 'rejected' ? (
                          <Button
                            size="small"
                            variant="outlined"
                            color="warning"
                            disabled={updatingRequestUuid === request.uuid}
                            onClick={() => void handleQuickStatusUpdate(request.uuid, 'rejected')}
                          >
                            Encerrar sem atendimento integral
                          </Button>
                        ) : null}
                      </Stack>
                    </Stack>
                  </Paper>
                ))}
              </Stack>
            )}
          </Stack>
        </Paper>
      </Box>

      {canExportTenantData ? (
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Stack spacing={1.5}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
              Exportar dados da empresa
            </Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Baixe um pacote único (.zip) com clientes, produtos e pedidos da sua empresa em CSV. Limite de 3
              exportações por hora.
            </Typography>

            <Button
              variant="outlined"
              startIcon={<DownloadOutlinedIcon />}
              onClick={() => void handleExport()}
              disabled={isExporting}
              sx={{ minHeight: UI_SIZE.control, alignSelf: 'flex-start' }}
            >
              {isExporting ? 'Exportando…' : 'Exportar meus dados'}
            </Button>
          </Stack>
        </Paper>
      ) : null}
    </Stack>
  )
}

const PRIVACY_REQUEST_ROLE_LABELS: Record<PrivacyRequesterRole, string> = {
  empresa: 'Empresa contratante',
  usuario_interno: 'Usuário interno',
  titular_final: 'Titular final',
  outro: 'Outro',
}

const PRIVACY_REQUEST_TYPE_LABELS: Record<PrivacyRequestType, string> = {
  acesso: 'Acesso / exportação',
  correcao: 'Correção',
  exclusao: 'Exclusão',
  anonimizacao: 'Anonimização',
  oposicao: 'Oposição',
  outro: 'Outro',
}

const PRIVACY_REQUEST_CHANNEL_LABELS: Record<PrivacyRequestChannel, string> = {
  email: 'E-mail',
  whatsapp: 'WhatsApp',
  telefone: 'Telefone',
  atendimento_interno: 'Atendimento interno',
  outro: 'Outro',
}

const PRIVACY_STATUS_META: Record<PrivacyRequestStatus, { label: string; color: 'default' | 'info' | 'success' | 'warning' }> = {
  open: { label: 'Aberta', color: 'warning' },
  in_progress: { label: 'Em análise', color: 'info' },
  completed: { label: 'Concluída', color: 'success' },
  rejected: { label: 'Encerrada', color: 'default' },
}

interface DocumentLinkCardProps {
  title: string
  path: string
  version: string | null
  publishedAt: string | null
}

function DocumentLinkCard({ title, path, version, publishedAt }: DocumentLinkCardProps) {
  return (
    <Paper
      variant="outlined"
      sx={{
        p: 1.5,
        borderColor: 'var(--pt-border)',
        background: 'var(--pt-surface)',
      }}
    >
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontSize: 13.5, fontWeight: 700 }}>{title}</Typography>
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
            {version ? `Versão ${version}` : 'Documento não disponível'}
            {publishedAt ? ` · publicado em ${new Date(publishedAt).toLocaleDateString('pt-BR')}` : ''}
          </Typography>
        </Box>

        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexShrink: 0 }}>
          {version ? <Chip size="small" color="success" label="Publicado" /> : <Chip size="small" color="warning" label="Verificar" />}
          <Button
            component={RouterLink}
            to={path}
            target="_blank"
            rel="noreferrer"
            size="small"
            endIcon={<OpenInNewOutlinedIcon fontSize="small" />}
          >
            Abrir
          </Button>
        </Stack>
      </Stack>
    </Paper>
  )
}

interface InfoCardProps {
  icon: ReactNode
  title: string
  body: string
}

function InfoCard({ icon, title, body }: InfoCardProps) {
  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25, ...CARD_EQUAL_HEIGHT_SX }}>
      <Stack spacing={1.25}>
        <Box>{icon}</Box>
        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
          {title}
        </Typography>
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
          {body}
        </Typography>
      </Stack>
    </Paper>
  )
}
