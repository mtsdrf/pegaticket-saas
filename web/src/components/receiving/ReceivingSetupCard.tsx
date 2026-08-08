import AccountBalanceWalletOutlinedIcon from '@mui/icons-material/AccountBalanceWalletOutlined'
import { Alert, Box, Button, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { ReceivingStatusBadge } from './ReceivingStatusBadge'
import { ACCESS } from '../../access/requirements'
import { useAuth } from '../../hooks/useAuth'
import * as pagBankConnectService from '../../services/pagBankConnectService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { TenantPagBankConnection } from '../../types/pagBankConnect'
import { groupConnectionStatus } from '../../utils/receivingStatusGroups'

const GROUP_MESSAGES: Record<ReturnType<typeof groupConnectionStatus>, { title: string; description: string; cta: string }> = {
  not_configured: {
    title: 'Configure seus recebimentos',
    description: 'Para vender ingressos pagos, precisamos configurar a conta que receberá suas vendas.',
    cta: 'Configurar agora',
  },
  in_progress: {
    title: 'Configuração em andamento',
    description: 'Falta pouco para concluir a configuração dos seus recebimentos.',
    cta: 'Continuar configuração',
  },
  in_review: {
    title: 'Conta em análise',
    description: 'Estamos aguardando a análise da sua conta de recebimento. Assim que for aprovada, você poderá vender ingressos pagos.',
    cta: 'Ver detalhes',
  },
  enabled: {
    title: 'Recebimentos habilitados',
    description: 'Sua conta está pronta para receber o pagamento das vendas de ingressos.',
    cta: 'Ver detalhes',
  },
  restricted: {
    title: 'Precisamos atualizar seu cadastro',
    description: 'Há uma pendência no seu cadastro de recebimento. Atualize os dados para continuar recebendo normalmente.',
    cta: 'Resolver pendência',
  },
  rejected: {
    title: 'Recebimentos temporariamente indisponíveis',
    description: 'Não foi possível habilitar seus recebimentos agora. Revise os dados enviados e tente novamente.',
    cta: 'Resolver pendência',
  },
}

/**
 * Card reutilizável de entrada do onboarding financeiro (roadmap seção 9.5.4)
 * — self-contido: busca o próprio status, trata loading/erro/permissão, e só
 * linka para `/configuracoes/pagbank-connect` (não abre o wizard inline).
 * Pensado para reaparecer em outras telas (Home, cadastro de ingresso) sem
 * duplicar a lógica de status — só a integração nesses pontos de entrada
 * específicos fica fora do escopo desta sub-fase (R2.4).
 */
export function ReceivingSetupCard() {
  const { hasPermission } = useAuth()
  const canView = hasPermission(ACCESS.financialReceivingRead)

  const [connection, setConnection] = useState<TenantPagBankConnection | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  useEffect(() => {
    if (!canView) {
      setIsLoading(false)
      return
    }

    let cancelled = false
    setIsLoading(true)
    setLoadError(null)
    pagBankConnectService
      .getStatus()
      .then((result) => {
        if (!cancelled) setConnection(result)
      })
      .catch((error: unknown) => {
        if (!cancelled) setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a situação dos seus recebimentos agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [canView])

  // 403/sem permissão: oculta o card por completo, não quebra a tela onde
  // ele foi inserido.
  if (!canView) return null

  if (isLoading) {
    return <Skeleton variant="rounded" height={104} />
  }

  if (loadError) {
    return (
      <Alert severity="error" variant="outlined">
        {loadError}
      </Alert>
    )
  }

  const group = groupConnectionStatus(connection?.status ?? null)
  const copy = GROUP_MESSAGES[group]

  return (
    <Paper variant="outlined" sx={{ p: { xs: 2.25, sm: 2.75 }, ...ELEVATED_SURFACE_SX }}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}>
        <Stack direction="row" spacing={1.75} sx={{ alignItems: 'flex-start', minWidth: 0 }}>
          <Box
            sx={{
              width: 42,
              height: 42,
              flexShrink: 0,
              borderRadius: 'var(--pt-radius-md)',
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: 'color-mix(in srgb, var(--pt-primary) 12%, white)',
              color: 'var(--pt-primary)',
            }}
          >
            <AccountBalanceWalletOutlinedIcon fontSize="small" />
          </Box>
          <Box sx={{ minWidth: 0 }}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <Typography sx={{ fontWeight: 700, fontSize: 15 }}>{copy.title}</Typography>
              <ReceivingStatusBadge status={connection?.status ?? null} />
            </Stack>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.4 }}>{copy.description}</Typography>
          </Box>
        </Stack>

        {hasPermission(ACCESS.financialReceivingUpdate) && (
          <Button
            component={RouterLink}
            to="/configuracoes/pagbank-connect"
            variant={group === 'not_configured' || group === 'restricted' || group === 'rejected' ? 'contained' : 'outlined'}
            sx={{ flexShrink: 0, alignSelf: { xs: 'stretch', sm: 'center' } }}
          >
            {copy.cta}
          </Button>
        )}
      </Stack>
    </Paper>
  )
}
