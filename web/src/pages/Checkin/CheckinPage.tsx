import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
import CameraAltOutlinedIcon from '@mui/icons-material/CameraAltOutlined'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlinedIcon from '@mui/icons-material/CheckCircleOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import KeyboardOutlinedIcon from '@mui/icons-material/KeyboardOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Chip,
  CircularProgress,
  MenuItem,
  Paper,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactElement } from 'react'
import { PageHeader } from '../../components/layout/PageHeader'
import { QrCodeScannerPanel } from '../../components/checkin/QrCodeScannerPanel'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { checkinContextStorageKey } from '../../constants/storage'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as eventGateService from '../../services/eventGateService'
import * as eventService from '../../services/eventService'
import * as eventSessionService from '../../services/eventSessionService'
import { checkinTicket, getCheckinSummary, getTicketCheckinHistory } from '../../services/ticketService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { enqueueCheckin, getQueuedCheckins, removeQueuedCheckin, type QueuedCheckin } from '../../utils/offlineCheckinQueue'
import {
  CHECKIN_RESULT_LABELS,
  checkinResultTone,
  type CheckinSummary,
  type CheckinSummaryEntry,
  TICKET_CHECKIN_ACCESS_TYPE_LABELS,
  TICKET_STATUS_LABELS,
  type CheckinResponse,
  type TicketCheckin,
  type CheckinTicketPayload,
} from '../../types/ticket'
import type { Event } from '../../types/event'
import type { EventSession } from '../../types/eventSession'

type SearchMode = 'code' | 'attendee_name' | 'attendee_document' | 'qr_token'
type InputMode = 'manual' | 'camera'
type CheckinContextStorage = {
  event_uuid: string
  event_name: string
  event_session_uuid: string
  gate_name: string
}

/** Intervalo de "pausa processando" após um resultado de check-in via câmera, pra não reler o mesmo QR em sequência. */
const CAMERA_RESCAN_COOLDOWN_MS = 2000
const SUMMARY_REFRESH_INTERVAL_MS = 15000

const SEARCH_MODE_OPTIONS: { value: SearchMode; label: string; placeholder: string }[] = [
  { value: 'code', label: 'Código do ingresso', placeholder: 'Ex.: A1B2C3D4' },
  { value: 'attendee_name', label: 'Nome do participante', placeholder: 'Nome completo' },
  { value: 'attendee_document', label: 'Documento (CPF/RG)', placeholder: 'Somente números' },
  { value: 'qr_token', label: 'Token do QR Code (colado)', placeholder: 'Cole aqui o conteúdo lido do QR Code' },
]

const TONE_STYLES: Record<'success' | 'warning' | 'error', { bg: string; fg: string; icon: ReactElement }> = {
  success: {
    bg: 'color-mix(in srgb, var(--pt-success) 14%, transparent)',
    fg: 'var(--pt-success)',
    icon: <CheckCircleOutlinedIcon sx={{ fontSize: 40 }} />,
  },
  warning: {
    bg: 'color-mix(in srgb, var(--pt-warning) 16%, transparent)',
    fg: 'var(--pt-warning)',
    icon: <WarningAmberOutlinedIcon sx={{ fontSize: 40 }} />,
  },
  error: {
    bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)',
    fg: 'var(--pt-danger)',
    icon: <CancelOutlinedIcon sx={{ fontSize: 40 }} />,
  },
}

