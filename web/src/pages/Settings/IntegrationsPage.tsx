import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PlayArrowOutlinedIcon from '@mui/icons-material/PlayArrowOutlined'
import RefreshOutlinedIcon from '@mui/icons-material/RefreshOutlined'
import VpnKeyOutlinedIcon from '@mui/icons-material/VpnKeyOutlined'
import WebhookOutlinedIcon from '@mui/icons-material/WebhookOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Divider,
  FormControlLabel,
  FormGroup,
  IconButton,
  MenuItem,
  Paper,
  Skeleton,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { PasswordField } from '../../components/form/PasswordField'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAccessControl } from '../../hooks/useAccessControl'
import { ACCESS } from '../../access/requirements'
import { FORM_FIELD_SURFACE_SX } from '../../styles/formFieldStyles'
import { CARD_EQUAL_HEIGHT_SX, CLAMP_TEXT_3_SX, FORM_GRID_2_SX, PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import * as apiKeyService from '../../services/apiKeyService'
import * as marketplaceService from '../../services/marketplaceService'
import * as webhookSubscriptionService from '../../services/webhookSubscriptionService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { ApiKey } from '../../types/apiKey'
import type {
  MarketplaceCatalogPreview,
  MarketplaceCatalogSync,
  MarketplaceEvent,
  MarketplaceIntegration,
  MarketplaceMerchantStatusSnapshot,
  MarketplaceOperationsSummary,
  MarketplaceOrder,
} from '../../types/marketplace'
import {
  WEBHOOK_EVENT_LABELS,
  WEBHOOK_EVENT_TYPES,
  type WebhookEventType,
  type WebhookSubscription,
} from '../../types/webhookSubscription'

const SECTION_SX = {
  p: { xs: 2, sm: 3 },
  ...ELEVATED_SURFACE_SX,
  ...FORM_FIELD_SURFACE_SX,
} as const

const COMPANY_INTEGRATION_PARAMETERS = [
  {
    title: 'Recebimento da empresa',
    description: 'Configure a forma de recebimento e a chave Pix da empresa para fluxos fora do gateway da plataforma.',
    to: '/configuracoes/pagamento',
    action: 'Abrir pagamento',
  },
  {
    title: 'Dados da empresa',
    description: 'Mantenha nome, logo e dados fiscais da empresa atualizados para integrações e operação.',
    to: '/configuracoes/empresa',
    action: 'Abrir empresa',
  },
  {
    title: 'Loja online',
    description: 'Defina taxas, promoções, cupons e demais parâmetros operacionais que impactam integrações comerciais.',
    to: '/configuracoes/loja-online',
    action: 'Abrir loja online',
  },
  {
    title: 'Contabilidade',
    description: 'Gerencie os acessos de escritórios contábeis sem precisar trocar dados por fora do sistema.',
    to: '/configuracoes/contadores',
    action: 'Abrir contabilidade',
  },
  {
    title: 'Regras tributárias',
    description: 'Cadastre alíquotas, CFOP, NCM e vigências fiscais da empresa em um único lugar.',
    to: '/configuracoes/regras-tributarias',
    action: 'Abrir regras tributárias',
  },
] as const

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleString('pt-BR')
}

/** Painel "mostrado uma única vez" — valor em texto puro some ao fechar/recarregar, nunca é recuperável depois. */
function OneTimeSecretPanel({
  label,
  value,
  helperText,
  onDismiss,
}: {
  label: string
  value: string
  helperText: string
  onDismiss: () => void
}) {
  const [copied, setCopied] = useState(false)

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      setCopied(false)
    }
  }

  return (
    <Alert
      severity="warning"
      variant="outlined"
      sx={{ mb: 2.5 }}
      action={
        <Button color="inherit" size="small" onClick={onDismiss}>
          Entendi
        </Button>
      }
    >
      <Typography sx={{ fontWeight: 600, mb: 0.5 }}>{label} gerado(a) com sucesso</Typography>
      <Typography sx={{ fontSize: 13, mb: 1.5 }}>{helperText}</Typography>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
        <TextField
          value={value}
          size="small"
          fullWidth
          slotProps={{ input: { readOnly: true, sx: { fontFamily: 'monospace', fontSize: 13 } } }}
        />
        <Button
          onClick={() => void handleCopy()}
          variant="outlined"
          size="small"
          startIcon={copied ? <CheckCircleOutlineIcon fontSize="small" /> : <ContentCopyOutlinedIcon fontSize="small" />}
          sx={{ whiteSpace: 'nowrap', minHeight: 44 }}
        >
          {copied ? 'Copiado' : 'Copiar'}
        </Button>
      </Stack>
    </Alert>
  )
}

function CompanyParametersSection() {
  return (
    <Paper variant="outlined" sx={{ ...SECTION_SX, mb: 3 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <CheckCircleOutlineIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Parâmetros da empresa</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Tudo o que a sua empresa precisa nos passar para operar integrações externas deve ser configurado dentro do
        próprio sistema. Use os atalhos abaixo para manter esses cadastros atualizados sem depender de suporte.
      </Typography>

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'repeat(2, minmax(0, 1fr))' } }}>
        {COMPANY_INTEGRATION_PARAMETERS.map((item) => (
          <Paper
            key={item.to}
            variant="outlined"
            sx={{
              p: { xs: 1.75, sm: 2 },
              ...SOFT_PANEL_SX,
              display: 'flex',
              flexDirection: 'column',
              gap: 1,
              ...CARD_EQUAL_HEIGHT_SX,
            }}
          >
            <Typography sx={{ fontSize: 14.5, fontWeight: 700 }}>{item.title}</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', flex: 1, ...CLAMP_TEXT_3_SX }}>{item.description}</Typography>
            <Box>
              <Button component={RouterLink} to={item.to} variant="outlined" size="small" sx={{ minHeight: UI_SIZE.control }}>
                {item.action}
              </Button>
            </Box>
          </Paper>
        ))}
      </Box>
    </Paper>
  )
}

