import AddIcon from '@mui/icons-material/Add'
import AttachFileOutlinedIcon from '@mui/icons-material/AttachFileOutlined'
import SupportAgentOutlinedIcon from '@mui/icons-material/SupportAgentOutlined'
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Link,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as helpRequestService from '../../services/helpRequestService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { HelpRequest } from '../../types/helpRequest'
import { formatDateTimeBR } from '../../utils/format'

const MAX_ATTACHMENT_MB = 5

function LoadingSkeleton() {
  return (
    <Stack spacing={1.5}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={96} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
      ))}
    </Stack>
  )
}

function TicketCard({ ticket }: { ticket: HelpRequest }) {
  return (
    <Paper
      variant="outlined"
      sx={{ p: 2.25, ...ELEVATED_SURFACE_SX }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5, mb: 0.5 }}>
        <Typography sx={{ fontWeight: 700, fontSize: 15.5, wordBreak: 'break-word' }}>{ticket.subject}</Typography>
        <Chip
          size="small"
          label={ticket.status === 'open' ? 'Aberto' : ticket.status}
          sx={{
            fontWeight: 600,
            flexShrink: 0,
            bgcolor: 'color-mix(in srgb, var(--pt-primary) 14%, transparent)',
            color: 'var(--pt-primary)',
          }}
        />
      </Stack>
      <Typography sx={{ fontSize: 14, color: 'var(--pt-text)', mb: 1, whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
        {ticket.description}
      </Typography>
      <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
          {ticket.created_at ? formatDateTimeBR(ticket.created_at) : ''}
        </Typography>
        {ticket.attachment_url ? (
          <Link
            href={ticket.attachment_url}
            target="_blank"
            rel="noopener noreferrer"
            sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, fontSize: 12.5 }}
          >
            <AttachFileOutlinedIcon sx={{ fontSize: 15 }} />
            {ticket.attachment_name ?? 'Anexo'}
          </Link>
        ) : null}
        {ticket.diagnostics ? (
          <Chip size="small" variant="outlined" label="Com diagnóstico técnico" sx={{ fontSize: 11.5 }} />
        ) : null}
      </Stack>
    </Paper>
  )
}

/** Central de chamados nativa (roadmap A4, item 17) — lista + abertura de chamado com diagnóstico técnico automático. */
export function SupportTicketsPage() {
  const { can } = useAccessControl()
  const canCreate = can(ACCESS.helpRequestsCreate)

  const [tickets, setTickets] = useState<HelpRequest[] | null>(null)
  const [isLoading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [isFormOpen, setFormOpen] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setTickets(await helpRequestService.listHelpRequests())
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar os chamados agora.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 900 }}>
      <PageHeader
        title="Central de chamados"
        subtitle="Abra um chamado com nosso suporte e acompanhe o histórico."
        action={
          canCreate ? (
            <Button variant="contained" startIcon={<AddIcon />} onClick={() => setFormOpen(true)} sx={{ minHeight: UI_SIZE.control }}>
              Novo chamado
            </Button>
          ) : undefined
        }
      />

      {isLoading ? <LoadingSkeleton /> : null}

      {!isLoading && error ? (
        <Alert severity="error" action={<Button color="inherit" size="small" onClick={() => void load()}>Tentar de novo</Button>}>
          {error}
        </Alert>
      ) : null}

      {!isLoading && !error && tickets && tickets.length === 0 ? (
        <EmptyState
          icon={<SupportAgentOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
          title="Nenhum chamado aberto ainda"
          description="Precisa de ajuda? Abra um chamado e nosso suporte responde por aqui."
          action={
            canCreate ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => setFormOpen(true)}>
                Abrir primeiro chamado
              </Button>
            ) : undefined
          }
        />
      ) : null}

      {!isLoading && !error && tickets && tickets.length > 0 ? (
        <Stack spacing={1.5}>
          {tickets.map((ticket) => (
            <TicketCard key={ticket.uuid} ticket={ticket} />
          ))}
        </Stack>
      ) : null}

      {isFormOpen ? (
        <NewSupportTicketDialog
          onClose={() => setFormOpen(false)}
          onCreated={(ticket) => {
            setTickets((current) => [ticket, ...(current ?? [])])
            setFormOpen(false)
          }}
        />
      ) : null}
    </Box>
  )
}

