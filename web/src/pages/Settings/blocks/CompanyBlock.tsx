import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import LaunchOutlinedIcon from '@mui/icons-material/LaunchOutlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Alert, Box, Button, Divider, Paper, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { QRCodeCanvas } from 'qrcode.react'
import { ImageUploadField } from '../../../components/shared/ImageUploadField'
import { ACCESS } from '../../../access/requirements'
import { useAuth } from '../../../hooks/useAuth'
import * as tenantProfileService from '../../../services/tenantProfileService'
import { CARD_EQUAL_HEIGHT_SX, CLAMP_TEXT_3_SX, UI_RADIUS, UI_SIZE } from '../../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../../types/api'
import type { TenantProfile } from '../../../types/tenantProfile'
import { formatCpfCnpj, normalizeCpfCnpj } from '../../../utils/cpfCnpj'

function PublicLinkCard({
  icon,
  title,
  description,
  url,
  onCopy,
  qrCodeFileName,
}: {
  icon: ReactNode
  title: string
  description: string
  url: string
  onCopy: (value: string) => void
  /** Quando informado, exibe o QR Code do link com opção de baixar em PNG. */
  qrCodeFileName?: string
}) {
  const qrCanvasWrapperRef = useRef<HTMLDivElement>(null)

  function handleDownloadQrCode() {
    const canvas = qrCanvasWrapperRef.current?.querySelector('canvas')
    if (!canvas) return
    const link = document.createElement('a')
    link.download = `${qrCodeFileName ?? 'qr-code'}.png`
    link.href = canvas.toDataURL('image/png')
    link.click()
  }

  return (
    <Paper
      variant="outlined"
      sx={{
        p: { xs: 1.75, sm: 2 },
        borderRadius: UI_RADIUS.lg,
        background: 'color-mix(in srgb, var(--pt-surface) 82%, var(--pt-primary) 4%)',
        ...CARD_EQUAL_HEIGHT_SX,
      }}
    >
      <Stack spacing={1.25}>
        <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center' }}>
          <Box
            sx={{
              width: UI_SIZE.iconButton,
              height: UI_SIZE.iconButton,
              borderRadius: UI_RADIUS.md,
              display: 'grid',
              placeItems: 'center',
              background: 'color-mix(in srgb, var(--pt-primary) 14%, transparent)',
              color: 'var(--pt-primary)',
              flexShrink: 0,
            }}
          >
            {icon}
          </Box>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700 }}>{title}</Typography>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', ...CLAMP_TEXT_3_SX }}>{description}</Typography>
          </Box>
        </Stack>

        <TextField
          value={url}
          fullWidth
          size="small"
          slotProps={{ input: { readOnly: true, sx: { fontSize: 13.5 } } }}
        />

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            startIcon={<ContentCopyOutlinedIcon fontSize="small" />}
            onClick={() => onCopy(url)}
            sx={{ minHeight: UI_SIZE.control, width: { xs: '100%', sm: 'auto' } }}
          >
            Copiar link
          </Button>
          <Button
            variant="outlined"
            startIcon={<LaunchOutlinedIcon fontSize="small" />}
            href={url}
            target="_blank"
            rel="noreferrer"
            sx={{ minHeight: UI_SIZE.control, width: { xs: '100%', sm: 'auto' } }}
          >
            Abrir
          </Button>
        </Stack>

        {qrCodeFileName && (
          <>
            <Divider sx={{ my: 0.5 }} />
            <Stack spacing={1.25} sx={{ alignItems: { xs: 'stretch', sm: 'center' } }}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <QrCode2OutlinedIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  Compartilhe este QR Code para clientes acessarem sua bilheteria online direto pelo celular.
                </Typography>
              </Stack>

              <Box
                ref={qrCanvasWrapperRef}
                sx={{
                  display: 'flex',
                  justifyContent: 'center',
                  p: 1.5,
                  borderRadius: UI_RADIUS.md,
                  background: '#fff',
                  width: 'fit-content',
                  alignSelf: { xs: 'center', sm: 'flex-start' },
                }}
              >
                <QRCodeCanvas value={url} size={168} marginSize={0} />
              </Box>

              <Button
                variant="outlined"
                size="small"
                startIcon={<DownloadOutlinedIcon fontSize="small" />}
                onClick={handleDownloadQrCode}
                sx={{ alignSelf: { xs: 'stretch', sm: 'flex-start' }, minHeight: UI_SIZE.control }}
              >
                Baixar QR Code
              </Button>
            </Stack>
          </>
        )}
      </Stack>
    </Paper>
  )
}

/**
 * Bloco "Empresa" do hub de Configurações — extraído de `TenantSettingsPage`
 * (2026-07-24). Form independente do de `tenant_settings`, com submit
 * próprio, endpoint `tenant-profile`. Gated por `tenant-profile,read`
 * (visibilidade, via `PermissionRoute` na rota `/configuracoes/empresa`) e
 * `tenant-profile,update` (habilita o salvar).
 */
