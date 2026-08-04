import { Alert, Box, Button, Chip, Paper, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as guestInviteService from '../../services/guestInviteService'
import type { GuestInvite } from '../../types/guestList'
import { getApiErrorMessage } from '../../types/api'
import { formatDateTimeBR } from '../../utils/format'

/** Autoatendimento público (sem login) de resgate de cortesia/convite — roadmap Fase 4. */
export function GuestInvitePage() {
  const navigate = useNavigate()
  const { token } = useParams<{ token: string }>()

  const [invite, setInvite] = useState<GuestInvite | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [document, setDocument] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  // Anti-bot básico (roadmap Fase 7) — `website` é honeypot (campo
  // invisível que só um bot preenche); `formRenderedAtRef` marca quando o
  // formulário carregou, para a checagem de tempo mínimo de preenchimento.
  // Ver App\Services\Security\AntiBotGuardService.
  const [website, setWebsite] = useState('')
  const formRenderedAtRef = useRef(new Date().toISOString())

  useEffect(() => {
    if (!token) return
    setIsLoading(true)
    setLoadError(null)
    guestInviteService
      .getGuestInvite(token)
      .then((result) => {
        setInvite(result)
        setName(result.name)
        setEmail(result.email)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Convite não encontrado ou inválido.')))
      .finally(() => setIsLoading(false))
  }, [token])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!token || !name.trim() || !email.trim()) return

    setSubmitError(null)
    setIsSubmitting(true)
    try {
      const result = await guestInviteService.redeemGuestInvite(token, {
        name: name.trim(),
        email: email.trim(),
        document: document.trim() || undefined,
        website,
        form_rendered_at: formRenderedAtRef.current,
      })
      navigate(`/rastreio/${result.sale_uuid}`)
    } catch (error) {
      setSubmitError(getApiErrorMessage(error, 'Não foi possível resgatar este convite agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        p: 2,
        background: 'var(--pt-page-background)',
      }}
    >
      <ThemeFab />
      <Paper elevation={0} sx={{ p: { xs: 3, sm: 4 }, maxWidth: 440, width: '100%', ...ELEVATED_SURFACE_SX }}>
        <Stack spacing={2.5} sx={{ alignItems: 'center', textAlign: 'center', mb: 2 }}>
          <Logo />
          <Typography sx={{ fontSize: 20, fontWeight: 700 }}>Seu convite</Typography>
        </Stack>

        {isLoading ? (
          <Skeleton variant="rounded" height={220} />
        ) : loadError ? (
          <Alert severity="error" variant="outlined">
            {loadError}
          </Alert>
        ) : invite ? (
          <Stack spacing={2.5}>
            <Box sx={{ textAlign: 'center' }}>
              <Typography sx={{ fontSize: 16, fontWeight: 700 }}>{invite.event.name}</Typography>
              {invite.session && (
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                  {invite.session.name}
                  {invite.session.starts_at ? ` · ${formatDateTimeBR(invite.session.starts_at)}` : ''}
                </Typography>
              )}
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>{invite.ticket_type.name}</Typography>
            </Box>

            {invite.is_redeemed ? (
              <Stack spacing={1.5} sx={{ alignItems: 'center' }}>
                <Chip label="Convite já resgatado" color="success" />
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', textAlign: 'center' }}>
                  Este convite já foi utilizado
                  {invite.redeemed_at ? ` em ${formatDateTimeBR(invite.redeemed_at)}` : ''}. Verifique seu e-mail para encontrar o ingresso.
                </Typography>
              </Stack>
            ) : (
              <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
                {submitError && (
                  <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>
                    {submitError}
                  </Alert>
                )}
                <Stack spacing={1.75}>
                  {/* Honeypot anti-bot (roadmap Fase 7) — invisível e fora da navegação por teclado/leitor de tela; só bots que preenchem tudo cegamente tendem a marcar este campo. */}
                  <TextField
                    label="Website"
                    name="website"
                    value={website}
                    onChange={(event) => setWebsite(event.target.value)}
                    tabIndex={-1}
                    autoComplete="off"
                    aria-hidden="true"
                    sx={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0, overflow: 'hidden' }}
                  />
                  <TextField label="Nome" value={name} onChange={(event) => setName(event.target.value)} fullWidth required />
                  <TextField
                    label="E-mail"
                    type="email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    fullWidth
                    required
                  />
                  <TextField label="Documento (opcional)" value={document} onChange={(event) => setDocument(event.target.value)} fullWidth />
                  <Button
                    type="submit"
                    variant="contained"
                    size="large"
                    disabled={isSubmitting || !name.trim() || !email.trim()}
                    sx={{ minHeight: 48 }}
                  >
                    {isSubmitting ? 'Resgatando…' : 'Resgatar meu ingresso'}
                  </Button>
                </Stack>
              </Box>
            )}
          </Stack>
        ) : null}
      </Paper>
    </Box>
  )
}
