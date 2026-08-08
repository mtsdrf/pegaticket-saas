import CheckCircleOutlineRoundedIcon from '@mui/icons-material/CheckCircleOutlineRounded'
import HourglassEmptyRoundedIcon from '@mui/icons-material/HourglassEmptyRounded'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import { Alert, Box, Button, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { PageHeader } from '../../components/layout/PageHeader'
import { ReceivingSetupWizard } from '../../components/receiving/ReceivingSetupWizard'
import { ReceivingStatusBadge } from '../../components/receiving/ReceivingStatusBadge'
import { ACCESS } from '../../access/requirements'
import { useAuth } from '../../hooks/useAuth'
import * as pagBankConnectService from '../../services/pagBankConnectService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { getApiErrorMessage } from '../../types/api'
import type { TenantPagBankConnection } from '../../types/pagBankConnect'
import { formatDateBR } from '../../utils/format'
import { groupConnectionStatus } from '../../utils/receivingStatusGroups'

const GROUP_ICON = {
  in_review: HourglassEmptyRoundedIcon,
  enabled: CheckCircleOutlineRoundedIcon,
  restricted: ReportProblemOutlinedIcon,
  rejected: ReportProblemOutlinedIcon,
} as const

const GROUP_DETAIL = {
  in_review: {
    title: 'Conta em análise',
    description:
      'Estamos aguardando a análise da sua conta de recebimento pelo PagBank. Assim que ela for aprovada, você poderá vender ingressos pagos. Não é possível garantir um prazo exato para essa análise.',
  },
  enabled: {
    title: 'Recebimentos habilitados',
    description: 'Sua conta está pronta para receber o pagamento das vendas de ingressos.',
  },
  restricted: {
    title: 'Precisamos atualizar seu cadastro',
    description: 'Há uma pendência no seu cadastro de recebimento. Atualize os dados para continuar recebendo normalmente.',
  },
  rejected: {
    title: 'Recebimentos temporariamente indisponíveis',
    description: 'Não foi possível habilitar seus recebimentos agora. Revise os dados enviados e tente novamente.',
  },
} as const

/**
 * Rota `/configuracoes/pagbank-connect` — destino fixo do redirect do
 * callback OAuth do backend (`PagBankConnectController::callback`), por
 * isso não pode mudar de path sem coordenar com o backend. Trata
 * `?pagbank_connect=success|error` da query string e decide entre mostrar
 * o wizard (sem conexão/retomando) ou o card de status (conexão já em
 * algum estado avançado).
 */
export function ReceivingSetupPage() {
  const { hasPermission } = useAuth()
  const canView = hasPermission(ACCESS.financialReceivingRead)
  const canUpdate = hasPermission(ACCESS.financialReceivingUpdate)

  const [searchParams, setSearchParams] = useSearchParams()
  const [connection, setConnection] = useState<TenantPagBankConnection | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [callbackNotice, setCallbackNotice] = useState<{ severity: 'success' | 'error'; message: string } | null>(null)
  const [showWizard, setShowWizard] = useState(false)
  const [disconnectSubmitting, setDisconnectSubmitting] = useState(false)
  const [disconnectError, setDisconnectError] = useState<string | null>(null)

  const load = useCallback(() => {
    setIsLoading(true)
    setLoadError(null)
    pagBankConnectService
      .getStatus()
      .then(setConnection)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a situação dos seus recebimentos agora.'))
      })
      .finally(() => setIsLoading(false))
  }, [])

  useEffect(() => {
    if (!canView) {
      setIsLoading(false)
      return
    }
    load()
  }, [canView, load])

  useEffect(() => {
    const result = searchParams.get('pagbank_connect')
    if (!result) return

    if (result === 'success') {
      setCallbackNotice({ severity: 'success', message: 'Conta PagBank conectada com sucesso.' })
      load()
    } else if (result === 'error') {
      // Nunca mostrar o `reason` técnico bruto ao usuário final (roadmap
      // seção 9.6/9.7) — mensagem genérica e amigável sempre, independente
      // do motivo real reportado pelo PagBank.
      setCallbackNotice({
        severity: 'error',
        message: 'Não foi possível concluir a conexão com o PagBank agora. Tente novamente.',
      })
    }

    const next = new URLSearchParams(searchParams)
    next.delete('pagbank_connect')
    next.delete('reason')
    setSearchParams(next, { replace: true })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function handleDisconnect() {
    setDisconnectSubmitting(true)
    setDisconnectError(null)
    try {
      await pagBankConnectService.disconnect()
      setShowWizard(false)
      load()
    } catch (error) {
      setDisconnectError(getApiErrorMessage(error, 'Não foi possível desconectar sua conta agora.'))
    } finally {
      setDisconnectSubmitting(false)
    }
  }

  if (!canView) {
    return (
      <Box sx={{ width: '100%', minWidth: 0, flex: 1 }}>
        <PageHeader
          title="Recebimentos"
          subtitle="Configure a conta que recebe o pagamento das suas vendas de ingressos."
          backLink={{ label: 'Voltar para Configurações', to: '/configuracoes' }}
        />
        <Alert severity="info" variant="outlined">
          Fale com o proprietário da empresa ou com quem administra os acessos para liberar a permissão de
          recebimentos, se você precisar dela.
        </Alert>
      </Box>
    )
  }

  const group = groupConnectionStatus(connection?.status ?? null)

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, width: '100%', minWidth: 0, flex: 1 }}>
      <PageHeader
        title="Recebimentos"
        subtitle="Configure a conta que recebe o pagamento das suas vendas de ingressos."
        backLink={{ label: 'Voltar para Configurações', to: '/configuracoes' }}
      />

      {callbackNotice && (
        <Alert severity={callbackNotice.severity} sx={{ mb: 2.5 }} onClose={() => setCallbackNotice(null)}>
          {callbackNotice.message}
        </Alert>
      )}

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
        <Skeleton variant="rounded" height={280} />
      ) : loadError ? null : group === 'not_configured' || showWizard ? (
        <Paper variant="outlined" sx={{ p: { xs: 2.5, sm: 3.5 }, ...ELEVATED_SURFACE_SX }}>
          <ReceivingSetupWizard
            onFinished={() => {
              setShowWizard(false)
              load()
            }}
          />
        </Paper>
      ) : group === 'in_progress' ? (
        <Paper variant="outlined" sx={{ p: { xs: 2.5, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
          <Stack spacing={2}>
            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
              <Typography sx={{ fontWeight: 700, fontSize: 17 }}>Configuração em andamento</Typography>
              <ReceivingStatusBadge status={connection?.status ?? null} />
            </Stack>
            <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>
              Falta pouco para concluir a configuração dos seus recebimentos.
            </Typography>
            {canUpdate && (
              <Box>
                <Button variant="contained" onClick={() => setShowWizard(true)}>
                  Continuar configuração
                </Button>
              </Box>
            )}
          </Stack>
        </Paper>
      ) : (
        <Paper variant="outlined" sx={{ p: { xs: 2.5, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
          <Stack spacing={2}>
            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
              {(() => {
                const Icon = GROUP_ICON[group as keyof typeof GROUP_ICON]
                return Icon ? <Icon sx={{ color: 'var(--pt-primary)' }} /> : null
              })()}
              <Typography sx={{ fontWeight: 700, fontSize: 17 }}>
                {GROUP_DETAIL[group as keyof typeof GROUP_DETAIL]?.title}
              </Typography>
              <ReceivingStatusBadge status={connection?.status ?? null} />
            </Stack>
            <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>
              {GROUP_DETAIL[group as keyof typeof GROUP_DETAIL]?.description}
            </Typography>

            {connection?.document_masked && (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Documento: {connection.document_masked}</Typography>
            )}
            {connection?.connected_at && (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                Conectado em {formatDateBR(connection.connected_at)}.
              </Typography>
            )}

            {disconnectError && <Alert severity="error">{disconnectError}</Alert>}

            {canUpdate && (
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                {(group === 'restricted' || group === 'rejected') && (
                  <Button variant="contained" onClick={() => setShowWizard(true)}>
                    Atualizar cadastro
                  </Button>
                )}
                {group === 'enabled' && (
                  <Button variant="outlined" color="error" disabled={disconnectSubmitting} onClick={() => void handleDisconnect()}>
                    {disconnectSubmitting ? 'Desconectando…' : 'Desconectar conta'}
                  </Button>
                )}
              </Stack>
            )}
          </Stack>
        </Paper>
      )}
    </Box>
  )
}
