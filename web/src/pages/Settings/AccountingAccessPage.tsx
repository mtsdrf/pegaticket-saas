import ChatOutlinedIcon from '@mui/icons-material/ChatOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  FormGroup,
  IconButton,
  Paper,
  Skeleton,
  Stack,
  Typography,
} from '@mui/material'
import CloseIcon from '@mui/icons-material/Close'
import { useCallback, useEffect, useState } from 'react'
import { AccountingMessageThread } from '../../components/accounting/AccountingMessageThread'
import { AccountingStatusBadge } from '../../components/accounting/AccountingStatusBadge'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAuth } from '../../hooks/useAuth'
import * as tenantAccountingService from '../../services/tenantAccountingAccessService'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type {
  AccountingMessage,
  AccountingOfficeTenantLink,
  AccountingScope,
  CreateAccountingMessagePayload,
} from '../../types/accounting'
import { getApiErrorMessage } from '../../types/api'
import { formatDateBR } from '../../utils/format'

const SCOPE_OPTIONS: { value: AccountingScope; label: string; hint: string }[] = [
  { value: 'financial.read', label: 'Financeiro', hint: 'Vendas e livro-caixa.' },
  { value: 'reports.read', label: 'Relatórios', hint: 'DRE e demais relatórios.' },
  { value: 'fiscal.read', label: 'Fiscal', hint: 'Consulta de dados fiscais.' },
  { value: 'fiscal.write', label: 'Fiscal - editar produtos', hint: 'Permite preencher os dados fiscais dos produtos.' },
]

