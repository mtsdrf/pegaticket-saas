import DeleteOutlineOutlinedIcon from '@mui/icons-material/DeleteOutlineOutlined'
import MailOutlineOutlinedIcon from '@mui/icons-material/MailOutlineOutlined'
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
import { useEffect, useState } from 'react'
import { ACCESS } from '../../../access/requirements'
import { useAccessControl } from '../../../hooks/useAccessControl'
import * as scheduledReportSubscriptionService from '../../../services/scheduledReportSubscriptionService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { UI_SIZE } from '../../../styles/layoutStandards'
import { getApiErrorMessage } from '../../../types/api'
import type {
  ScheduledReportFrequency,
  ScheduledReportSubscription,
} from '../../../types/scheduledReportSubscription'

const FREQUENCY_LABELS: Record<ScheduledReportFrequency, string> = {
  daily: 'Diário',
  weekly: 'Semanal',
}

/**
 * Relatórios agendados básicos (roadmap A2) — assina um e-mail (próprio do
 * usuário logado ou customizado) para receber o resumo dos KPIs do Home
 * diária ou semanalmente. CRUD simples: criar, listar, cancelar. Ver
 * App\Services\Report\ScheduledReportSubscriptionService (backend).
 */
export function ScheduledReportsBlock() {
  const { can } = useAccessControl()
  const canRead = can(ACCESS.reportsRead)
  const canCreate = can(ACCESS.reportsCreate)
  const canDelete = can(ACCESS.reportsDelete)

  const [subscriptions, setSubscriptions] = useState<ScheduledReportSubscription[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [recipientEmail, setRecipientEmail] = useState('')
  const [frequency, setFrequency] = useState<ScheduledReportFrequency>('weekly')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [cancellingUuid, setCancellingUuid] = useState<string | null>(null)

  useEffect(() => {
    if (!canRead) {
      setIsLoading(false)
      return
    }
    void loadSubscriptions()
  }, [canRead])

  async function loadSubscriptions() {
    setIsLoading(true)
    setLoadError(null)
    try {
      setSubscriptions(await scheduledReportSubscriptionService.listScheduledReportSubscriptions())
    } catch (error) {
      setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as assinaturas agora.'))
    } finally {
      setIsLoading(false)
    }
  }

  async function handleCreate() {
    if (!recipientEmail.trim()) return
    setFormError(null)
    setIsSubmitting(true)
    try {
      await scheduledReportSubscriptionService.createScheduledReportSubscription({
        recipient_email: recipientEmail.trim(),
        frequency,
      })
      await loadSubscriptions()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível criar a assinatura agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleCancel(uuid: string) {
    setCancellingUuid(uuid)
    setFormError(null)
    try {
      await scheduledReportSubscriptionService.cancelScheduledReportSubscription(uuid)
      await loadSubscriptions()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível cancelar a assinatura agora.'))
    } finally {
      setCancellingUuid(null)
    }
  }

  return (
    <Stack spacing={2}>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
        Receba por e-mail um resumo dos principais números do Home (vendas, faturamento, ticket médio, valor
        recebido) diária ou semanalmente — útil para acompanhar a operação sem entrar no painel todo dia.
      </Typography>

      {formError ? <Alert severity="error">{formError}</Alert> : null}

      {canCreate ? (
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <MailOutlineOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                Nova assinatura
              </Typography>
            </Stack>

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <TextField
                label="E-mail de destino"
                type="email"
                value={recipientEmail}
                onChange={(event) => setRecipientEmail(event.target.value)}
                fullWidth
              />
              <TextField
                select
                label="Frequência"
                value={frequency}
                onChange={(event) => setFrequency(event.target.value as ScheduledReportFrequency)}
                sx={{ minWidth: { sm: 180 } }}
              >
                <MenuItem value="daily">Diário</MenuItem>
                <MenuItem value="weekly">Semanal</MenuItem>
              </TextField>
            </Stack>

            <Button
              variant="contained"
              onClick={() => void handleCreate()}
              disabled={isSubmitting || !recipientEmail.trim()}
              sx={{ alignSelf: 'flex-start', minHeight: UI_SIZE.control }}
            >
              {isSubmitting ? 'Criando…' : 'Criar assinatura'}
            </Button>
          </Stack>
        </Paper>
      ) : null}

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
        <Stack spacing={1.5}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
            Assinaturas ativas
          </Typography>

          {!canRead ? (
            <Alert severity="info" variant="outlined">
              Seu perfil atual não pode consultar as assinaturas de relatório agendado desta empresa.
            </Alert>
          ) : isLoading ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
              <CircularProgress size={22} />
            </Box>
          ) : loadError ? (
            <Alert severity="error">{loadError}</Alert>
          ) : subscriptions.length === 0 ? (
            <Alert severity="info" variant="outlined">
              Nenhuma assinatura ativa. Crie uma acima para receber o resumo por e-mail.
            </Alert>
          ) : (
            <Stack spacing={1.25}>
              {subscriptions.map((subscription) => (
                <Paper
                  key={subscription.uuid}
                  variant="outlined"
                  sx={{
                    p: 1.5,
                    borderColor: 'var(--pt-border)',
                    background: 'var(--pt-surface)',
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 1,
                  }}
                >
                  <Box sx={{ minWidth: 0 }}>
                    <Typography sx={{ fontSize: 13.5, fontWeight: 700 }}>{subscription.recipient_email}</Typography>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                      {subscription.last_sent_at
                        ? `Último envio em ${new Date(subscription.last_sent_at).toLocaleString('pt-BR')}`
                        : 'Ainda não enviado'}
                    </Typography>
                  </Box>

                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexShrink: 0 }}>
                    <Chip size="small" label={FREQUENCY_LABELS[subscription.frequency]} />
                    {canDelete ? (
                      <Button
                        size="small"
                        variant="outlined"
                        color="error"
                        startIcon={<DeleteOutlineOutlinedIcon fontSize="small" />}
                        disabled={cancellingUuid === subscription.uuid}
                        onClick={() => void handleCancel(subscription.uuid)}
                      >
                        Cancelar
                      </Button>
                    ) : null}
                  </Stack>
                </Paper>
              ))}
            </Stack>
          )}
        </Stack>
      </Paper>
    </Stack>
  )
}