function MarketplaceSection() {
  const { can } = useAccessControl()
  const canCreate = can(ACCESS.apiAccessCreate)
  const canUpdate = can(ACCESS.apiAccessUpdate)

  const [integration, setIntegration] = useState<MarketplaceIntegration | null>(null)
  const [events, setEvents] = useState<MarketplaceEvent[]>([])
  const [orders, setOrders] = useState<MarketplaceOrder[]>([])
  const [operationsSummary, setOperationsSummary] = useState<MarketplaceOperationsSummary | null>(null)
  const [catalogPreview, setCatalogPreview] = useState<MarketplaceCatalogPreview | null>(null)
  const [catalogSyncs, setCatalogSyncs] = useState<MarketplaceCatalogSync[]>([])
  const [merchantStatus, setMerchantStatus] = useState<MarketplaceMerchantStatusSnapshot | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isSyncing, setIsSyncing] = useState(false)
  const [isPolling, setIsPolling] = useState(false)
  const [isSyncingCatalog, setIsSyncingCatalog] = useState(false)
  const [isRefreshingMerchantStatus, setIsRefreshingMerchantStatus] = useState(false)
  const [isCreatingInterruption, setIsCreatingInterruption] = useState(false)
  const [isSyncingOpeningHours, setIsSyncingOpeningHours] = useState(false)
  const [copiedWebhook, setCopiedWebhook] = useState(false)
  const [importingOrderUuid, setImportingOrderUuid] = useState<string | null>(null)
  const [retryingEventUuid, setRetryingEventUuid] = useState<string | null>(null)
  const [refreshingCatalogSyncUuid, setRefreshingCatalogSyncUuid] = useState<string | null>(null)
  const [removingInterruptionId, setRemovingInterruptionId] = useState<string | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [healthSummary, setHealthSummary] = useState<string | null>(null)

  const [name, setName] = useState('iFood')
  const [environment, setEnvironment] = useState<'sandbox' | 'production'>('sandbox')
  const [isActive, setIsActive] = useState(true)
  const [clientId, setClientId] = useState('')
  const [clientSecret, setClientSecret] = useState('')
  const [authorizationCode, setAuthorizationCode] = useState('')
  const [merchantId, setMerchantId] = useState('')
  const [webhookUrl, setWebhookUrl] = useState('')
  const [pollingMerchantIds, setPollingMerchantIds] = useState('')
  const [interruptionDescription, setInterruptionDescription] = useState('Pausa operacional temporária')
  const [interruptionDuration, setInterruptionDuration] = useState('30')

  function hydrate(current: MarketplaceIntegration | null) {
    setIntegration(current)
    setName(current?.name ?? 'iFood')
    setEnvironment((current?.environment as 'sandbox' | 'production' | undefined) ?? 'sandbox')
    setIsActive(current?.is_active ?? true)
    setClientId(current?.client_id ?? '')
    setClientSecret('')
    setAuthorizationCode('')
    setMerchantId(current?.merchant_id ?? '')
    setWebhookUrl(current?.webhook_url ?? '')
    setPollingMerchantIds(current?.polling_merchant_ids ?? '')
  }

  async function load() {
    setIsLoading(true)
    setLoadError(null)

    try {
      const list = await marketplaceService.listMarketplaceIntegrations()
      const ifood = list.find((item) => item.provider === 'ifood') ?? null
      hydrate(ifood)

      if (ifood) {
        const [loadedEvents, loadedOrders, loadedSummary, loadedPreview, loadedSyncs, loadedMerchantStatus] = await Promise.all([
          marketplaceService.listMarketplaceEvents(ifood.uuid),
          marketplaceService.listMarketplaceOrders(ifood.uuid),
          marketplaceService.getMarketplaceOperationsSummary(ifood.uuid),
          marketplaceService.getMarketplaceCatalogPreview(ifood.uuid).catch(() => null),
          marketplaceService.listMarketplaceCatalogSyncs(ifood.uuid).catch(() => []),
          marketplaceService.getMarketplaceMerchantStatus(ifood.uuid).catch(() => null),
        ])
        setEvents(loadedEvents)
        setOrders(loadedOrders)
        setOperationsSummary(loadedSummary)
        setCatalogPreview(loadedPreview)
        setCatalogSyncs(loadedSyncs)
        setMerchantStatus(loadedMerchantStatus)
      } else {
        setEvents([])
        setOrders([])
        setOperationsSummary(null)
        setCatalogPreview(null)
        setCatalogSyncs([])
        setMerchantStatus(null)
      }
    } catch (error) {
      setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a integração do iFood agora.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleSubmit() {
    if (!(integration ? canUpdate : canCreate)) return

    setIsSaving(true)
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})

    const payload = {
      provider: 'ifood' as const,
      name: name.trim() || 'iFood',
      environment,
      is_active: isActive,
      client_id: clientId.trim() || undefined,
      client_secret: clientSecret.trim() || undefined,
      authorization_code: authorizationCode.trim() || undefined,
      merchant_id: merchantId.trim() || undefined,
      webhook_url: webhookUrl.trim() || undefined,
      polling_merchant_ids: pollingMerchantIds.trim() || undefined,
    }

    try {
      const saved = integration
        ? await marketplaceService.updateMarketplaceIntegration(integration.uuid, payload)
        : await marketplaceService.createMarketplaceIntegration(payload)

      hydrate(saved)
      setSuccessMessage(integration ? 'Integração do iFood atualizada com sucesso.' : 'Integração do iFood criada com sucesso.')
      if (!integration) {
        setEvents([])
        setOrders([])
        setMerchantStatus(null)
      }
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a integração do iFood agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSaving(false)
    }
  }

  async function handleSyncMerchants() {
    if (!integration || !canUpdate) return
    setIsSyncing(true)
    setFormError(null)
    setSuccessMessage(null)

    try {
      const updated = await marketplaceService.syncMarketplaceMerchants(integration.uuid)
      hydrate(updated)
      setSuccessMessage('Lojas do iFood sincronizadas com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível sincronizar as lojas do iFood agora.'))
    } finally {
      setIsSyncing(false)
    }
  }

  async function handlePoll() {
    if (!integration || !canUpdate) return
    setIsPolling(true)
    setFormError(null)
    setSuccessMessage(null)

    try {
      const result = await marketplaceService.pollMarketplaceIntegration(integration.uuid)
      hydrate(result.integration)
      const [loadedEvents, loadedOrders, loadedSummary, loadedSyncs] = await Promise.all([
        marketplaceService.listMarketplaceEvents(integration.uuid),
        marketplaceService.listMarketplaceOrders(integration.uuid),
        marketplaceService.getMarketplaceOperationsSummary(integration.uuid),
        marketplaceService.listMarketplaceCatalogSyncs(integration.uuid).catch(() => []),
      ])
      setEvents(loadedEvents)
      setOrders(loadedOrders)
      setOperationsSummary(loadedSummary)
      setCatalogSyncs(loadedSyncs)
      setSuccessMessage(`Polling concluído: ${result.processed} evento(s) processado(s) e ${result.acknowledged} acknowledgment(s).`)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível executar o polling do iFood agora.'))
    } finally {
      setIsPolling(false)
    }
  }

  async function handleHealthCheck() {
    if (!integration) return
    setHealthSummary(null)
    setFormError(null)

    try {
      const result = await marketplaceService.checkMarketplaceHealth(integration.uuid)
      setHealthSummary(`Conexão válida. ${result.merchant_count} loja(s) visível(is) para esta credencial.`)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível validar a credencial do iFood agora.'))
    }
  }

  async function handleOrderAction(orderUuid: string, action: 'confirm' | 'startPreparation' | 'readyToPickup' | 'dispatch') {
    if (!canUpdate) return

    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.performMarketplaceOrderAction(orderUuid, { action })
      setSuccessMessage('Ação enviada ao iFood com sucesso.')
      if (integration) {
        const [loadedOrders, loadedSummary, loadedSyncs] = await Promise.all([
          marketplaceService.listMarketplaceOrders(integration.uuid),
          marketplaceService.getMarketplaceOperationsSummary(integration.uuid),
          marketplaceService.listMarketplaceCatalogSyncs(integration.uuid).catch(() => []),
        ])
        setOrders(loadedOrders)
        setOperationsSummary(loadedSummary)
        setCatalogSyncs(loadedSyncs)
      }
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível enviar a ação para o iFood agora.'))
    }
  }

  async function handleImportOrder(orderUuid: string) {
    if (!canUpdate || !integration) return

    setImportingOrderUuid(orderUuid)
    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.importMarketplaceOrder(orderUuid)
      const [loadedOrders, loadedSummary, loadedSyncs] = await Promise.all([
        marketplaceService.listMarketplaceOrders(integration.uuid),
        marketplaceService.getMarketplaceOperationsSummary(integration.uuid),
        marketplaceService.listMarketplaceCatalogSyncs(integration.uuid).catch(() => []),
      ])
      setOrders(loadedOrders)
      setOperationsSummary(loadedSummary)
      setCatalogSyncs(loadedSyncs)
      setSuccessMessage('Pedido externo importado para o fluxo interno com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível importar este pedido externo agora.'))
    } finally {
      setImportingOrderUuid(null)
    }
  }

  async function handleCopyWebhookUrl() {
    if (!integration?.generated_webhook_url) return

    try {
      await navigator.clipboard.writeText(integration.generated_webhook_url)
      setCopiedWebhook(true)
      setTimeout(() => setCopiedWebhook(false), 2000)
    } catch {
      setCopiedWebhook(false)
    }
  }

  async function handleRetryEvent(eventUuid: string) {
    if (!integration || !canUpdate) return

    setRetryingEventUuid(eventUuid)
    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.retryMarketplaceEvent(eventUuid)
      const [loadedEvents, loadedOrders, loadedSummary, loadedSyncs] = await Promise.all([
        marketplaceService.listMarketplaceEvents(integration.uuid),
        marketplaceService.listMarketplaceOrders(integration.uuid),
        marketplaceService.getMarketplaceOperationsSummary(integration.uuid),
        marketplaceService.listMarketplaceCatalogSyncs(integration.uuid).catch(() => []),
      ])
      setEvents(loadedEvents)
      setOrders(loadedOrders)
      setOperationsSummary(loadedSummary)
      setCatalogSyncs(loadedSyncs)
      setSuccessMessage('Evento reprocessado com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível reprocessar este evento agora.'))
    } finally {
      setRetryingEventUuid(null)
    }
  }

  async function handleCatalogSync() {
    if (!integration || !canUpdate) return

    setIsSyncingCatalog(true)
    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.syncMarketplaceCatalog(integration.uuid)
      const [loadedPreview, loadedSyncs] = await Promise.all([
        marketplaceService.getMarketplaceCatalogPreview(integration.uuid).catch(() => null),
        marketplaceService.listMarketplaceCatalogSyncs(integration.uuid),
      ])
      setCatalogPreview(loadedPreview)
      setCatalogSyncs(loadedSyncs)
      setSuccessMessage('Publicação do catálogo iniciada no iFood.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível iniciar a publicação do catálogo agora.'))
    } finally {
      setIsSyncingCatalog(false)
    }
  }

  async function refreshMerchantStatus(showSuccess = false) {
    if (!integration) return

    setIsRefreshingMerchantStatus(true)
    setFormError(null)

    try {
      const snapshot = await marketplaceService.getMarketplaceMerchantStatus(integration.uuid)
      setMerchantStatus(snapshot)
      if (showSuccess) {
        setSuccessMessage('Status operacional do iFood atualizado com sucesso.')
      }
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível carregar o status operacional do iFood agora.'))
    } finally {
      setIsRefreshingMerchantStatus(false)
    }
  }

  async function handleCreateInterruption() {
    if (!integration || !canUpdate) return

    setIsCreatingInterruption(true)
    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.createMarketplaceInterruption(integration.uuid, {
        description: interruptionDescription.trim() || 'Pausa operacional temporária',
        duration: Number(interruptionDuration),
      })
      await refreshMerchantStatus(false)
      setSuccessMessage('Pausa operacional criada no iFood.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível criar a pausa operacional agora.'))
    } finally {
      setIsCreatingInterruption(false)
    }
  }

  async function handleDeleteInterruption(interruptionId: string) {
    if (!integration || !canUpdate) return

    setRemovingInterruptionId(interruptionId)
    setFormError(null)
    setSuccessMessage(null)

    try {
      await marketplaceService.deleteMarketplaceInterruption(integration.uuid, interruptionId)
      await refreshMerchantStatus(false)
      setSuccessMessage('Pausa operacional removida do iFood.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível remover a pausa operacional agora.'))
    } finally {
      setRemovingInterruptionId(null)
    }
  }

  async function handleSyncOpeningHours() {
    if (!integration || !canUpdate) return

    setIsSyncingOpeningHours(true)
    setFormError(null)
    setSuccessMessage(null)

    try {
      const result = await marketplaceService.syncMarketplaceOpeningHours(integration.uuid)
      await refreshMerchantStatus(false)
      setSuccessMessage(`Horários da loja enviados ao iFood com sucesso. ${result.shifts_count} turno(s) publicado(s).`)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível enviar os horários da loja para o iFood agora.'))
    } finally {
      setIsSyncingOpeningHours(false)
    }
  }

  async function handleRefreshCatalogSync(syncUuid: string) {
    if (!integration || !canUpdate) return

    setRefreshingCatalogSyncUuid(syncUuid)
    setFormError(null)

    try {
      await marketplaceService.refreshMarketplaceCatalogSync(syncUuid)
      const loadedSyncs = await marketplaceService.listMarketplaceCatalogSyncs(integration.uuid)
      setCatalogSyncs(loadedSyncs)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível atualizar o status desta sincronização agora.'))
    } finally {
      setRefreshingCatalogSyncUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={{ ...SECTION_SX, mb: 3 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <LocalShippingOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>iFood</Typography>
        {integration && (
          <Chip
            label={integration.status === 'connected' ? 'Conectado' : integration.status === 'attention' ? 'Atenção' : 'Pendente'}
            color={integration.status === 'connected' ? 'success' : integration.status === 'attention' ? 'warning' : 'default'}
            size="small"
            variant={integration.status === 'connected' ? 'filled' : 'outlined'}
          />
        )}
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Cadastre a credencial da empresa, sincronize as lojas externas e acompanhe pedidos/eventos do iFood sem sair do Maskats.
      </Typography>

      {formError && <Alert severity="error" sx={{ mb: 2 }}>{formError}</Alert>}
      {successMessage && <Alert severity="success" sx={{ mb: 2 }}>{successMessage}</Alert>}
      {healthSummary && <Alert severity="info" sx={{ mb: 2 }}>{healthSummary}</Alert>}
      {loadError && (
        <Alert severity="error" sx={{ mb: 2 }} action={<Button color="inherit" size="small" onClick={() => void load()}>Tentar novamente</Button>}>
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={220} />
      ) : (
        <Stack spacing={2.5}>
          <Box sx={{ ...FORM_GRID_2_SX }}>
            <TextField label="Nome interno" value={name} onChange={(event) => setName(event.target.value)} error={Boolean(fieldErrors.name)} helperText={fieldErrors.name?.[0]} />
            <TextField select label="Ambiente" value={environment} onChange={(event) => setEnvironment(event.target.value as 'sandbox' | 'production')}>
              <MenuItem value="sandbox">Homologação</MenuItem>
              <MenuItem value="production">Produção</MenuItem>
            </TextField>
            <TextField label="Client ID" value={clientId} onChange={(event) => setClientId(event.target.value)} error={Boolean(fieldErrors.client_id)} helperText={fieldErrors.client_id?.[0] ?? 'Credencial pública da empresa no iFood.'} />
            <PasswordField label={integration ? 'Client Secret (preencha só para trocar)' : 'Client Secret'} value={clientSecret} onChange={(event) => setClientSecret(event.target.value)} error={Boolean(fieldErrors.client_secret)} helperText={fieldErrors.client_secret?.[0] ?? 'Mantido criptografado na base.'} />
            <TextField label={integration ? 'Authorization Code (preencha só para trocar)' : 'Authorization Code'} value={authorizationCode} onChange={(event) => setAuthorizationCode(event.target.value)} error={Boolean(fieldErrors.authorization_code)} helperText={fieldErrors.authorization_code?.[0] ?? 'Código inicial liberado para a empresa no portal do iFood.'} />
            <TextField label="Merchant principal" value={merchantId} onChange={(event) => setMerchantId(event.target.value)} error={Boolean(fieldErrors.merchant_id)} helperText={fieldErrors.merchant_id?.[0] ?? 'Opcional. Use quando quiser fixar a loja principal da empresa.'} />
            <TextField label="Webhook público da empresa" value={webhookUrl} onChange={(event) => setWebhookUrl(event.target.value)} error={Boolean(fieldErrors.webhook_url)} helperText={fieldErrors.webhook_url?.[0] ?? 'Opcional nesta fase. O fluxo já funciona por polling.'} />
            <TextField label="Merchant IDs para polling" value={pollingMerchantIds} onChange={(event) => setPollingMerchantIds(event.target.value)} error={Boolean(fieldErrors.polling_merchant_ids)} helperText={fieldErrors.polling_merchant_ids?.[0] ?? 'Opcional. Informe vários IDs separados por vírgula.'} />
          </Box>

          <FormControlLabel control={<Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />} label="Integração ativa para sincronização automática" />

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignItems: { sm: 'center' }, flexWrap: 'wrap' }}>
            {(integration ? canUpdate : canCreate) && (
              <Button variant="contained" onClick={() => void handleSubmit()} disabled={isSaving}>
                {integration ? 'Salvar credencial' : 'Criar integração'}
              </Button>
            )}
            {integration && canUpdate && (
              <Button variant="outlined" startIcon={<RefreshOutlinedIcon />} onClick={() => void handleSyncMerchants()} disabled={isSyncing}>
                Sincronizar lojas
              </Button>
            )}
            {integration && canUpdate && (
              <Button variant="outlined" startIcon={<PlayArrowOutlinedIcon />} onClick={() => void handlePoll()} disabled={isPolling}>
                Executar polling agora
              </Button>
            )}
            {integration && (
              <Button variant="text" onClick={() => void handleHealthCheck()}>
                Validar credencial
              </Button>
            )}
          </Stack>

          {integration && (
            <>
              <Divider />

              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', lg: 'repeat(4, minmax(0, 1fr))' }, gap: 1.25 }}>
                {[
                  { label: 'Lojas', value: integration.merchants_count ?? integration.merchants?.length ?? 0 },
                  { label: 'Eventos', value: integration.events_count ?? events.length },
                  { label: 'Pedidos', value: integration.orders_count ?? orders.length },
                  { label: 'Último polling', value: integration.last_polled_at ? new Date(integration.last_polled_at).toLocaleString('pt-BR') : '—' },
                ].map((item) => (
                  <Paper key={item.label} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{item.label}</Typography>
                    <Typography sx={{ fontSize: item.label === 'Último polling' ? 13 : 24, fontWeight: 700 }}>{item.value}</Typography>
                  </Paper>
                ))}
              </Box>

              {operationsSummary && (
                <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', xl: 'repeat(4, minmax(0, 1fr))' }, gap: 1.25 }}>
                  {[
                    { label: 'Eventos com falha', value: operationsSummary.events_failed },
                    { label: 'Eventos em letra morta', value: operationsSummary.events_dead_letter },
                    { label: 'Pedidos pendentes', value: operationsSummary.orders_pending_import },
                    { label: 'Pedidos com erro', value: operationsSummary.orders_with_import_error },
                  ].map((item) => (
                  <Paper key={item.label} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{item.label}</Typography>
                      <Typography sx={{ fontSize: 24, fontWeight: 700 }}>{item.value}</Typography>
                    </Paper>
                  ))}
                </Box>
              )}

              <Paper variant="outlined" sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 0.75 }}>Webhook operacional do iFood</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', mb: 1.5 }}>
                  Se a sua aplicação no iFood estiver habilitada para webhook, cadastre esta URL. Mesmo sem webhook, o polling manual e automático continua funcionando.
                </Typography>
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignItems: { sm: 'center' } }}>
                  <TextField
                    fullWidth
                    size="small"
                    value={integration.generated_webhook_url ?? 'URL indisponível neste ambiente'}
                    slotProps={{ input: { readOnly: true, sx: { fontFamily: 'monospace', fontSize: 13 } } }}
                  />
                  <Button variant="outlined" onClick={() => void handleCopyWebhookUrl()} disabled={!integration.generated_webhook_url}>
                    {copiedWebhook ? 'Copiado' : 'Copiar URL'}
                  </Button>
                </Stack>
                <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 1.25 }}>
                  Último webhook recebido:{' '}
                  {typeof integration.settings?.last_webhook_received_at === 'string'
                    ? new Date(integration.settings.last_webhook_received_at).toLocaleString('pt-BR')
                    : 'nenhum ainda'}
                </Typography>
              </Paper>

              <Paper variant="outlined" sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between', mb: 1.5 }}>
                  <Box>
                    <Typography sx={{ fontSize: 14.5, fontWeight: 700 }}>Catálogo iFood</Typography>
                    <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                      Esta fase publica categorias e itens simples do Maskats. Complementos e combos entram na próxima etapa.
                    </Typography>
                  </Box>
                  {canUpdate && (
                    <Button variant="contained" onClick={() => void handleCatalogSync()} disabled={isSyncingCatalog}>
                      Publicar catálogo
                    </Button>
                  )}
                </Stack>

                {catalogPreview ? (
                  <Stack spacing={1.5}>
                    <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap' }}>
                      <Chip size="small" color="primary" label={`Loja: ${catalogPreview.merchant.name}`} />
                      <Chip size="small" variant="outlined" label={`${catalogPreview.categories_total} categoria(s)`} />
                      <Chip size="small" variant="outlined" label={`${catalogPreview.items_total} item(ns)`} />
                      {catalogPreview.supported_features.map((feature) => (
                        <Chip key={feature} size="small" color="success" variant="outlined" label={feature} />
                      ))}
                      {catalogPreview.pending_features.slice(0, 4).map((feature) => (
                        <Chip key={feature} size="small" color="warning" variant="outlined" label={`Em evolução: ${feature}`} />
                      ))}
                    </Stack>

                    {catalogPreview.limitations.length > 0 && (
                      <Stack spacing={0.75}>
                        {catalogPreview.limitations.map((limitation) => (
                          <Alert key={limitation} severity="info" variant="outlined">
                            {limitation}
                          </Alert>
                        ))}
                      </Stack>
                    )}

                    <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', xl: 'repeat(2, minmax(0, 1fr))' } }}>
                      <Paper variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, mb: 1 }}>Categorias da prévia</Typography>
                        <Stack spacing={0.75}>
                          {catalogPreview.categories.slice(0, 6).map((category) => (
                            <Typography key={category.id} sx={{ fontSize: 13.5 }}>
                              {category.name}
                            </Typography>
                          ))}
                        </Stack>
                      </Paper>

                      <Paper variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, mb: 1 }}>Itens da prévia</Typography>
                        <Stack spacing={0.75}>
                          {catalogPreview.items.slice(0, 6).map((item) => (
                            <Typography key={item.id} sx={{ fontSize: 13.5 }}>
                              {item.product_name} • {item.category_name}
                            </Typography>
                          ))}
                        </Stack>
                      </Paper>
                    </Box>
                  </Stack>
                ) : (
                  <Alert severity="info" variant="outlined">
                    Salve a credencial e sincronize ao menos uma loja do iFood para gerar a prévia do catálogo.
                  </Alert>
                )}
              </Paper>

              <Paper variant="outlined" sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between', mb: 1.5 }}>
                  <Box>
                    <Typography sx={{ fontSize: 14.5, fontWeight: 700 }}>Operação da loja no iFood</Typography>
                    <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                      Consulte disponibilidade em tempo real, aplique pausas temporárias e publique os horários atuais da loja para o iFood.
                    </Typography>
                  </Box>
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                    <Button variant="outlined" onClick={() => void refreshMerchantStatus(true)} disabled={isRefreshingMerchantStatus}>
                      Atualizar status
                    </Button>
                    {canUpdate && (
                      <Button variant="outlined" onClick={() => void handleSyncOpeningHours()} disabled={isSyncingOpeningHours}>
                        Enviar horários da loja
                      </Button>
                    )}
                  </Stack>
                </Stack>

                {merchantStatus ? (
                  <Stack spacing={1.5}>
                    <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap' }}>
                      <Chip size="small" color="primary" label={`Loja monitorada: ${merchantStatus.merchant.name}`} />
                      <Chip
                        size="small"
                        variant="outlined"
                        label={
                          merchantStatus.last_opening_hours_sync_at
                            ? `Horários enviados em ${formatDateTime(merchantStatus.last_opening_hours_sync_at)}`
                            : 'Horários ainda não enviados'
                        }
                      />
                      {merchantStatus.last_opening_hours_shift_count != null && (
                        <Chip size="small" variant="outlined" label={`${merchantStatus.last_opening_hours_shift_count} turno(s) publicados`} />
                      )}
                    </Stack>

                    <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', xl: 'repeat(2, minmax(0, 1fr))' } }}>
                      <Paper variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, mb: 1 }}>Status operacional</Typography>
                        {merchantStatus.status.length === 0 ? (
                          <Alert severity="info" variant="outlined">O iFood não retornou operações visíveis para esta loja ainda.</Alert>
                        ) : (
                          <Stack spacing={1}>
                            {merchantStatus.status.map((item, index) => (
                              <Stack key={`${item.operation}-${item.salesChannel}-${index}`} direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', p: 1.25, ...SOFT_PANEL_SX, borderRadius: UI_RADIUS.md }}>
                                <Box>
                                  <Typography sx={{ fontSize: 13.5, fontWeight: 600 }}>
                                    {item.operation || 'Operação'} • {item.salesChannel || 'Canal padrão'}
                                  </Typography>
                                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                                    {item.message || item.state || (item.available ? 'Disponível para pedidos' : 'Indisponível para pedidos')}
                                  </Typography>
                                </Box>
                                <Chip
                                  size="small"
                                  color={item.available ? 'success' : item.state === 'WARNING' ? 'warning' : 'default'}
                                  variant={item.available ? 'filled' : 'outlined'}
                                  label={item.available ? 'Recebendo pedidos' : 'Pausado'}
                                />
                              </Stack>
                            ))}
                          </Stack>
                        )}
                      </Paper>

                      <Paper variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX, ...CARD_EQUAL_HEIGHT_SX }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700, mb: 1 }}>Pausas operacionais</Typography>
                        {merchantStatus.interruptions.length === 0 ? (
                          <Alert severity="success" variant="outlined">Nenhuma pausa ativa no iFood neste momento.</Alert>
                        ) : (
                          <Stack spacing={1}>
                            {merchantStatus.interruptions.map((item) => (
                              <Stack key={item.id} direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', p: 1.25, ...SOFT_PANEL_SX, borderRadius: UI_RADIUS.md }}>
                                <Box>
                                  <Typography sx={{ fontSize: 13.5, fontWeight: 600 }}>{item.description || 'Pausa operacional'}</Typography>
                                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                                    {formatDateTime(item.start)} até {formatDateTime(item.end)}
                                  </Typography>
                                </Box>
                                {canUpdate && (
                                  <Button
                                    size="small"
                                    variant="outlined"
                                    color="warning"
                                    onClick={() => void handleDeleteInterruption(item.id)}
                                    disabled={removingInterruptionId === item.id}
                                  >
                                    Remover pausa
                                  </Button>
                                )}
                              </Stack>
                            ))}
                          </Stack>
                        )}
                      </Paper>
                    </Box>

                    {canUpdate && (
                      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 2fr) minmax(180px, 1fr) auto' } }}>
                        <TextField
                          label="Motivo da pausa"
                          value={interruptionDescription}
                          onChange={(event) => setInterruptionDescription(event.target.value)}
                          helperText="Ex.: manutenção rápida, falta de energia, ajuste operacional."
                        />
                        <TextField
                          label="Duração (minutos)"
                          value={interruptionDuration}
                          onChange={(event) => setInterruptionDuration(event.target.value.replace(/\D/g, ''))}
                          helperText="Máximo de 1440 minutos."
                        />
                        <Button variant="contained" onClick={() => void handleCreateInterruption()} disabled={isCreatingInterruption} sx={{ minHeight: UI_SIZE.controlLarge, borderRadius: UI_RADIUS.md }}>
                          Criar pausa
                        </Button>
                      </Box>
                    )}
                  </Stack>
                ) : (
                  <Alert severity="info" variant="outlined">
                    Sincronize ao menos uma loja e use “Atualizar status” para consultar disponibilidade, pausas e horários publicados no iFood.
                  </Alert>
                )}
              </Paper>

              <Box>
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 1 }}>Lojas visíveis pela credencial</Typography>
                {integration.merchants && integration.merchants.length > 0 ? (
                  <Stack spacing={1}>
                    {integration.merchants.map((merchant) => (
                      <Stack key={merchant.uuid} direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', p: 1.5, ...SOFT_PANEL_SX }}>
                        <Box>
                          <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{merchant.name}</Typography>
                          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{merchant.external_id}</Typography>
                        </Box>
                        <Chip label={merchant.is_active ? 'Ativa' : 'Inativa'} color={merchant.is_active ? 'success' : 'default'} size="small" variant={merchant.is_active ? 'filled' : 'outlined'} />
                      </Stack>
                    ))}
                  </Stack>
                ) : (
                  <Alert severity="info" variant="outlined">Depois de salvar a credencial, use “Sincronizar lojas” para carregar os estabelecimentos visíveis para esta empresa.</Alert>
                )}
              </Box>

              <Box>
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 1 }}>Sincronizações de catálogo</Typography>
                {catalogSyncs.length === 0 ? (
                  <Alert severity="info" variant="outlined">Nenhuma publicação de catálogo foi iniciada ainda.</Alert>
                ) : (
                  <Stack spacing={1}>
                    {catalogSyncs.slice(0, 5).map((sync) => (
                      <Paper key={sync.uuid} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
                        <Stack spacing={1}>
                          <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                            <Box>
                              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                                {sync.merchant?.name ?? 'Loja iFood'} • {sync.status}
                              </Typography>
                              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                                {sync.success_count} sucesso(s) • {sync.failed_count} falha(s) • {sync.processed_count}/{sync.categories_total + sync.items_total} processado(s)
                              </Typography>
                            </Box>
                            {canUpdate && (
                              <Button
                                size="small"
                                variant="outlined"
                                onClick={() => void handleRefreshCatalogSync(sync.uuid)}
                                disabled={refreshingCatalogSyncUuid === sync.uuid}
                              >
                                Atualizar status
                              </Button>
                            )}
                          </Stack>

                          {sync.error_message && (
                            <Alert severity="error" variant="outlined">{sync.error_message}</Alert>
                          )}

                          {sync.items && sync.items.some((item) => item.error_message) && (
                            <Stack spacing={0.75}>
                              {sync.items.filter((item) => item.error_message).slice(0, 3).map((item) => (
                                <Alert key={item.uuid} severity="warning" variant="outlined">
                                  {item.product?.name ?? item.entity_key}: {item.error_message}
                                </Alert>
                              ))}
                            </Stack>
                          )}
                        </Stack>
                      </Paper>
                    ))}
                  </Stack>
                )}
              </Box>

              <Box>
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 1 }}>Últimos eventos recebidos</Typography>
                {events.length === 0 ? (
                  <Alert severity="info" variant="outlined">Ainda não há eventos do iFood para esta empresa. Execute o polling manual ou aguarde o scheduler.</Alert>
                ) : (
                  <Stack spacing={1}>
                    {events.slice(0, 8).map((event) => (
                      <Paper key={event.uuid} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
                        <Stack spacing={1}>
                          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between' }}>
                            <Box sx={{ minWidth: 0 }}>
                              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{event.event_type}</Typography>
                              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                                Pedido {event.external_order_id || '—'} • {event.external_event_id || 'sem id externo'}
                              </Typography>
                            </Box>
                            <Chip label={event.status} size="small" color={event.status === 'processed' ? 'success' : event.dead_lettered_at ? 'error' : event.status === 'failed' ? 'warning' : 'default'} />
                          </Stack>

                          <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap' }}>
                            <Chip size="small" variant="outlined" label={`${event.processing_attempts} tentativa(s)`} />
                            {event.acknowledged_at ? (
                              <Chip size="small" variant="outlined" color="success" label="Confirmado no iFood" />
                            ) : (
                              <Chip size="small" variant="outlined" color="warning" label="Ainda não confirmado no iFood" />
                            )}
                            {event.dead_lettered_at && (
                              <Chip size="small" color="error" label="Letra morta" />
                            )}
                          </Stack>

                          {event.error_message && (
                            <Alert severity={event.dead_lettered_at ? 'error' : 'warning'} variant="outlined">
                              {event.error_message}
                            </Alert>
                          )}

                          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                            Última tentativa: {event.last_attempted_at ? new Date(event.last_attempted_at).toLocaleString('pt-BR') : 'ainda não tentado'}
                          </Typography>

                          {canUpdate && event.status !== 'processed' && (
                            <Box>
                              <Button
                                size="small"
                                variant="outlined"
                                onClick={() => void handleRetryEvent(event.uuid)}
                                disabled={retryingEventUuid === event.uuid}
                              >
                                Reprocessar evento
                              </Button>
                            </Box>
                          )}
                        </Stack>
                      </Paper>
                    ))}
                  </Stack>
                )}
              </Box>

              <Box>
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, mb: 1 }}>Pedidos externos sincronizados</Typography>
                {orders.length === 0 ? (
                  <Alert severity="info" variant="outlined">Quando os primeiros pedidos do iFood chegarem, eles aparecerão aqui para acompanhamento operacional.</Alert>
                ) : (
                  <Stack spacing={1}>
                    {orders.slice(0, 8).map((order) => (
                      <Paper key={order.uuid} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
                        <Stack spacing={1.25}>
                          <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                            <Box sx={{ minWidth: 0 }}>
                              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                                {order.display_id || order.order_number || order.external_id}
                              </Typography>
                              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                                {order.customer_name || 'Cliente não informado'} • {order.status || 'Status não informado'} •{' '}
                                {order.total_amount != null ? order.total_amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : 'Total não informado'}
                              </Typography>
                            </Box>
                            <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                              {order.internal_order ? `Pedido interno #${order.internal_order.codigo || order.internal_order.uuid}` : 'Ainda não importado'}
                            </Typography>
                          </Stack>

                          <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap' }}>
                            {order.internal_order ? (
                              <Chip
                                size="small"
                                color="success"
                                label={`Importado no Maskats • ${order.internal_order.status}`}
                              />
                            ) : (
                              <Chip
                                size="small"
                                variant="outlined"
                                color={order.import_error_message ? 'warning' : 'default'}
                                label={order.import_error_message ? 'Aguardando mapeamento' : 'Pendente de importação'}
                              />
                            )}
                            {order.imported_at && (
                              <Chip
                                size="small"
                                variant="outlined"
                                label={`Importado em ${new Date(order.imported_at).toLocaleString('pt-BR')}`}
                              />
                            )}
                          </Stack>

                          {order.import_error_message && (
                            <Alert severity="warning" variant="outlined">
                              {order.import_error_message}
                            </Alert>
                          )}

                          {canUpdate && (
                            <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap' }}>
                              {!order.internal_order && (
                                <Button
                                  size="small"
                                  variant="contained"
                                  onClick={() => void handleImportOrder(order.uuid)}
                                  disabled={importingOrderUuid === order.uuid}
                                >
                                  Importar para pedidos
                                </Button>
                              )}
                              <Button size="small" variant="outlined" onClick={() => void handleOrderAction(order.uuid, 'confirm')}>Confirmar</Button>
                              <Button size="small" variant="outlined" onClick={() => void handleOrderAction(order.uuid, 'startPreparation')}>Preparar</Button>
                              <Button size="small" variant="outlined" onClick={() => void handleOrderAction(order.uuid, 'readyToPickup')}>Pronto</Button>
                              <Button size="small" variant="outlined" onClick={() => void handleOrderAction(order.uuid, 'dispatch')}>Despachar</Button>
                            </Stack>
                          )}
                        </Stack>
                      </Paper>
                    ))}
                  </Stack>
                )}
              </Box>
            </>
          )}
        </Stack>
      )}
    </Paper>
  )
}

