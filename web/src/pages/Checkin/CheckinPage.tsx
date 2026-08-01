import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlinedIcon from '@mui/icons-material/CheckCircleOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import {
  Alert,
  Box,
  Button,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useState, type FormEvent, type ReactElement } from 'react'
import { PageHeader } from '../../components/layout/PageHeader'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { checkinTicket } from '../../services/ticketService'
import { getApiErrorMessage } from '../../types/api'
import {
  CHECKIN_RESULT_LABELS,
  checkinResultTone,
  TICKET_STATUS_LABELS,
  type CheckinResponse,
  type CheckinTicketPayload,
} from '../../types/ticket'

type SearchMode = 'code' | 'attendee_name' | 'attendee_document' | 'qr_token'

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

function ResultCard({ result }: { result: CheckinResponse }) {
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
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mt: 1 }}>
              {result.checkin.gate_name ? `Portão ${result.checkin.gate_name} · ` : ''}
              {new Date(result.checkin.checked_in_at).toLocaleString('pt-BR')}
              {result.checkin.operator?.name ? ` · Operador: ${result.checkin.operator.name}` : ''}
            </Typography>
          )}

          {!ticket && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 1 }}>
              Nenhum ingresso foi localizado com os dados informados.
            </Typography>
          )}
        </Box>
      </Stack>
    </Paper>
  )
}

/**
 * Tela de portaria (check-in) — busca manual por código/nome/documento do
 * ingresso ou colagem do token de QR Code. Leitura de QR Code por câmera
 * não foi incluída nesta rodada: o projeto não tem nenhuma lib de leitura
 * instalada (`jsQR`/`html5-qrcode`/`zxing`) e instalar dependência nova
 * está fora de escopo sem alinhamento prévio — fica documentado como
 * pendência. Quem tiver um leitor externo (app de celular, leitor USB) pode
 * colar o token lido no campo "Token do QR Code".
 */
export function CheckinPage() {
  const [searchMode, setSearchMode] = useState<SearchMode>('code')
  const [searchValue, setSearchValue] = useState('')
  const [gateName, setGateName] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [result, setResult] = useState<CheckinResponse | null>(null)

  const currentOption = SEARCH_MODE_OPTIONS.find((option) => option.value === searchMode)!

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = searchValue.trim()
    if (!trimmed) return

    setIsSubmitting(true)
    setErrorMessage(null)
    setResult(null)

    const payload: CheckinTicketPayload = {
      [searchMode]: trimmed,
      gate_name: gateName.trim() || undefined,
      device_info: navigator.userAgent,
    }

    try {
      const response = await checkinTicket(payload)
      setResult(response)
      if (response.result === 'valido' || response.result === 'ja_utilizado') {
        setSearchValue('')
      }
    } catch (error) {
      setErrorMessage(getApiErrorMessage(error, 'Não foi possível processar o check-in agora. Tente novamente.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box sx={PAGE_CONTAINER_SX}>
      <PageHeader title="Portaria" subtitle="Faça o check-in dos participantes na entrada do evento." />

      <Stack spacing={2.5} sx={{ maxWidth: 560 }}>
        <Alert severity="info" variant="outlined" icon={<QrCodeScannerOutlinedIcon fontSize="small" />}>
          Leitura de QR Code pela câmera ainda não está disponível nesta tela. Use um leitor externo e cole o token
          lido no campo "Token do QR Code", ou busque por código/nome/documento.
        </Alert>

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

              <TextField
                label="Portão / posto (opcional)"
                value={gateName}
                onChange={(event) => setGateName(event.target.value)}
                fullWidth
                size="small"
              />

              {errorMessage && (
                <Alert severity="error" variant="outlined" role="alert">
                  {errorMessage}
                </Alert>
              )}

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

        {result && <ResultCard result={result} />}

        {!result && !errorMessage && (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', color: 'var(--pt-muted)', px: 0.5 }}>
            <InfoOutlinedIcon sx={{ fontSize: 18 }} />
            <Typography sx={{ fontSize: 12.5 }}>
              O resultado do check-in aparece aqui: verde para entrada liberada, amarelo para ingresso já utilizado,
              vermelho para qualquer outro bloqueio.
            </Typography>
          </Stack>
        )}
      </Stack>
    </Box>
  )
}