function ResultCard({ result, history }: { result: CheckinResponse; history: TicketCheckin[] }) {
  const tone = checkinResultTone(result.result)
  const toneStyle = TONE_STYLES[tone]
  const ticket = result.ticket

  return (
    <Paper
      elevation={0}
      sx={{
        p: 2.5,
        ...ELEVATED_SURFACE_SX,
        bgcolor: toneStyle.bg,
        border: '1px solid',
        borderColor: toneStyle.fg,
      }}
    >
      <Stack direction="row" spacing={1.5} sx={{ alignItems: 'flex-start' }}>
        <Box sx={{ color: toneStyle.fg, display: 'flex', flexShrink: 0 }}>{toneStyle.icon}</Box>
        <Box sx={{ minWidth: 0, flex: 1 }}>
          <Typography sx={{ fontWeight: 700, fontSize: 18, color: toneStyle.fg, lineHeight: 1.25 }}>
            {CHECKIN_RESULT_LABELS[result.result]}
          </Typography>

          {ticket && (
            <Stack spacing={0.5} sx={{ mt: 1.5 }}>
              {ticket.attendee_name && (
                <Typography sx={{ fontSize: 15, fontWeight: 600, wordBreak: 'break-word' }}>
                  {ticket.attendee_name}
                </Typography>
              )}
              {ticket.event?.name && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Evento: {ticket.event.name}</Typography>
              )}
              {ticket.session?.name && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Sessão: {ticket.session.name}</Typography>
              )}
              {ticket.ticket_type?.name && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                  Tipo de ingresso: {ticket.ticket_type.name}
                </Typography>
              )}
              {ticket.seat && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', display: 'flex', alignItems: 'center', gap: 0.5 }}>
                  <EventSeatOutlinedIcon sx={{ fontSize: 16 }} />
                  {ticket.seat.label}
                  {ticket.seat.sector_name ? ` — ${ticket.seat.sector_name}` : ''}
                </Typography>
              )}
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Código: {ticket.code} · Status: {TICKET_STATUS_LABELS[ticket.status] ?? ticket.status}
              </Typography>
            </Stack>
          )}

          {result.checkin && (
            <Stack spacing={0.75} sx={{ mt: 1 }}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                <Chip size="small" label={TICKET_CHECKIN_ACCESS_TYPE_LABELS[result.checkin.access_type]} sx={{ fontWeight: 700 }} />
                {result.checkin.reason && <Chip size="small" variant="outlined" label={result.checkin.reason} />}
              </Stack>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                {result.checkin.gate_name ? `Portão ${result.checkin.gate_name} · ` : ''}
                {new Date(result.checkin.checked_in_at).toLocaleString('pt-BR')}
                {result.checkin.operator?.name ? ` · Operador: ${result.checkin.operator.name}` : ''}
              </Typography>
            </Stack>
          )}

          {!ticket && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 1 }}>
              Nenhum ingresso foi localizado com os dados informados.
            </Typography>
          )}

          {history.length > 0 && (
            <Box sx={{ mt: 2 }}>
              <Typography sx={{ fontSize: 12, fontWeight: 700, color: 'var(--pt-muted)', mb: 1 }}>
                Últimos acessos
              </Typography>
              <Stack spacing={0.75}>
                {history.slice(0, 4).map((item) => (
                  <Box
                    key={item.uuid}
                    sx={{
                      p: 1,
                      borderRadius: 2,
                      bgcolor: 'rgba(255,255,255,0.35)',
                      border: '1px solid rgba(0,0,0,0.06)',
                    }}
                  >
                    <Typography sx={{ fontSize: 12.5, fontWeight: 600 }}>
                      {CHECKIN_RESULT_LABELS[item.result]} · {TICKET_CHECKIN_ACCESS_TYPE_LABELS[item.access_type]}
                    </Typography>
                    <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                      {new Date(item.checked_in_at).toLocaleString('pt-BR')}
                      {item.gate_name ? ` · Portão ${item.gate_name}` : ''}
                      {item.operator?.name ? ` · ${item.operator.name}` : ''}
                    </Typography>
                    {item.reason && (
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                        Motivo: {item.reason}
                      </Typography>
                    )}
                  </Box>
                ))}
              </Stack>
            </Box>
          )}
        </Box>
      </Stack>
    </Paper>
  )
}

function selectedContextMismatch(
  result: CheckinResponse | null,
  selectedEventUuid: string,
  selectedSessionUuid: string,
): string | null {
  if (!result?.ticket) return null

  if (selectedSessionUuid && result.ticket.session?.uuid !== selectedSessionUuid) {
    return 'O ingresso lido não pertence à sessão selecionada nesta portaria.'
  }

  if (selectedEventUuid && result.ticket.event?.uuid !== selectedEventUuid) {
    return 'O ingresso lido não pertence ao evento selecionado nesta portaria.'
  }

  return null
}

function createEmptySummary(): CheckinSummary {
  return {
    filters: {
      event_uuid: null,
      event_session_uuid: null,
      gate_name: null,
    },
    counters: {
      total: 0,
      granted: 0,
      warning: 0,
      blocked: 0,
    },
    recent: [],
  }
}