/** Seção 1: chaves de API — geração exibe o valor uma única vez, revogação é definitiva. */
function ApiKeysSection() {
  const { can } = useAccessControl()
  const canCreate = can(ACCESS.apiAccessCreate)
  const canDelete = can(ACCESS.apiAccessDelete)

  const [keys, setKeys] = useState<ApiKey[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [newKeyValue, setNewKeyValue] = useState<string | null>(null)
  const [removingUuid, setRemovingUuid] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    apiKeyService
      .listApiKeys()
      .then(setKeys)
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as chaves de API agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})

    if (!name.trim()) {
      setFieldErrors({ name: ['Informe um nome para identificar esta chave.'] })
      return
    }

    setIsSubmitting(true)
    try {
      const created = await apiKeyService.createApiKey({ name: name.trim() })
      setName('')
      setNewKeyValue(created.key)
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível gerar a chave de API agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleRevoke(uuid: string) {
    setRemovingUuid(uuid)
    setFormError(null)
    try {
      await apiKeyService.revokeApiKey(uuid)
      setKeys((current) => current.filter((key) => key.uuid !== uuid))
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível revogar esta chave agora.'))
    } finally {
      setRemovingUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={SECTION_SX}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <VpnKeyOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Chaves de API</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Use uma chave de API para permitir que outro sistema leia pedidos e produtos da sua empresa. O valor completo só
        aparece uma vez, no momento em que a chave é gerada.
      </Typography>

      {newKeyValue && (
        <OneTimeSecretPanel
          label="Chave de API"
          value={newKeyValue}
          helperText="Copie e guarde esta chave agora em um lugar seguro — ela não será mostrada novamente. Se perder, gere uma nova e revogue esta."
          onDismiss={() => setNewKeyValue(null)}
        />
      )}

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}

      {canCreate && (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 3, alignItems: { sm: 'flex-start' } }}>
          <TextField
            label="Nome da chave"
            placeholder="Ex.: Integração com o ERP"
            value={name}
            onChange={(event) => setName(event.target.value)}
            error={Boolean(fieldErrors.name)}
            helperText={fieldErrors.name?.[0]}
            fullWidth
          />
          <Button
            variant="contained"
            disabled={isSubmitting}
            onClick={() => void handleSubmit()}
            sx={{ minWidth: 160, minHeight: 44 }}
          >
            {isSubmitting ? 'Gerando…' : 'Gerar chave'}
          </Button>
        </Stack>
      )}

      <Divider sx={{ mb: 2 }} />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={120} />
      ) : (
        !loadError &&
        (keys.length === 0 ? (
          <EmptyState
            icon={<VpnKeyOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
            title="Nenhuma chave de API gerada ainda"
            description="Gere uma chave para conectar outro sistema à sua empresa."
          />
        ) : (
          <Stack spacing={1}>
            {keys.map((key) => {
              const isRevoked = Boolean(key.revoked_at)
              return (
                <Stack
                  key={key.uuid}
                  direction="row"
                  sx={{
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    p: 1.5,
                    ...SOFT_PANEL_SX,
                    opacity: isRevoked ? 0.6 : 1,
                    gap: 1,
                  }}
                >
                  <Box sx={{ minWidth: 0 }}>
                    <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{key.name}</Typography>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                      {isRevoked
                        ? `Revogada em ${formatDateTime(key.revoked_at)}`
                        : `Último uso: ${formatDateTime(key.last_used_at)}`}
                    </Typography>
                  </Box>
                  {canDelete && !isRevoked && (
                    <IconButton
                      size="small"
                      aria-label={`Revogar chave ${key.name}`}
                      disabled={removingUuid === key.uuid}
                      onClick={() => void handleRevoke(key.uuid)}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  )}
                </Stack>
              )
            })}
          </Stack>
        ))
      )}
    </Paper>
  )
}