interface NewSupportTicketDialogProps {
  onClose: () => void
  onCreated: (ticket: HelpRequest) => void
}

function NewSupportTicketDialog({ onClose, onCreated }: NewSupportTicketDialogProps) {
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [subject, setSubject] = useState('')
  const [description, setDescription] = useState('')
  const [includeDiagnostics, setIncludeDiagnostics] = useState(true)
  const [attachment, setAttachment] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setSubmitting] = useState(false)

  function handleAttachmentChange(file: File | null) {
    setFormError(null)
    if (file && file.size > MAX_ATTACHMENT_MB * 1024 * 1024) {
      setFormError(`O anexo deve ter no máximo ${MAX_ATTACHMENT_MB}MB.`)
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }
    setAttachment(file)
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setSubmitting(true)

    try {
      const ticket = await helpRequestService.createHelpRequest(
        { subject: subject.trim(), description: description.trim(), include_diagnostics: includeDiagnostics },
        attachment,
      )
      onCreated(ticket)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível abrir o chamado agora.'))
      if (err instanceof ApiRequestError) setFieldErrors(err.errors)
      setSubmitting(false)
    }
  }

  return (
    <Dialog
      open
      onClose={isSubmitting ? undefined : onClose}
      fullWidth
      maxWidth="sm"
      slotProps={{ paper: { sx: { ...ELEVATED_SURFACE_SX } } }}
    >
      <DialogTitle sx={{ fontWeight: 700 }}>Novo chamado</DialogTitle>
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <DialogContent dividers>
          <Stack spacing={2.5}>
            {formError ? <Alert severity="error">{formError}</Alert> : null}

            <TextField
              label="Assunto"
              value={subject}
              onChange={(event) => setSubject(event.target.value)}
              error={Boolean(fieldErrors.subject)}
              helperText={fieldErrors.subject?.[0]}
              required
              autoFocus
              slotProps={{ htmlInput: { maxLength: 150 } }}
            />

            <TextField
              label="Descrição"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              error={Boolean(fieldErrors.description)}
              helperText={fieldErrors.description?.[0]}
              required
              multiline
              minRows={4}
              slotProps={{ htmlInput: { maxLength: 5000 } }}
            />

            <FormControlLabel
              control={
                <Checkbox
                  checked={includeDiagnostics}
                  onChange={(event) => setIncludeDiagnostics(event.target.checked)}
                />
              }
              label="Incluir diagnóstico técnico (plano, fila e auditoria recente — ajuda nosso suporte a entender o problema mais rápido)"
            />

            <Box>
              <input
                ref={fileInputRef}
                type="file"
                hidden
                accept=".pdf,.png,.jpg,.jpeg,.csv,.xlsx,.xls,.doc,.docx"
                onChange={(event) => handleAttachmentChange(event.target.files?.[0] ?? null)}
              />
              <Button
                variant="outlined"
                startIcon={<UploadFileOutlinedIcon />}
                onClick={() => fileInputRef.current?.click()}
                sx={{ minHeight: UI_SIZE.control }}
              >
                {attachment ? attachment.name : 'Anexar arquivo (opcional)'}
              </Button>
              {attachment ? (
                <Button size="small" color="inherit" onClick={() => handleAttachmentChange(null)} sx={{ ml: 1 }}>
                  Remover
                </Button>
              ) : null}
              {fieldErrors.attachment ? (
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-danger)', mt: 0.5 }}>
                  {fieldErrors.attachment[0]}
                </Typography>
              ) : null}
            </Box>
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, py: 2 }}>
          <Button onClick={onClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || !subject.trim() || !description.trim()}>
            {isSubmitting ? 'Enviando…' : 'Abrir chamado'}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  )
}