/**
 * Tela de portaria (check-in) — dois modos de entrada: leitura de QR Code
 * pela câmera (`html5-qrcode`, API `Html5Qrcode` de baixo nível) ou busca
 * manual por código/nome/documento/token colado. A câmera é o caminho
 * rápido pra fila de entrada; o manual continua como fallback pra quando a
 * câmera não está disponível (sem permissão, sem hardware) ou o operador
 * prefere digitar/colar um token lido por leitor externo.
 */
export function CheckinPage() {
  const [inputMode, setInputMode] = useState<InputMode>('camera')
  const [searchMode, setSearchMode] = useState<SearchMode>('code')
  const [searchValue, setSearchValue] = useState('')
  const [gateName, setGateName] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [result, setResult] = useState<CheckinResponse | null>(null)
  const [history, setHistory] = useState<TicketCheckin[]>([])
  const [cameraCooldown, setCameraCooldown] = useState(false)
  const [reentryReason, setReentryReason] = useState('')
  const [isLoadingHistory, setIsLoadingHistory] = useState(false)
  const [eventOptions, setEventOptions] = useState<Event[]>([])
  const [sessionOptions, setSessionOptions] = useState<EventSession[]>([])
  const [gateOptions, setGateOptions] = useState<string[]>([])
  const [selectedEventUuid, setSelectedEventUuid] = useState('')
  const [selectedSessionUuid, setSelectedSessionUuid] = useState('')
  const [pendingSessionUuid, setPendingSessionUuid] = useState('')
  const [isLoadingContext, setIsLoadingContext] = useState(true)
  const [isLoadingSessions, setIsLoadingSessions] = useState(false)
  const [contextError, setContextError] = useState<string | null>(null)
  const [summary, setSummary] = useState<CheckinSummary>(createEmptySummary)
  const [isLoadingSummary, setIsLoadingSummary] = useState(true)
  const [summaryError, setSummaryError] = useState<string | null>(null)
  const [queuedCheckins, setQueuedCheckins] = useState<QueuedCheckin[]>(() => getQueuedCheckins())
  const [isSyncingQueue, setIsSyncingQueue] = useState(false)
  const [offlineNotice, setOfflineNotice] = useState<string | null>(null)
  const summaryData = summary ?? createEmptySummary()

  const currentOption = SEARCH_MODE_OPTIONS.find((option) => option.value === searchMode)!
  const cooldownTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    return () => {
      if (cooldownTimeoutRef.current) clearTimeout(cooldownTimeoutRef.current)
    }
  }, [])

  useEffect(() => {
    try {
      const tenantUuid = localStorage.getItem('pegaticket.active_tenant_uuid')
      const raw = localStorage.getItem(checkinContextStorageKey(tenantUuid))
      if (!raw) return
      const parsed = JSON.parse(raw) as Partial<CheckinContextStorage>
      setSelectedEventUuid(parsed.event_uuid ?? '')
      setPendingSessionUuid(parsed.event_session_uuid ?? '')
      setGateName(parsed.gate_name ?? '')
    } catch {
      // segue sem persistência se localStorage/JSON falhar.
    }
  }, [])

  useEffect(() => {
    setIsLoadingContext(true)
    setContextError(null)

    eventService
      .listEvents({ per_page: 100, status: 'publicado', sort_by: 'starts_at', sort_dir: 'asc' })
      .then((response) => {
        setEventOptions(response.items)
      })
      .catch((error) => {
        setContextError(getApiErrorMessage(error, 'Não foi possível carregar eventos para a portaria agora.'))
      })
      .finally(() => setIsLoadingContext(false))
  }, [])

  useEffect(() => {
    if (!selectedEventUuid) {
      setSessionOptions([])
      setSelectedSessionUuid('')
      return
    }

    setIsLoadingSessions(true)
    setSelectedSessionUuid('')

    eventSessionService
      .listEventSessions(selectedEventUuid, { per_page: 100, sort_by: 'starts_at', sort_dir: 'asc' })
      .then((response) => {
        const availableSessions = response.items.filter((session) => session.status !== 'cancelado')
        setSessionOptions(availableSessions)
        if (pendingSessionUuid && availableSessions.some((session) => session.uuid === pendingSessionUuid)) {
          setSelectedSessionUuid(pendingSessionUuid)
          setPendingSessionUuid('')
        }
      })
      .catch(() => setSessionOptions([]))
      .finally(() => setIsLoadingSessions(false))
  }, [pendingSessionUuid, selectedEventUuid])

  useEffect(() => {
    if (!selectedEventUuid) {
      setGateOptions([])
      return
    }

    eventGateService
      .listEventGates(selectedEventUuid, { is_active: true })
      .then((response) => setGateOptions(response.items.map((gate) => gate.name)))
      .catch(() => setGateOptions([]))
  }, [selectedEventUuid])

  useEffect(() => {
    try {
      const tenantUuid = localStorage.getItem('pegaticket.active_tenant_uuid')
      const selectedEvent = eventOptions.find((event) => event.uuid === selectedEventUuid)
      const payload: CheckinContextStorage = {
        event_uuid: selectedEventUuid,
        event_name: selectedEvent?.name ?? '',
        event_session_uuid: selectedSessionUuid,
        gate_name: gateName,
      }
      localStorage.setItem(checkinContextStorageKey(tenantUuid), JSON.stringify(payload))
    } catch {
      // localStorage indisponível — a tela segue funcional sem persistência.
    }
  }, [eventOptions, gateName, selectedEventUuid, selectedSessionUuid])

  const mismatchMessage = selectedContextMismatch(result, selectedEventUuid, selectedSessionUuid)

  const loadSummary = useCallback(async () => {
    setSummaryError(null)

    try {
      const operationalSummary = await getCheckinSummary({
        event_uuid: selectedEventUuid || undefined,
        event_session_uuid: selectedSessionUuid || undefined,
        gate_name: gateName.trim() || undefined,
        limit: 5,
      })

      setSummary(operationalSummary ?? createEmptySummary())
    } catch (error) {
      setSummary((current) => current ?? createEmptySummary())
      setSummaryError(getApiErrorMessage(error, 'Não foi possível carregar o resumo operacional agora.'))
    } finally {
      setIsLoadingSummary(false)
    }
  }, [gateName, selectedEventUuid, selectedSessionUuid])

  useEffect(() => {
    setIsLoadingSummary(true)
    void loadSummary()

    const intervalId = window.setInterval(() => {
      void loadSummary()
    }, SUMMARY_REFRESH_INTERVAL_MS)

    return () => window.clearInterval(intervalId)
  }, [loadSummary])

  // Sincroniza a fila offline (roadmap Fase 2 — "modo offline controlado
  // para acesso"): reenvia em ordem, um por vez, parando no primeiro erro
  // que não seja de rede (ex.: ticket já usado por outra portaria enquanto
  // offline — fica na fila pra revisão manual, não trava a sincronização
  // dos itens seguintes).
  const syncQueuedCheckins = useCallback(async () => {
    if (isSyncingQueue) return
    const queue = getQueuedCheckins()
    if (queue.length === 0) return

    setIsSyncingQueue(true)
    for (const item of queue) {
      try {
        await checkinTicket(item.payload)
        removeQueuedCheckin(item.id)
      } catch (error) {
        if (!(error instanceof ApiRequestError)) {
          // Ainda sem rede de verdade — para aqui, tenta de novo depois.
          break
        }
        // Erro de negócio (ex.: já utilizado) — remove da fila mesmo
        // assim, o servidor já tem o estado real do ticket.
        removeQueuedCheckin(item.id)
      }
    }
    setQueuedCheckins(getQueuedCheckins())
    setIsSyncingQueue(false)
    void loadSummary()
  }, [isSyncingQueue, loadSummary])

  useEffect(() => {
    const handleOnline = () => void syncQueuedCheckins()
    window.addEventListener('online', handleOnline)
    if (navigator.onLine) void syncQueuedCheckins()
    return () => window.removeEventListener('online', handleOnline)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function runCheckin(payload: CheckinTicketPayload) {
    setIsSubmitting(true)
    setErrorMessage(null)
    setOfflineNotice(null)
    setResult(null)
    setHistory([])

    const fullPayload: CheckinTicketPayload = {
      ...payload,
      event_uuid: selectedEventUuid || undefined,
      event_session_uuid: selectedSessionUuid || undefined,
      gate_name: gateName.trim() || undefined,
      device_info: navigator.userAgent,
    }

    try {
      const response = await checkinTicket(fullPayload)
      setResult(response)
      setReentryReason('')
      void loadSummary()

      if (response.ticket?.uuid) {
        setIsLoadingHistory(true)
        getTicketCheckinHistory(response.ticket.uuid)
          .then((items) => setHistory(items))
          .catch(() => setHistory([]))
          .finally(() => setIsLoadingHistory(false))
      }

      if (response.result === 'valido' || response.result === 'ja_utilizado') {
        setSearchValue('')
      }
    } catch (error) {
      if (!(error instanceof ApiRequestError)) {
        enqueueCheckin(fullPayload)
        setQueuedCheckins(getQueuedCheckins())
        setErrorMessage(null)
        setOfflineNotice('Sem conexão — check-in registrado offline e será sincronizado automaticamente.')
        setSearchValue('')
      } else {
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível processar o check-in agora. Tente novamente.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleAuthorizeReentry() {
    if (!result?.ticket?.code) return

    await runCheckin({
      code: result.ticket.code,
      allow_reentry: true,
      reason: reentryReason.trim() || undefined,
    })
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = searchValue.trim()
    if (!trimmed) return
    await runCheckin({ [searchMode]: trimmed })
  }

  async function handleQrDetected(decodedText: string) {
    const trimmed = decodedText.trim()
    if (!trimmed || isSubmitting || cameraCooldown) return

    await runCheckin({ qr_token: trimmed })

    setCameraCooldown(true)
    if (cooldownTimeoutRef.current) clearTimeout(cooldownTimeoutRef.current)
    cooldownTimeoutRef.current = setTimeout(() => setCameraCooldown(false), CAMERA_RESCAN_COOLDOWN_MS)
  }

  function handleInputModeChange(_event: unknown, next: InputMode | null) {
    if (!next || next === inputMode) return
    setInputMode(next)
    setSearchValue('')
    setErrorMessage(null)
  }

  return (
    <Box sx={PAGE_CONTAINER_SX}>
      <PageHeader title="Portaria" subtitle="Faça o check-in dos participantes na entrada do evento." />

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'minmax(0, 1fr)', xl: 'minmax(0, 1.4fr) minmax(360px, 0.9fr)' },
          gap: 2.5,
          alignItems: 'start',
        }}
      >
        <Stack spacing={2.5} sx={{ minWidth: 0 }}>
          {offlineNotice && (
            <Alert severity="info" onClose={() => setOfflineNotice(null)}>
              {offlineNotice}
            </Alert>
          )}
          {queuedCheckins.length > 0 && (
            <Alert
              severity="warning"
              action={
                <Button color="inherit" size="small" disabled={isSyncingQueue} onClick={() => void syncQueuedCheckins()}>
                  {isSyncingQueue ? 'Sincronizando…' : 'Sincronizar agora'}
                </Button>
              }
            >
              {queuedCheckins.length} check-in(s) pendente(s) de sincronização.
            </Alert>
          )}

          <ToggleButtonGroup
            value={inputMode}
            exclusive
            onChange={handleInputModeChange}
            fullWidth
            sx={{
              '& .MuiToggleButton-root': {
                minHeight: UI_SIZE.control,
                textTransform: 'none',
                fontWeight: 600,
                gap: 0.75,
              },
            }}
          >
            <ToggleButton value="camera">
              <CameraAltOutlinedIcon fontSize="small" />
              Ler QR Code (câmera)
            </ToggleButton>
            <ToggleButton value="manual">
              <KeyboardOutlinedIcon fontSize="small" />
              Buscar manualmente
            </ToggleButton>
          </ToggleButtonGroup>

          <Autocomplete
            freeSolo
            size="small"
            fullWidth
            options={gateOptions}
            inputValue={gateName}
            onInputChange={(_event, value) => setGateName(value)}
            renderInput={(params) => (
              <TextField
                {...params}
                label="Portão / posto (opcional)"
                helperText={
                  selectedEventUuid && gateOptions.length === 0
                    ? 'Este evento não tem portarias cadastradas — pode digitar livremente.'
                    : 'Selecione uma portaria cadastrada ou digite um valor livre.'
                }
              />
            )}
          />

          {inputMode === 'camera' ? (
            <QrCodeScannerPanel
              paused={isSubmitting || cameraCooldown}
              onDetected={(text) => void handleQrDetected(text)}
              onSwitchToManual={() => handleInputModeChange(null, 'manual')}
            />
          ) : (
            <Paper elevation={0} sx={{ p: 2.5, ...ELEVATED_SURFACE_SX }}>
              <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
                <Stack spacing={2}>
                  <TextField
                    select
                    label="Buscar por"
                    value={searchMode}
                    onChange={(event) => {
                      setSearchMode(event.target.value as SearchMode)
                      setSearchValue('')
                    }}
                    fullWidth
                    size="small"
                  >
                    {SEARCH_MODE_OPTIONS.map((option) => (
                      <MenuItem key={option.value} value={option.value}>
                        {option.label}
                      </MenuItem>
                    ))}
                  </TextField>

                  <TextField
                    label={currentOption.label}
                    placeholder={currentOption.placeholder}
                    value={searchValue}
                    onChange={(event) => setSearchValue(event.target.value)}
                    fullWidth
                    autoFocus
                    slotProps={{
                      input: {
                        startAdornment: (
                          <Box sx={{ display: 'flex', mr: 1, color: 'var(--pt-muted)' }}>
                            {searchMode === 'attendee_name' || searchMode === 'attendee_document' ? (
                              <BadgeOutlinedIcon fontSize="small" />
                            ) : (
                              <QrCodeScannerOutlinedIcon fontSize="small" />
                            )}
                          </Box>
                        ),
                      },
                    }}
                  />

                  <Button
                    type="submit"
                    variant="contained"
                    size="large"
                    disabled={isSubmitting || !searchValue.trim()}
                    sx={{ minHeight: UI_SIZE.controlLarge }}
                  >
                    {isSubmitting ? 'Verificando…' : 'Fazer check-in'}
                  </Button>
                </Stack>
              </Box>
            </Paper>
          )}

          {errorMessage && (
            <Alert severity="error" variant="outlined" role="alert">
              {errorMessage}
            </Alert>
          )}

          {mismatchMessage && (
            <Alert severity="warning" variant="outlined">
              {mismatchMessage}
            </Alert>
          )}

          {result && (
            <Stack spacing={1.5}>
              <ResultCard result={result} history={history} />

              {(result.result === 'ja_utilizado'
                || result.result === 'reentrada_nao_permitida'
                || result.result === 'reentrada_limite_excedido'
                || result.result === 'reentrada_intervalo_nao_atingido') && result.ticket && (
                <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
                  <Stack spacing={1.5}>
                    <Typography sx={{ fontWeight: 700 }}>Ação de reentrada</Typography>
                    <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                      Se a operação decidir liberar novo acesso, registre o motivo e autorize a reentrada sem sair da portaria.
                    </Typography>
                    <TextField
                      label="Motivo da reentrada"
                      value={reentryReason}
                      onChange={(event) => setReentryReason(event.target.value)}
                      placeholder="Ex.: saiu para fumar, retorno autorizado pela produção, ajuste operacional"
                      fullWidth
                      size="small"
                    />
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                      <Button
                        variant="contained"
                        onClick={() => void handleAuthorizeReentry()}
                        disabled={isSubmitting}
                        startIcon={<BadgeOutlinedIcon />}
                      >
                        Autorizar reentrada
                      </Button>
                      {isLoadingHistory && (
                        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                          Atualizando histórico...
                        </Typography>
                      )}
                    </Stack>
                  </Stack>
                </Paper>
              )}
            </Stack>
          )}

          {!result && !errorMessage && (
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', color: 'var(--pt-muted)', px: 0.5 }}>
              <InfoOutlinedIcon sx={{ fontSize: 18 }} />
              <Typography sx={{ fontSize: 12.5 }}>
                O resultado do check-in aparece aqui: verde para entrada liberada, amarelo para ingresso já utilizado,
                vermelho para bloqueios e com histórico imediato para apoiar a decisão da portaria.
              </Typography>
            </Stack>
          )}
        </Stack>

        <Stack spacing={2.5} sx={{ minWidth: 0 }}>
          <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
            <Stack spacing={1.5}>
              <Typography sx={{ fontWeight: 700 }}>Resumo do turno</Typography>
              {summaryError && <Alert severity="warning" variant="outlined">{summaryError}</Alert>}
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', sm: 'repeat(4, minmax(0, 1fr))', xl: 'repeat(2, minmax(0, 1fr))' },
                  gap: 1,
                }}
              >
                {[
                  { label: 'Leituras', value: summaryData.counters.total, color: 'var(--pt-text)' },
                  { label: 'Liberados', value: summaryData.counters.granted, color: 'var(--pt-success)' },
                  { label: 'Atenção', value: summaryData.counters.warning, color: 'var(--pt-warning)' },
                  { label: 'Bloqueados', value: summaryData.counters.blocked, color: 'var(--pt-danger)' },
                ].map((item) => (
                  <Box
                    key={item.label}
                    sx={{
                      p: 1.25,
                      borderRadius: 2,
                      border: '1px solid var(--pt-divider)',
                      backgroundColor: 'var(--pt-surface-soft)',
                    }}
                  >
                    <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{item.label}</Typography>
                    <Typography sx={{ fontSize: 24, fontWeight: 800, color: item.color }}>{item.value}</Typography>
                  </Box>
                ))}
              </Box>

              {isLoadingSummary && summaryData.recent.length === 0 && (
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', color: 'var(--pt-muted)' }}>
                  <CircularProgress size={14} />
                  <Typography sx={{ fontSize: 12.5 }}>Carregando visão compartilhada da portaria...</Typography>
                </Stack>
              )}

              {summaryData.recent.length > 0 && (
                <Box>
                  <Typography sx={{ fontSize: 12, fontWeight: 700, color: 'var(--pt-muted)', mb: 1 }}>
                    Últimas leituras compartilhadas
                  </Typography>
                  <Stack spacing={0.75}>
                    {summaryData.recent.map((entry: CheckinSummaryEntry) => (
                      <Box
                        key={entry.uuid}
                        sx={{
                          p: 1,
                          borderRadius: 2,
                          border: '1px solid var(--pt-divider)',
                          backgroundColor: 'var(--pt-surface-soft)',
                        }}
                      >
                        <Typography sx={{ fontSize: 12.5, fontWeight: 700 }}>
                          {CHECKIN_RESULT_LABELS[entry.result]}
                        </Typography>
                        <Typography sx={{ fontSize: 12.5 }}>
                          {entry.ticket?.attendee_name || entry.ticket?.code || 'Ingresso não localizado'}
                        </Typography>
                        <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                          {[entry.ticket?.event?.name, entry.ticket?.session?.name].filter(Boolean).join(' · ') || 'Sem contexto'}
                          {entry.checked_in_at ? ` · ${new Date(entry.checked_in_at).toLocaleTimeString('pt-BR')}` : ''}
                          {entry.gate_name ? ` · ${entry.gate_name}` : ''}
                        </Typography>
                      </Box>
                    ))}
                  </Stack>
                </Box>
              )}
            </Stack>
          </Paper>

          <Paper elevation={0} sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
            <Stack spacing={1.5}>
              <Typography sx={{ fontWeight: 700 }}>Contexto da portaria</Typography>
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                Selecionar evento e sessão ajuda a impedir leitura válida no acesso errado.
              </Typography>

              {contextError && <Alert severity="warning" variant="outlined">{contextError}</Alert>}

              <LocalAutocomplete
                label="Evento"
                options={eventOptions}
                value={eventOptions.find((event) => event.uuid === selectedEventUuid) ?? null}
                onChange={(option) => setSelectedEventUuid(option?.uuid ?? '')}
                getOptionLabel={(option) => option.name}
                getOptionKey={(option) => option.uuid}
                fullWidth
                loading={isLoadingContext}
                helperText="Opcional, mas recomendado em operação com múltiplos eventos."
              />

              <LocalAutocomplete
                label="Sessão"
                options={sessionOptions}
                value={sessionOptions.find((session) => session.uuid === selectedSessionUuid) ?? null}
                onChange={(option) => setSelectedSessionUuid(option?.uuid ?? '')}
                getOptionLabel={(option) => option.name || new Date(option.starts_at).toLocaleString('pt-BR')}
                getOptionKey={(option) => option.uuid}
                fullWidth
                disabled={!selectedEventUuid || isLoadingSessions || sessionOptions.length === 0}
                loading={isLoadingSessions}
                helperText={
                  !selectedEventUuid
                    ? 'Selecione um evento primeiro.'
                    : sessionOptions.length === 0
                      ? 'Este evento não possui sessões ativas cadastradas.'
                      : 'Opcional. Se informada, o ingresso precisa pertencer exatamente a esta sessão.'
                }
              />

              {isLoadingSessions && (
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', color: 'var(--pt-muted)' }}>
                  <CircularProgress size={14} />
                  <Typography sx={{ fontSize: 12.5 }}>Carregando sessões…</Typography>
                </Stack>
              )}
            </Stack>
          </Paper>
        </Stack>
      </Box>
    </Box>
  )
}