const EMPTY_WEBHOOK_FORM = {
  url: '',
  event_types: [] as WebhookEventType[],
  is_active: true,
}

/** Seção 2: assinaturas de webhook — CRUD completo, `secret` exibido uma única vez na criação. */
function WebhookSubscriptionsSection() {
  const { can } = useAccessControl()
  const canCreate = can(ACCESS.apiAccessCreate)
  const canUpdate = can(ACCESS.apiAccessUpdate)
  const canDelete = can(ACCESS.apiAccessDelete)

  const [subscriptions, setSubscriptions] = useState<WebhookSubscription[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [editingUuid, setEditingUuid] = useState<string | null>(null)
  const [form, setForm] = useState(EMPTY_WEBHOOK_FORM)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [removingUuid, setRemovingUuid] = useState<string | null>(null)
  const [newSecret, setNewSecret] = useState<string | null>(null)

  function load() {
    setIsLoading(true)
    setLoadError(null)
    webhookSubscriptionService
      .listWebhookSubscriptions()
      .then(setSubscriptions)
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os webhooks agora.')))
      .finally(() => setIsLoading(false))
  }

  useEffect(load, [])

  function resetForm() {
    setEditingUuid(null)
    setForm(EMPTY_WEBHOOK_FORM)
    setFieldErrors({})
  }

  function startEdit(subscription: WebhookSubscription) {
    setEditingUuid(subscription.uuid)
    setForm({ url: subscription.url, event_types: subscription.event_types, is_active: subscription.is_active })
    setFieldErrors({})
    setFormError(null)
  }

  function toggleEventType(eventType: WebhookEventType) {
    setForm((current) => ({
      ...current,
      event_types: current.event_types.includes(eventType)
        ? current.event_types.filter((type) => type !== eventType)
        : [...current.event_types, eventType],
    }))
  }

  async function handleSubmit() {
    setFormError(null)

    const errors: Record<string, string[]> = {}
    if (!form.url.trim()) errors.url = ['Informe a URL que vai receber os eventos.']
    if (form.event_types.length === 0) errors.event_types = ['Selecione ao menos um evento.']
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }
    setFieldErrors({})

    setIsSubmitting(true)
    try {
      const payload = { url: form.url.trim(), event_types: form.event_types, is_active: form.is_active }
      if (editingUuid) {
        await webhookSubscriptionService.updateWebhookSubscription(editingUuid, payload)
      } else {
        const created = await webhookSubscriptionService.createWebhookSubscription(payload)
        setNewSecret(created.secret)
      }
      resetForm()
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar este webhook agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete(uuid: string) {
    setRemovingUuid(uuid)
    setFormError(null)
    try {
      await webhookSubscriptionService.deleteWebhookSubscription(uuid)
      setSubscriptions((current) => current.filter((subscription) => subscription.uuid !== uuid))
      if (editingUuid === uuid) resetForm()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível remover este webhook agora.'))
    } finally {
      setRemovingUuid(null)
    }
  }

  return (
    <Paper variant="outlined" sx={{ ...SECTION_SX, mt: 3 }}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5 }}>
        <WebhookOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
        <Typography sx={{ fontSize: 17, fontWeight: 700 }}>Webhooks</Typography>
      </Stack>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2.5 }}>
        Receba um aviso automático em outro sistema sempre que um pedido mudar de status. A chave secreta de cada
        webhook só aparece uma vez, no momento em que ele é criado.
      </Typography>

      {newSecret && (
        <OneTimeSecretPanel
          label="Segredo do webhook"
          value={newSecret}
          helperText="Use este valor para conferir que cada aviso recebido realmente veio da Maskats. Copie e guarde agora — não será mostrado novamente."
          onDismiss={() => setNewSecret(null)}
        />
      )}

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}

      {(canCreate || canUpdate) && (
        <Box sx={{ mb: 3 }}>
          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 2, mb: 1.5 }}>
            <TextField
              label="URL de destino"
              placeholder="https://seusite.com/webhooks/maskats"
              value={form.url}
              onChange={(event) => setForm((current) => ({ ...current, url: event.target.value }))}
              error={Boolean(fieldErrors.url)}
              helperText={fieldErrors.url?.[0]}
              fullWidth
            />
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Switch
                checked={form.is_active}
                onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
              />
              <Typography sx={{ fontSize: 14 }}>Ativo</Typography>
            </Stack>
          </Box>

          <Typography sx={{ fontSize: 13, fontWeight: 600, mb: 0.5 }}>Eventos</Typography>
          <FormGroup
            row
            sx={{ mb: fieldErrors.event_types ? 0 : 1.5 }}
          >
            {WEBHOOK_EVENT_TYPES.map((eventType) => (
              <FormControlLabel
                key={eventType}
                control={
                  <Checkbox
                    checked={form.event_types.includes(eventType)}
                    onChange={() => toggleEventType(eventType)}
                  />
                }
                label={WEBHOOK_EVENT_LABELS[eventType]}
              />
            ))}
          </FormGroup>
          {fieldErrors.event_types && (
            <Typography sx={{ fontSize: 12.5, color: 'var(--mk-error, #d32f2f)', mb: 1.5 }}>
              {fieldErrors.event_types[0]}
            </Typography>
          )}

          <Stack direction="row" spacing={1.5} sx={{ justifyContent: 'flex-end' }}>
            {editingUuid && (
              <Button variant="text" onClick={resetForm} disabled={isSubmitting}>
                Cancelar edição
              </Button>
            )}
            <Button variant="outlined" disabled={isSubmitting} onClick={() => void handleSubmit()}>
              {isSubmitting ? 'Salvando…' : editingUuid ? 'Salvar alterações' : 'Criar webhook'}
            </Button>
          </Stack>
        </Box>
      )}

      <Divider sx={{ mb: 2 }} />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Skeleton variant="rounded" height={120} />
      ) : (
        !loadError &&
        (subscriptions.length === 0 ? (
          <EmptyState
            icon={<WebhookOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />}
            title="Nenhum webhook cadastrado ainda"
            description="Cadastre uma URL para receber avisos automáticos de mudança de status dos pedidos."
          />
        ) : (
          <Stack spacing={1}>
            {subscriptions.map((subscription) => (
              <Stack
                key={subscription.uuid}
                direction={{ xs: 'column', sm: 'row' }}
                spacing={1}
                sx={{
                  alignItems: { sm: 'center' },
                  justifyContent: 'space-between',
                  p: 1.5,
                  ...SOFT_PANEL_SX,
                  opacity: subscription.is_active ? 1 : 0.6,
                }}
              >
                <Box sx={{ minWidth: 0 }}>
                  <Typography sx={{ fontSize: 14, fontWeight: 600, wordBreak: 'break-all' }}>{subscription.url}</Typography>
                  <Stack direction="row" spacing={0.5} sx={{ flexWrap: 'wrap', mt: 0.5 }}>
                    {subscription.event_types.map((eventType) => (
                      <Chip key={eventType} label={WEBHOOK_EVENT_LABELS[eventType]} size="small" />
                    ))}
                    {!subscription.is_active && <Chip label="Inativo" size="small" color="default" variant="outlined" />}
                  </Stack>
                </Box>
                <Stack direction="row" spacing={0.5} sx={{ flexShrink: 0, alignSelf: { xs: 'flex-end', sm: 'center' } }}>
                  {canUpdate && (
                    <IconButton
                      size="small"
                      aria-label={`Editar webhook ${subscription.url}`}
                      onClick={() => startEdit(subscription)}
                    >
                      <EditOutlinedIcon fontSize="small" />
                    </IconButton>
                  )}
                  {canDelete && (
                    <IconButton
                      size="small"
                      aria-label={`Remover webhook ${subscription.url}`}
                      disabled={removingUuid === subscription.uuid}
                      onClick={() => void handleDelete(subscription.uuid)}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  )}
                </Stack>
              </Stack>
            ))}
          </Stack>
        ))
      )}
    </Paper>
  )
}

/** Tela gated por `api-access:read` — geração de chave de API e assinaturas de webhook pro tenant conectar outro sistema. */
export function IntegrationsPage() {
  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, width: '100%', minWidth: 0, flex: 1 }}>
      <PageHeader
        title="Integrações"
        subtitle="Configure os parâmetros da sua empresa e conecte outros sistemas por API e webhook sem depender de configuração manual fora da plataforma."
      />
      <CompanyParametersSection />
      <MarketplaceSection />
      <ApiKeysSection />
      <WebhookSubscriptionsSection />
    </Box>
  )
}