export function CompanyBlock() {
  const [profile, setProfile] = useState<TenantProfile | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [cnpj, setCnpj] = useState('')
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [copyMessage, setCopyMessage] = useState<string | null>(null)

  const { hasPermission } = useAuth()
  const canUpdate = hasPermission(ACCESS.tenantProfileUpdate)
  const publicBaseUrl = typeof window !== 'undefined' ? window.location.origin : ''
  const storefrontUrl = `${publicBaseUrl}/loja/${profile?.slug ?? ''}`

  function syncProfile(data: TenantProfile) {
    setProfile(data)
    setName(data.name)
    setCnpj(data.cnpj ? formatCpfCnpj(data.cnpj) : '')
  }

  function load() {
    setIsLoading(true)
    setLoadError(null)
    tenantProfileService
      .getTenantProfile()
      .then((data) => {
        syncProfile(data)
      })
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados da empresa agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await tenantProfileService.updateTenantProfile({
        name,
        logo: logoFile,
        cnpj: normalizeCpfCnpj(cnpj) || null,
      })
      syncProfile(updated)
      setLogoFile(null)
      setSuccessMessage('Dados da empresa salvos com sucesso.')
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar os dados da empresa agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleCopyLink(value: string) {
    try {
      await navigator.clipboard.writeText(value)
      setCopyMessage('Link copiado com sucesso.')
    } catch {
      setCopyMessage('Não foi possível copiar o link automaticamente neste navegador.')
    }
  }

  if (loadError && !profile) {
    return (
      <Alert
        severity="error"
        action={
          <Button color="inherit" size="small" onClick={load}>
            Tentar novamente
          </Button>
        }
      >
        {loadError}
      </Alert>
    )
  }

  if (isLoading && !profile) {
    return <Skeleton variant="rounded" height={140} />
  }

  if (!profile) return null

  return (
    <>
      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}
      {successMessage && (
        <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
          {successMessage}
        </Alert>
      )}
      {copyMessage && (
        <Alert severity="info" sx={{ mb: 2.5 }} onClose={() => setCopyMessage(null)}>
          {copyMessage}
        </Alert>
      )}

      <Alert severity="info" sx={{ mb: 2.5 }}>
        Compartilhe os links públicos da sua empresa por WhatsApp, Instagram, bio ou campanhas. O link de reservas pode ser usado mesmo sem a bilheteria online, desde que a empresa esteja operando reservas com mesas cadastradas.
      </Alert>

      <Stack spacing={1.5} sx={{ mb: 2.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700 }}>Links públicos da empresa</Typography>
        <Stack direction={{ xs: 'column', xl: 'row' }} spacing={1.5}>
          <PublicLinkCard
            icon={<StorefrontOutlinedIcon fontSize="small" />}
            title="Bilheteria online"
            description="Use este endereço para divulgar o catálogo e receber pedidos pela vitrine pública."
            url={storefrontUrl}
            onCopy={handleCopyLink}
            qrCodeFileName={`qr-code-loja-${profile?.slug ?? 'pegaticket'}`}
          />
        </Stack>
      </Stack>

      <TextField
        label="Nome da empresa"
        value={name}
        onChange={(event) => setName(event.target.value)}
        error={Boolean(fieldErrors.name)}
        helperText={fieldErrors.name?.[0]}
        disabled={!canUpdate}
        required
        fullWidth
        sx={{ maxWidth: { sm: 480 }, mb: 2.5 }}
        slotProps={{ htmlInput: { maxLength: 255 } }}
      />

      <TextField
        label="CNPJ"
        value={cnpj}
        onChange={(event) => setCnpj(formatCpfCnpj(event.target.value))}
        error={Boolean(fieldErrors.cnpj)}
        helperText={fieldErrors.cnpj?.[0] ?? 'Aceita CNPJ numérico ou alfanumérico.'}
        disabled={!canUpdate}
        fullWidth
        sx={{ maxWidth: { sm: 320 }, mb: 2.5 }}
        slotProps={{ htmlInput: { maxLength: 18 } }}
      />

      <ImageUploadField label="Logo da empresa" existingImageUrl={profile.logo_url} onFileSelected={setLogoFile} />

      {canUpdate && (
        <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
          <Button
            variant="contained"
            disabled={isSubmitting || !name.trim()}
            onClick={() => void handleSubmit()}
            sx={{ minWidth: 140, minHeight: UI_SIZE.controlLarge, borderRadius: UI_RADIUS.md }}
          >
            {isSubmitting ? 'Salvando…' : 'Salvar'}
          </Button>
        </Stack>
      )}
    </>
  )
}
