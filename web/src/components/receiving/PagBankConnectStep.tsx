import LinkOutlinedIcon from '@mui/icons-material/LinkOutlined'
import PersonAddAltOutlinedIcon from '@mui/icons-material/PersonAddAltOutlined'
import { Alert, Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useState } from 'react'
import * as pagBankConnectService from '../../services/pagBankConnectService'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'

interface PagBankConnectStepProps {
  onChooseCreateAccount: () => void
}

/**
 * Tela de entrada do onboarding financeiro — textos oficiais preservados
 * literalmente (roadmap seção 9.6). Oferece os dois caminhos: conectar
 * conta PagBank existente (OAuth, navegação real fora da SPA) ou criar
 * conta nova (segue para `PagBankAccountTypeStep` dentro do wizard).
 */
export function PagBankConnectStep({ onChooseCreateAccount }: PagBankConnectStepProps) {
  const [isRedirecting, setIsRedirecting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleConnectExisting() {
    setError(null)
    setIsRedirecting(true)
    try {
      const { authorize_url } = await pagBankConnectService.getAuthorizeUrl()
      window.location.href = authorize_url
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível iniciar a conexão com o PagBank agora. Tente novamente em instantes.'))
      setIsRedirecting(false)
    }
  }

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography sx={{ fontWeight: 700, fontSize: 20, mb: 0.75 }}>Configure seus recebimentos</Typography>
        <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', maxWidth: 620 }}>
          Para vender ingressos pagos, precisamos configurar a conta que receberá suas vendas.
        </Typography>
      </Box>

      <Typography sx={{ fontSize: 14, color: 'var(--pt-text)', maxWidth: 640 }}>
        Se você já possui uma conta PagBank, poderá conectá-la. Caso ainda não possua, ajudaremos você a criar sua
        conta de recebimento.
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
        <Paper variant="outlined" sx={{ p: 2.5, flex: 1, ...ELEVATED_SURFACE_SX }}>
          <Stack spacing={1.5} sx={{ height: '100%' }}>
            <LinkOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
            <Typography sx={{ fontWeight: 700, fontSize: 15 }}>Já tenho uma conta PagBank</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', flex: 1 }}>
              Você será direcionado ao PagBank para autorizar o recebimento das suas vendas com segurança.
            </Typography>
            <Button variant="contained" disabled={isRedirecting} onClick={() => void handleConnectExisting()}>
              {isRedirecting ? 'Redirecionando…' : 'Conectar conta existente'}
            </Button>
          </Stack>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2.5, flex: 1, ...ELEVATED_SURFACE_SX }}>
          <Stack spacing={1.5} sx={{ height: '100%' }}>
            <PersonAddAltOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
            <Typography sx={{ fontWeight: 700, fontSize: 15 }}>Ainda não tenho conta</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', flex: 1 }}>
              Vamos te ajudar a criar sua conta de recebimento agora mesmo, direto por aqui.
            </Typography>
            <Button variant="outlined" disabled={isRedirecting} onClick={onChooseCreateAccount}>
              Criar minha conta de recebimento
            </Button>
          </Stack>
        </Paper>
      </Stack>
    </Stack>
  )
}
