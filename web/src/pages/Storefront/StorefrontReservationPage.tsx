import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import OpenInNewRoundedIcon from '@mui/icons-material/OpenInNewRounded'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import { Alert, Box, Button, Chip, Paper, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { Logo } from '../../components/ui/Logo'
import * as storefrontService from '../../services/storefrontService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { PublicReservationTenant, StorefrontTableReservationPayload } from '../../types/storefront'
import { formatDateTimeBR } from '../../utils/format'

function nextRoundedHour() {
  const date = new Date()
  date.setMinutes(0, 0, 0)
  date.setHours(date.getHours() + 1)
  const timezoneOffsetMs = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - timezoneOffsetMs).toISOString().slice(0, 16)
}

export function StorefrontReservationPage() {
  const navigate = useNavigate()
  const { slug } = useParams<{ slug: string }>()
  const [tenant, setTenant] = useState<PublicReservationTenant | null>(null)
  const [isLoading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isSubmitting, setSubmitting] = useState(false)
  const [form, setForm] = useState<StorefrontTableReservationPayload>({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    party_size: 2,
    scheduled_for: nextRoundedHour(),
    duration_minutes: 120,
    notes: '',
  })

  useEffect(() => {
    if (!slug) return
    let cancelled = false
    setLoading(true)
    storefrontService
      .getPublicReservationTenant(slug)
      .then((result) => {
        if (!cancelled) {
          setTenant(result)
          if (!result.allow_table_reservations) {
            setError('Esta loja não está recebendo reservas online no momento.')
          }
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(getApiErrorMessage(err, 'Não foi possível carregar a página de reservas agora.'))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug])

  async function handleSubmit() {
    if (!slug) return
    setSubmitting(true)
    setError(null)
    try {
      const reservation = await storefrontService.createPublicTableReservation(slug, {
        ...form,
        customer_phone: form.customer_phone?.trim() || null,
        customer_email: form.customer_email?.trim() || null,
        notes: form.notes?.trim() || null,
      })
      setSuccessMessage(
        `Reserva confirmada para ${formatDateTimeBR(reservation.scheduled_for)}${reservation.table ? ` na ${reservation.table.label}` : ''}.`,
      )
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível confirmar sua reserva agora.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--mk-page-background)',
        pb: 4,
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 760, px: { xs: 2, sm: 3 }, py: { xs: 3, sm: 4 } }}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
          <Button variant="text" onClick={() => navigate(slug && tenant?.storefront_enabled ? `/loja/${slug}` : '/')}>
            {tenant?.storefront_enabled ? 'Voltar para a loja' : 'Ir para o início'}
          </Button>
          <Stack spacing={0.5} sx={{ alignItems: 'center' }}>
            <Logo variant="mark" size={22} />
            <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)' }}>Reservas via Maskats</Typography>
          </Stack>
        </Stack>

        {isLoading ? (
          <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 3 }}>
            <Typography>Carregando reservas…</Typography>
          </Paper>
        ) : error && !tenant ? (
          <EmptyState
            icon={<SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />}
            title="Reserva indisponível"
            description={error}
          />
        ) : (
          <Stack spacing={2}>
            <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 } }}>
              <Stack spacing={1.25}>
                <Chip icon={<EventSeatOutlinedIcon fontSize="small" />} label="Reserva online de mesa" sx={{ width: 'fit-content' }} />
                <Typography sx={{ fontSize: { xs: 24, sm: 28 }, fontWeight: 700, lineHeight: 1.1 }}>
                  {tenant?.name ?? 'Loja'}
                </Typography>
                <Typography sx={{ color: 'var(--mk-muted)' }}>
                  Solicite sua mesa informando horário e quantidade de pessoas. A confirmação já sai com uma mesa compatível reservada para você.
                </Typography>
                {tenant && (
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25} sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}>
                    <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
                      Depois da confirmação, basta chegar no horário e avisar o nome da reserva no atendimento.
                    </Typography>
                    {tenant.storefront_enabled && slug ? (
                      <Button
                        variant="outlined"
                        size="small"
                        endIcon={<OpenInNewRoundedIcon fontSize="small" />}
                        onClick={() => navigate(`/loja/${slug}`)}
                      >
                        Abrir loja online
                      </Button>
                    ) : null}
                  </Stack>
                )}
              </Stack>
            </Paper>

            {successMessage ? <Alert severity="success">{successMessage}</Alert> : null}
            {error && tenant ? <Alert severity="error">{error}</Alert> : null}

            <Paper sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 3 } }}>
              <Stack spacing={2}>
                <TextField label="Seu nome" value={form.customer_name} onChange={(event) => setForm((current) => ({ ...current, customer_name: event.target.value }))} fullWidth />
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                  <TextField label="Telefone" value={form.customer_phone ?? ''} onChange={(event) => setForm((current) => ({ ...current, customer_phone: event.target.value }))} fullWidth />
                  <TextField label="E-mail" type="email" value={form.customer_email ?? ''} onChange={(event) => setForm((current) => ({ ...current, customer_email: event.target.value }))} fullWidth />
                </Stack>
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                  <TextField label="Quantidade de pessoas" type="number" value={form.party_size} onChange={(event) => setForm((current) => ({ ...current, party_size: Number(event.target.value || 1) }))} fullWidth />
                  <TextField label="Duração estimada (min)" type="number" value={form.duration_minutes ?? 120} onChange={(event) => setForm((current) => ({ ...current, duration_minutes: Number(event.target.value || 120) }))} fullWidth />
                </Stack>
                <TextField
                  label="Data e horário"
                  type="datetime-local"
                  value={form.scheduled_for}
                  onChange={(event) => setForm((current) => ({ ...current, scheduled_for: event.target.value }))}
                  fullWidth
                  slotProps={{ inputLabel: { shrink: true } }}
                />
                <TextField label="Observações" value={form.notes ?? ''} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} fullWidth multiline minRows={3} />
                <Button variant="contained" size="large" onClick={handleSubmit} disabled={isSubmitting || !tenant?.allow_table_reservations || !form.customer_name.trim()}>
                  {isSubmitting ? 'Confirmando reserva…' : 'Confirmar reserva'}
                </Button>
              </Stack>
            </Paper>
          </Stack>
        )}
      </Box>
    </Box>
  )
}