function ApproveDialog({
  link,
  onClose,
  onApproved,
}: {
  link: AccountingOfficeTenantLink
  onClose: () => void
  onApproved: () => void
}) {
  const [scopes, setScopes] = useState<AccountingScope[]>(['financial.read', 'reports.read'])
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function toggle(scope: AccountingScope) {
    setScopes((current) => (current.includes(scope) ? current.filter((item) => item !== scope) : [...current, scope]))
  }

  async function handleApprove() {
    setError(null)
    setIsSubmitting(true)
    try {
      await tenantAccountingService.approveAccessRequest(link.uuid, { scopes })
      onApproved()
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível aprovar a solicitação agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Aprovar acesso do contador</DialogTitle>
      <DialogContent>
        <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)', mb: 2 }}>
          Escolha o que o escritório <strong>{link.accounting_office?.company_name}</strong> poderá consultar.
        </Typography>

        {error && (
          <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        <FormGroup>
          {SCOPE_OPTIONS.map((option) => (
            <FormControlLabel
              key={option.value}
              control={<Checkbox checked={scopes.includes(option.value)} onChange={() => toggle(option.value)} />}
              label={
                <Box>
                  <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>{option.label}</Typography>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{option.hint}</Typography>
                </Box>
              }
              sx={{ alignItems: 'flex-start', mb: 1 }}
            />
          ))}
        </FormGroup>

        <Stack direction="row" spacing={1.5} sx={{ mt: 2, justifyContent: 'flex-end' }}>
          <Button onClick={onClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button variant="contained" onClick={handleApprove} disabled={isSubmitting || scopes.length === 0}>
            {isSubmitting ? 'Aprovando…' : 'Aprovar'}
          </Button>
        </Stack>
      </DialogContent>
    </Dialog>
  )
}

function MessagesDialog({ link, onClose }: { link: AccountingOfficeTenantLink; onClose: () => void }) {
  const [messages, setMessages] = useState<AccountingMessage[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setIsLoading(true)
    setLoadError(null)
    try {
      setMessages(await tenantAccountingService.listMessages(link.uuid))
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar as mensagens agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [link.uuid])

  useEffect(() => {
    void load()
  }, [load])

  const handleSend = useCallback(
    async (payload: CreateAccountingMessagePayload) => {
      await tenantAccountingService.sendMessage(link.uuid, payload)
      await load()
    },
    [link.uuid, load],
  )

  return (
    <Dialog open onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700, pr: 6 }}>
        Pendências · {link.accounting_office?.company_name}
        <IconButton onClick={onClose} sx={{ position: 'absolute', right: 8, top: 8 }} aria-label="Fechar">
          <CloseIcon />
        </IconButton>
      </DialogTitle>
      <DialogContent>
        <AccountingMessageThread
          messages={messages}
          isLoading={isLoading}
          loadError={loadError}
          currentSender="tenant"
          onSend={handleSend}
          disabledReason={
            link.status !== 'approved' ? 'O vínculo não está ativo. Reative o acesso para trocar mensagens.' : undefined
          }
        />
      </DialogContent>
    </Dialog>
  )
}

export function AccountingAccessPage() {
  const { activeTenantUuid } = useAuth()
  const [links, setLinks] = useState<AccountingOfficeTenantLink[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [approving, setApproving] = useState<AccountingOfficeTenantLink | null>(null)
  const [messaging, setMessaging] = useState<AccountingOfficeTenantLink | null>(null)
  const [revokingUuid, setRevokingUuid] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!activeTenantUuid) return
    setIsLoading(true)
    setError(null)
    try {
      setLinks(await tenantAccountingService.listAccessRequests())
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar as solicitações de contadores agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [activeTenantUuid])

  useEffect(() => {
    void load()
  }, [load])

  const handleRevoke = useCallback(
    async (link: AccountingOfficeTenantLink) => {
      setActionError(null)
      setRevokingUuid(link.uuid)
      try {
        await tenantAccountingService.revokeAccessRequest(link.uuid)
        await load()
      } catch (err) {
        setActionError(getApiErrorMessage(err, 'Não foi possível revogar o acesso agora.'))
      } finally {
        setRevokingUuid(null)
      }
    },
    [load],
  )

  return (
    <Box>
      <PageHeader
        title="Acesso de contadores"
        subtitle="Aprove ou revogue o acesso do escritório de contabilidade aos dados da empresa e responda às pendências."
      />

      {actionError && (
        <Alert severity="error" variant="outlined" sx={{ mb: 2 }} onClose={() => setActionError(null)}>
          {actionError}
        </Alert>
      )}

      {isLoading && (
        <Stack spacing={1.5}>
          <Skeleton variant="rounded" height={96} />
          <Skeleton variant="rounded" height={96} />
        </Stack>
      )}

      {!isLoading && error && (
        <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>
          {error}
        </Alert>
      )}

      {!isLoading && !error && links.length === 0 && (
        <Paper variant="outlined" sx={{ p: 4, textAlign: 'center', ...ELEVATED_SURFACE_SX }}>
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 0.5 }}>Nenhuma solicitação de contador</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
            Quando um escritório de contabilidade solicitar acesso pelo CNPJ da sua empresa, a solicitação aparece aqui.
          </Typography>
        </Paper>
      )}

      {!isLoading && !error && links.length > 0 && (
        <Stack spacing={1.5}>
          {links.map((link) => {
            const office = link.accounting_office
            return (
              <Paper
                key={link.uuid}
                variant="outlined"
                sx={{ p: { xs: 2, sm: 2.5 }, ...ELEVATED_SURFACE_SX }}
              >
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                  <Box sx={{ minWidth: 0 }}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', gap: 0.75 }}>
                      <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>{office?.company_name ?? 'Escritório'}</Typography>
                      <AccountingStatusBadge status={link.status} />
                    </Stack>
                    {office?.cnpj && <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>CNPJ {office.cnpj}</Typography>}
                    {office?.responsible_name && (
                      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                        Responsável: {office.responsible_name} · {office.email}
                      </Typography>
                    )}
                    {link.requested_at && (
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                        Solicitado em {formatDateBR(link.requested_at)}
                      </Typography>
                    )}
                    {link.status === 'approved' && link.scopes.length > 0 && (
                      <Stack direction="row" spacing={0.5} sx={{ mt: 0.75, flexWrap: 'wrap', gap: 0.5 }}>
                        {link.scopes.map((scope) => (
                          <Typography
                            key={scope}
                            sx={{ fontSize: 11, color: 'var(--mk-muted)', background: 'var(--mk-surface-soft)', px: 0.75, py: 0.25, borderRadius: 1 }}
                          >
                            {scope}
                          </Typography>
                        ))}
                      </Stack>
                    )}
                  </Box>

                  <Stack direction="row" spacing={1} sx={{ alignItems: 'flex-start', flexWrap: 'wrap', gap: 1 }}>
                    <Button
                      size="small"
                      variant="outlined"
                      startIcon={<ChatOutlinedIcon fontSize="small" />}
                      onClick={() => setMessaging(link)}
                      sx={{ minHeight: UI_SIZE.compactControl }}
                    >
                      Pendências
                    </Button>

                    {link.status === 'pending' && (
                      <Button size="small" variant="contained" onClick={() => setApproving(link)} sx={{ minHeight: UI_SIZE.compactControl }}>
                        Aprovar
                      </Button>
                    )}

                    {link.status === 'approved' && (
                      <Button
                        size="small"
                        color="error"
                        variant="outlined"
                        onClick={() => void handleRevoke(link)}
                        disabled={revokingUuid === link.uuid}
                        sx={{ minHeight: UI_SIZE.compactControl }}
                      >
                        {revokingUuid === link.uuid ? 'Revogando…' : 'Revogar'}
                      </Button>
                    )}
                  </Stack>
                </Stack>
              </Paper>
            )
          })}
        </Stack>
      )}

      {approving && (
        <ApproveDialog
          link={approving}
          onClose={() => setApproving(null)}
          onApproved={() => {
            setApproving(null)
            void load()
          }}
        />
      )}

      {messaging && <MessagesDialog link={messaging} onClose={() => setMessaging(null)} />}
    </Box>
  )
}
