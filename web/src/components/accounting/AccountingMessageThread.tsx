import AttachFileOutlinedIcon from '@mui/icons-material/AttachFileOutlined'
import InsertDriveFileOutlinedIcon from '@mui/icons-material/InsertDriveFileOutlined'
import SendOutlinedIcon from '@mui/icons-material/SendOutlined'
import {
  Alert,
  Box,
  Button,
  IconButton,
  Link as MuiLink,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useRef, useState, type FormEvent } from 'react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { AccountingMessage, AccountingMessageSender, CreateAccountingMessagePayload } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { formatDateBR, formatDateTimeBR } from '../../utils/format'

interface AccountingMessageThreadProps {
  messages: AccountingMessage[]
  isLoading: boolean
  loadError: string | null
  /** Qual lado está escrevendo — decide o alinhamento/rótulo das bolhas. */
  currentSender: AccountingMessageSender
  onSend: (payload: CreateAccountingMessagePayload) => Promise<void>
  /** Desabilita o formulário de envio (ex.: vínculo revogado no lado do tenant). */
  disabledReason?: string
}

const SENDER_LABEL: Record<AccountingMessageSender, string> = {
  tenant: 'Empresa',
  accounting_office: 'Contador',
}

/**
 * Central de pendências reutilizada pelos dois lados (contador e tenant). A
 * diferença é só `currentSender` (alinhamento) e o `onSend` (service de cada
 * lado). Mobile-first: bolhas empilhadas + formulário fixo abaixo.
 */
export function AccountingMessageThread({
  messages,
  isLoading,
  loadError,
  currentSender,
  onSend,
  disabledReason,
}: AccountingMessageThreadProps) {
  const [body, setBody] = useState('')
  const [dueDate, setDueDate] = useState('')
  const [attachment, setAttachment] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [sendError, setSendError] = useState<string | null>(null)
  const [isSending, setIsSending] = useState(false)
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSendError(null)
    setFieldErrors({})
    setIsSending(true)

    try {
      await onSend({ body, due_date: dueDate || null, attachment })
      setBody('')
      setDueDate('')
      setAttachment(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
    } catch (error) {
      setSendError(getApiErrorMessage(error, 'Não foi possível enviar a mensagem. Tente novamente.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSending(false)
    }
  }

  return (
    <Box>
      <Stack spacing={1.5} sx={{ mb: 2.5 }}>
        {isLoading && (
          <>
            <Skeleton variant="rounded" height={64} />
            <Skeleton variant="rounded" height={64} />
          </>
        )}

        {!isLoading && loadError && (
          <Alert severity="error" variant="outlined">
            {loadError}
          </Alert>
        )}

        {!isLoading && !loadError && messages.length === 0 && (
          <Box
            sx={{
              textAlign: 'center',
              py: 4,
              px: 2,
              ...SOFT_PANEL_SX,
              borderStyle: 'dashed',
              color: 'var(--mk-muted)',
            }}
          >
            <Typography sx={{ fontSize: 14 }}>Nenhuma mensagem ainda. Envie a primeira abaixo.</Typography>
          </Box>
        )}

        {!isLoading &&
          !loadError &&
          messages.map((message) => {
            const isMine = message.sender_type === currentSender
            return (
              <Box
                key={message.uuid}
                sx={{
                  alignSelf: isMine ? 'flex-end' : 'flex-start',
                  maxWidth: '85%',
                  ...SOFT_PANEL_SX,
                  background: isMine
                    ? 'color-mix(in srgb, var(--mk-primary) 12%, var(--mk-surface))'
                    : SOFT_PANEL_SX.background,
                  p: 1.5,
                }}
              >
                <Typography sx={{ fontSize: 11.5, fontWeight: 700, color: 'var(--mk-muted)', mb: 0.5 }}>
                  {SENDER_LABEL[message.sender_type]}
                  {message.created_at ? ` · ${formatDateTimeBR(message.created_at)}` : ''}
                </Typography>
                <Typography sx={{ fontSize: 14, whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                  {message.body}
                </Typography>

                {message.due_date && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-warning)', fontWeight: 600, mt: 0.75 }}>
                    Prazo: {formatDateBR(message.due_date)}
                  </Typography>
                )}

                {message.attachment_url && (
                  <MuiLink
                    href={message.attachment_url}
                    target="_blank"
                    rel="noopener"
                    sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, fontSize: 13, mt: 0.75 }}
                  >
                    <InsertDriveFileOutlinedIcon fontSize="small" />
                    {message.attachment_name ?? 'Anexo'}
                  </MuiLink>
                )}
              </Box>
            )
          })}
      </Stack>

      {disabledReason ? (
        <Alert severity="info" variant="outlined">
          {disabledReason}
        </Alert>
      ) : (
        <Box component="form" onSubmit={handleSubmit} noValidate>
          <Stack spacing={1.5}>
            {sendError && (
              <Alert severity="error" variant="outlined" role="alert">
                {sendError}
              </Alert>
            )}

            <TextField
              label="Nova mensagem"
              value={body}
              onChange={(event) => setBody(event.target.value)}
              error={Boolean(fieldErrors.body?.[0])}
              helperText={fieldErrors.body?.[0]}
              multiline
              minRows={2}
              fullWidth
              required
            />

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ alignItems: { sm: 'center' } }}>
              <TextField
                label="Prazo (opcional)"
                type="date"
                size="small"
                value={dueDate}
                onChange={(event) => setDueDate(event.target.value)}
                error={Boolean(fieldErrors.due_date?.[0])}
                helperText={fieldErrors.due_date?.[0]}
                slotProps={{ inputLabel: { shrink: true } }}
                sx={{ flex: 1, minWidth: { xs: '100%', sm: 180 } }}
              />

              <input
                ref={fileInputRef}
                type="file"
                hidden
                accept=".pdf,.png,.jpg,.jpeg,.csv,.xlsx,.xls,.doc,.docx"
                onChange={(event) => setAttachment(event.target.files?.[0] ?? null)}
              />
              <Button
                type="button"
                variant="outlined"
                size="small"
                startIcon={<AttachFileOutlinedIcon fontSize="small" />}
                onClick={() => fileInputRef.current?.click()}
                sx={{ minHeight: 44, whiteSpace: 'nowrap' }}
              >
                {attachment ? 'Trocar anexo' : 'Anexar'}
              </Button>
            </Stack>

            {attachment && (
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {attachment.name}
                </Typography>
                <IconButton size="small" aria-label="Remover anexo" onClick={() => {
                  setAttachment(null)
                  if (fileInputRef.current) fileInputRef.current.value = ''
                }}>
                  <Typography sx={{ fontSize: 16, lineHeight: 1 }}>×</Typography>
                </IconButton>
              </Stack>
            )}
            {fieldErrors.attachment?.[0] && (
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-danger)' }}>{fieldErrors.attachment[0]}</Typography>
            )}

            <Button
              type="submit"
              variant="contained"
              startIcon={<SendOutlinedIcon fontSize="small" />}
              disabled={isSending || body.trim().length === 0}
              sx={{ alignSelf: { sm: 'flex-start' } }}
            >
              {isSending ? 'Enviando…' : 'Enviar'}
            </Button>
          </Stack>
        </Box>
      )}
    </Box>
  )
}
