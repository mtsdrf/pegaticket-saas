import { Alert, Box, Button, Chip, IconButton, Paper, Skeleton, Stack, TextField, Tooltip, Typography } from '@mui/material'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import * as guestListService from '../../services/guestListService'
import type { GuestList } from '../../types/guestList'
import { getApiErrorMessage } from '../../types/api'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatDateTimeBR } from '../../utils/format'

export function GuestListDetailPage() {
  const navigate = useNavigate()
  const { uuid } = useParams<{ uuid: string }>()
  const { hasPermission } = useAuth()
  const canAdd = hasPermission(ACCESS.eventsUpdate)

  const [guestList, setGuestList] = useState<GuestList | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [document, setDocument] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [copiedUuid, setCopiedUuid] = useState<string | null>(null)

  const load = useCallback(() => {
    if (!uuid) return
    setIsLoading(true)
    setLoadError(null)
    guestListService
      .getGuestList(uuid)
      .then(setGuestList)
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a lista agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  useEffect(load, [load])

  async function handleAddEntry() {
    if (!uuid || !name.trim() || !email.trim()) return
    setFormError(null)
    setIsSubmitting(true)
    try {
      await guestListService.addGuestListEntry(uuid, {
        name: name.trim(),
        email: email.trim(),
        document: document.trim() || undefined,
      })
      setName('')
      setEmail('')
      setDocument('')
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível adicionar o convidado agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function copyLink(url: string, entryUuid: string) {
    try {
      await navigator.clipboard.writeText(url)
      setCopiedUuid(entryUuid)
      setTimeout(() => setCopiedUuid((current) => (current === entryUuid ? null : current)), 2000)
    } catch {
      // clipboard indisponível (ex.: contexto não seguro) — sem crash, só não copia.
    }
  }

  return (
    <Box sx={PAGE_CONTAINER_SX}>
      <Button startIcon={<ArrowBackIcon />} color="inherit" onClick={() => navigate('/listas-de-convidados')} sx={{ ml: -1, mb: 1 }}>
        Voltar
      </Button>

      {isLoading ? (
        <Skeleton variant="rounded" height={300} />
      ) : loadError ? (
        <Alert severity="error" action={<Button onClick={load}>Tentar de novo</Button>}>
          {loadError}
        </Alert>
      ) : guestList ? (
        <Stack spacing={3}>
          <PageHeader
            title={guestList.name}
            subtitle={`${guestList.event.name} · ${guestList.ticket_type.name} · ${guestList.quantity_per_entry} ingresso(s) por convidado`}
          />

          {canAdd && (
            <Paper elevation={0} sx={{ p: 2.5, ...ELEVATED_SURFACE_SX }}>
              <Typography sx={{ fontWeight: 700, mb: 1.5 }}>Adicionar convidado</Typography>
              {formError && (
                <Alert severity="error" sx={{ mb: 1.5 }}>
                  {formError}
                </Alert>
              )}
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ maxWidth: 720 }}>
                <TextField label="Nome" value={name} onChange={(event) => setName(event.target.value)} fullWidth required />
                <TextField label="E-mail" type="email" value={email} onChange={(event) => setEmail(event.target.value)} fullWidth required />
                <TextField label="Documento (opcional)" value={document} onChange={(event) => setDocument(event.target.value)} fullWidth />
              </Stack>
              <Button
                variant="contained"
                disabled={isSubmitting || !name.trim() || !email.trim()}
                onClick={() => void handleAddEntry()}
                sx={{ mt: 1.5, minHeight: 44 }}
              >
                {isSubmitting ? 'Adicionando…' : 'Adicionar convidado'}
              </Button>
            </Paper>
          )}

          <Box>
            <Typography sx={{ fontWeight: 700, mb: 1.25 }}>Convidados ({guestList.entries?.length ?? 0})</Typography>
            <Stack spacing={1}>
              {guestList.entries?.map((entry) => (
                <Paper key={entry.uuid} elevation={0} sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
                  <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
                    <Box sx={{ minWidth: 0 }}>
                      <Typography sx={{ fontWeight: 600, fontSize: 14 }}>{entry.name}</Typography>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{entry.email}</Typography>
                      {entry.redeemed_at && (
                        <Typography sx={{ fontSize: 12, color: 'var(--pt-success)' }}>
                          Resgatado em {formatDateTimeBR(entry.redeemed_at)}
                        </Typography>
                      )}
                    </Box>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                      {entry.redeemed_at ? (
                        <Chip label="Resgatado" size="small" color="success" />
                      ) : (
                        <>
                          <Chip label="Pendente" size="small" />
                          <Tooltip title={copiedUuid === entry.uuid ? 'Copiado!' : 'Copiar link do convite'}>
                            <IconButton size="small" onClick={() => void copyLink(entry.invite_url, entry.uuid)}>
                              <ContentCopyOutlinedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                    </Stack>
                  </Stack>
                </Paper>
              ))}
              {(!guestList.entries || guestList.entries.length === 0) && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Nenhum convidado adicionado ainda.</Typography>
              )}
            </Stack>
          </Box>
        </Stack>
      ) : null}
    </Box>
  )
}
