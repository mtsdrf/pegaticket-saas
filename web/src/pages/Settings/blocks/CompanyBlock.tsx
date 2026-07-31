import DeleteOutlineOutlinedIcon from '@mui/icons-material/DeleteOutlineOutlined'
import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import LaunchOutlinedIcon from '@mui/icons-material/LaunchOutlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Alert, Box, Button, Divider, IconButton, MenuItem, Paper, Skeleton, Stack, TextField, Tooltip, Typography } from '@mui/material'
import { useEffect, useRef, useState, type ChangeEvent, type ReactNode } from 'react'
import { QRCodeCanvas } from 'qrcode.react'
import { PasswordField } from '../../../components/form/PasswordField'
import { ImageUploadField } from '../../../components/shared/ImageUploadField'
import { ACCESS } from '../../../access/requirements'
import { useAuth } from '../../../hooks/useAuth'
import * as tenantProfileService from '../../../services/tenantProfileService'
import { CARD_EQUAL_HEIGHT_SX, CLAMP_TEXT_3_SX, FORM_GRID_2_SX, FORM_GRID_3_SX, UI_RADIUS, UI_SIZE } from '../../../styles/layoutStandards'
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
        background: 'color-mix(in srgb, var(--mk-surface) 82%, var(--mk-primary) 4%)',
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
              background: 'color-mix(in srgb, var(--mk-primary) 14%, transparent)',
              color: 'var(--mk-primary)',
              flexShrink: 0,
            }}
          >
            {icon}
          </Box>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontWeight: 700 }}>{title}</Typography>
            <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', ...CLAMP_TEXT_3_SX }}>{description}</Typography>
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
                <QrCode2OutlinedIcon fontSize="small" sx={{ color: 'var(--mk-muted)' }} />
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                  Compartilhe este QR Code para clientes acessarem sua loja online direto pelo celular.
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
  const [ie, setIe] = useState('')
  const [im, setIm] = useState('')
  const [cnae, setCnae] = useState('')
  const [taxRegime, setTaxRegime] = useState<'simples_nacional' | 'lucro_presumido' | 'lucro_real' | ''>('')
  const [fiscalEnvironment, setFiscalEnvironment] = useState<'homologacao' | 'producao' | ''>('')
  const [ibgeCityCode, setIbgeCityCode] = useState('')
  const [fiscalProvider, setFiscalProvider] = useState<'manual' | 'focus_nfe' | 'plugnotas' | 'nfeio' | 'sped_nfe' | ''>('')
  const [fiscalNfeSeries, setFiscalNfeSeries] = useState('')
  const [fiscalNfceSeries, setFiscalNfceSeries] = useState('')
  const [fiscalNfseSeries, setFiscalNfseSeries] = useState('')
  const [fiscalNextNfeNumber, setFiscalNextNfeNumber] = useState('')
  const [fiscalNextNfceNumber, setFiscalNextNfceNumber] = useState('')
  const [fiscalNextNfseNumber, setFiscalNextNfseNumber] = useState('')
  const [fiscalNfceCscId, setFiscalNfceCscId] = useState('')
  const [fiscalNfceCscCode, setFiscalNfceCscCode] = useState('')
  const [fiscalProviderApiToken, setFiscalProviderApiToken] = useState('')
  const [fiscalCertificateA1File, setFiscalCertificateA1File] = useState<File | null>(null)
  const [fiscalCertificateA1Password, setFiscalCertificateA1Password] = useState('')
  const [clearFiscalNfceCscCode, setClearFiscalNfceCscCode] = useState(false)
  const [clearFiscalProviderApiToken, setClearFiscalProviderApiToken] = useState(false)
  const [clearFiscalCertificateA1, setClearFiscalCertificateA1] = useState(false)
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [copyMessage, setCopyMessage] = useState<string | null>(null)

  const { hasPermission } = useAuth()
  const canUpdate = hasPermission(ACCESS.tenantProfileUpdate)
  const hasFiscalModule = hasPermission(ACCESS.taxRulesRead)
  const publicBaseUrl = typeof window !== 'undefined' ? window.location.origin : ''
  const storefrontUrl = `${publicBaseUrl}/loja/${profile?.slug ?? ''}`
  const reservationUrl = `${publicBaseUrl}/reservas/${profile?.slug ?? ''}`

  function syncProfile(data: TenantProfile) {
    setProfile(data)
    setName(data.name)
    setCnpj(data.cnpj ? formatCpfCnpj(data.cnpj) : '')
    setIe(data.ie ?? '')
    setIm(data.im ?? '')
    setCnae(data.cnae ?? '')
    setTaxRegime(data.tax_regime ?? '')
    setFiscalEnvironment(data.fiscal_environment ?? '')
    setIbgeCityCode(data.ibge_city_code ?? '')
    setFiscalProvider(data.fiscal_provider ?? '')
    setFiscalNfeSeries(data.fiscal_nfe_series ?? '')
    setFiscalNfceSeries(data.fiscal_nfce_series ?? '')
    setFiscalNfseSeries(data.fiscal_nfse_series ?? '')
    setFiscalNextNfeNumber(data.fiscal_next_nfe_number ? String(data.fiscal_next_nfe_number) : '')
    setFiscalNextNfceNumber(data.fiscal_next_nfce_number ? String(data.fiscal_next_nfce_number) : '')
    setFiscalNextNfseNumber(data.fiscal_next_nfse_number ? String(data.fiscal_next_nfse_number) : '')
    setFiscalNfceCscId(data.fiscal_nfce_csc_id ?? '')
    setFiscalNfceCscCode('')
    setFiscalProviderApiToken('')
    setFiscalCertificateA1Password('')
    setFiscalCertificateA1File(null)
    setClearFiscalNfceCscCode(false)
    setClearFiscalProviderApiToken(false)
    setClearFiscalCertificateA1(false)
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
        ie: ie.trim() || null,
        im: im.trim() || null,
        cnae: cnae.trim() || null,
        tax_regime: taxRegime || null,
        fiscal_environment: fiscalEnvironment || null,
        ibge_city_code: ibgeCityCode.trim() || null,
        fiscal_provider: hasFiscalModule ? fiscalProvider || null : null,
        fiscal_nfe_series: hasFiscalModule ? fiscalNfeSeries.trim() || null : null,
        fiscal_nfce_series: hasFiscalModule ? fiscalNfceSeries.trim() || null : null,
        fiscal_nfse_series: hasFiscalModule ? fiscalNfseSeries.trim() || null : null,
        fiscal_next_nfe_number: hasFiscalModule && fiscalNextNfeNumber.trim() ? Number(fiscalNextNfeNumber) : null,
        fiscal_next_nfce_number: hasFiscalModule && fiscalNextNfceNumber.trim() ? Number(fiscalNextNfceNumber) : null,
        fiscal_next_nfse_number: hasFiscalModule && fiscalNextNfseNumber.trim() ? Number(fiscalNextNfseNumber) : null,
        fiscal_nfce_csc_id: hasFiscalModule ? fiscalNfceCscId.trim() || null : null,
        fiscal_nfce_csc_code: hasFiscalModule && !clearFiscalNfceCscCode ? fiscalNfceCscCode.trim() || null : null,
        fiscal_provider_api_token: hasFiscalModule && !clearFiscalProviderApiToken ? fiscalProviderApiToken.trim() || null : null,
        fiscal_certificate_a1: hasFiscalModule && !clearFiscalCertificateA1 ? fiscalCertificateA1File : null,
        fiscal_certificate_a1_password: hasFiscalModule && !clearFiscalCertificateA1 ? fiscalCertificateA1Password || null : null,
        clear_fiscal_nfce_csc_code: hasFiscalModule && clearFiscalNfceCscCode,
        clear_fiscal_provider_api_token: hasFiscalModule && clearFiscalProviderApiToken,
        clear_fiscal_certificate_a1: hasFiscalModule && clearFiscalCertificateA1,
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
        Compartilhe os links públicos da sua empresa por WhatsApp, Instagram, bio ou campanhas. O link de reservas pode ser usado mesmo sem a loja online, desde que a empresa esteja operando reservas com mesas cadastradas.
      </Alert>

      <Stack spacing={1.5} sx={{ mb: 2.5 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700 }}>Links públicos da empresa</Typography>
        <Stack direction={{ xs: 'column', xl: 'row' }} spacing={1.5}>
          <PublicLinkCard
            icon={<StorefrontOutlinedIcon fontSize="small" />}
            title="Loja online"
            description="Use este endereço para divulgar o catálogo e receber pedidos pela vitrine pública."
            url={storefrontUrl}
            onCopy={handleCopyLink}
            qrCodeFileName={`qr-code-loja-${profile?.slug ?? 'maskats'}`}
          />
          <PublicLinkCard
            icon={<EventSeatOutlinedIcon fontSize="small" />}
            title="Reservas"
            description="Compartilhe este link para receber reservas online mesmo quando a operação não usar delivery."
            url={reservationUrl}
            onCopy={handleCopyLink}
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

      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 1.5 }}>
        Cadastre aqui os dados fiscais e cadastrais da empresa para integrações contábeis e futuras integrações fiscais.
      </Typography>

      <Stack spacing={2} sx={{ mb: 2.5 }}>
        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="CNPJ"
            value={cnpj}
            onChange={(event) => setCnpj(formatCpfCnpj(event.target.value))}
            error={Boolean(fieldErrors.cnpj)}
            helperText={fieldErrors.cnpj?.[0] ?? 'Aceita CNPJ numérico ou alfanumérico.'}
            disabled={!canUpdate}
            fullWidth
            slotProps={{ htmlInput: { maxLength: 18 } }}
          />
          <TextField
            label="Inscrição Estadual"
            value={ie}
            onChange={(event) => setIe(event.target.value)}
            error={Boolean(fieldErrors.ie)}
            helperText={fieldErrors.ie?.[0] ?? 'Use ISENTO quando aplicável.'}
            disabled={!canUpdate}
            fullWidth
            slotProps={{ htmlInput: { maxLength: 30 } }}
          />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <TextField
            label="Inscrição Municipal"
            value={im}
            onChange={(event) => setIm(event.target.value)}
            error={Boolean(fieldErrors.im)}
            helperText={fieldErrors.im?.[0]}
            disabled={!canUpdate}
            fullWidth
            slotProps={{ htmlInput: { maxLength: 30 } }}
          />
          <TextField
            label="CNAE"
            value={cnae}
            onChange={(event) => setCnae(event.target.value)}
            error={Boolean(fieldErrors.cnae)}
            helperText={fieldErrors.cnae?.[0]}
            disabled={!canUpdate}
            fullWidth
            slotProps={{ htmlInput: { maxLength: 20 } }}
          />
        </Box>

        <Box sx={FORM_GRID_2_SX}>
          <TextField
            select
            label="Regime tributário"
            value={taxRegime}
            onChange={(event) => setTaxRegime(event.target.value as 'simples_nacional' | 'lucro_presumido' | 'lucro_real' | '')}
            error={Boolean(fieldErrors.tax_regime)}
            helperText={fieldErrors.tax_regime?.[0]}
            disabled={!canUpdate}
            fullWidth
          >
            <MenuItem value="">Não informado</MenuItem>
            <MenuItem value="simples_nacional">Simples Nacional</MenuItem>
            <MenuItem value="lucro_presumido">Lucro Presumido</MenuItem>
            <MenuItem value="lucro_real">Lucro Real</MenuItem>
          </TextField>

          <TextField
            select
            label="Ambiente fiscal"
            value={fiscalEnvironment}
            onChange={(event) => setFiscalEnvironment(event.target.value as 'homologacao' | 'producao' | '')}
            error={Boolean(fieldErrors.fiscal_environment)}
            helperText={fieldErrors.fiscal_environment?.[0] ?? 'Homologação é o padrão seguro para testes.'}
            disabled={!canUpdate}
            fullWidth
          >
            <MenuItem value="">Não informado</MenuItem>
            <MenuItem value="homologacao">Homologação</MenuItem>
            <MenuItem value="producao">Produção</MenuItem>
          </TextField>
        </Box>

        <TextField
          label="Código IBGE do município"
          value={ibgeCityCode}
          onChange={(event) => setIbgeCityCode(event.target.value)}
          error={Boolean(fieldErrors.ibge_city_code)}
          helperText={fieldErrors.ibge_city_code?.[0] ?? 'Necessário para integrações fiscais que dependem do município da empresa.'}
          disabled={!canUpdate}
          fullWidth
          sx={{ maxWidth: { sm: 320 } }}
          slotProps={{ htmlInput: { maxLength: 7 } }}
        />
      </Stack>

      {hasFiscalModule ? (
        <>
          <Divider sx={{ my: 3 }} />

          <Typography sx={{ fontSize: 15, fontWeight: 700, mb: 0.75 }}>
            Integração fiscal
          </Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2 }}>
            Preencha somente se a sua empresa for usar emissão fiscal real. Os segredos ficam protegidos no backend e
            nunca voltam expostos para a tela.
          </Typography>

          <Stack spacing={2} sx={{ mb: 2.5 }}>
            <Box sx={FORM_GRID_2_SX}>
              <TextField
                select
                label="Provedor fiscal"
                value={fiscalProvider}
                onChange={(event) => setFiscalProvider(event.target.value as 'manual' | 'focus_nfe' | 'plugnotas' | 'nfeio' | 'sped_nfe' | '')}
                error={Boolean(fieldErrors.fiscal_provider)}
                helperText={fieldErrors.fiscal_provider?.[0] ?? 'Selecione o provedor que a empresa vai usar na emissão real.'}
                disabled={!canUpdate}
                fullWidth
              >
                <MenuItem value="">Não informado</MenuItem>
                <MenuItem value="manual">Manual</MenuItem>
                <MenuItem value="focus_nfe">Focus NFe</MenuItem>
                <MenuItem value="plugnotas">PlugNotas</MenuItem>
                <MenuItem value="nfeio">NFe.io</MenuItem>
                <MenuItem value="sped_nfe">Biblioteca própria (sped-nfe)</MenuItem>
              </TextField>

              <TextField
                label="ID CSC da NFC-e"
                value={fiscalNfceCscId}
                onChange={(event) => setFiscalNfceCscId(event.target.value)}
                error={Boolean(fieldErrors.fiscal_nfce_csc_id)}
                helperText={fieldErrors.fiscal_nfce_csc_id?.[0] ?? 'Obrigatório em cenários de NFC-e conforme a UF.'}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 40 } }}
              />
            </Box>

            <Box sx={FORM_GRID_3_SX}>
              <TextField
                label="Série NF-e"
                value={fiscalNfeSeries}
                onChange={(event) => setFiscalNfeSeries(event.target.value)}
                error={Boolean(fieldErrors.fiscal_nfe_series)}
                helperText={fieldErrors.fiscal_nfe_series?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 20 } }}
              />
              <TextField
                label="Série NFC-e"
                value={fiscalNfceSeries}
                onChange={(event) => setFiscalNfceSeries(event.target.value)}
                error={Boolean(fieldErrors.fiscal_nfce_series)}
                helperText={fieldErrors.fiscal_nfce_series?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 20 } }}
              />
              <TextField
                label="Série NFS-e"
                value={fiscalNfseSeries}
                onChange={(event) => setFiscalNfseSeries(event.target.value)}
                error={Boolean(fieldErrors.fiscal_nfse_series)}
                helperText={fieldErrors.fiscal_nfse_series?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 20 } }}
              />
            </Box>

            <Box sx={FORM_GRID_3_SX}>
              <TextField
                label="Próximo número NF-e"
                value={fiscalNextNfeNumber}
                onChange={(event) => setFiscalNextNfeNumber(event.target.value.replace(/\D/g, ''))}
                error={Boolean(fieldErrors.fiscal_next_nfe_number)}
                helperText={fieldErrors.fiscal_next_nfe_number?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 9 } }}
              />
              <TextField
                label="Próximo número NFC-e"
                value={fiscalNextNfceNumber}
                onChange={(event) => setFiscalNextNfceNumber(event.target.value.replace(/\D/g, ''))}
                error={Boolean(fieldErrors.fiscal_next_nfce_number)}
                helperText={fieldErrors.fiscal_next_nfce_number?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 9 } }}
              />
              <TextField
                label="Próximo número NFS-e"
                value={fiscalNextNfseNumber}
                onChange={(event) => setFiscalNextNfseNumber(event.target.value.replace(/\D/g, ''))}
                error={Boolean(fieldErrors.fiscal_next_nfse_number)}
                helperText={fieldErrors.fiscal_next_nfse_number?.[0]}
                disabled={!canUpdate}
                fullWidth
                slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 9 } }}
              />
            </Box>

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ alignItems: { md: 'flex-start' } }}>
              <PasswordField
                label="Código CSC da NFC-e"
                value={fiscalNfceCscCode}
                onChange={(event) => {
                  setFiscalNfceCscCode(event.target.value)
                  setClearFiscalNfceCscCode(false)
                }}
                error={Boolean(fieldErrors.fiscal_nfce_csc_code)}
                helperText={
                  fieldErrors.fiscal_nfce_csc_code?.[0]
                  ?? (profile.has_fiscal_nfce_csc_code && !clearFiscalNfceCscCode
                    ? 'Já existe um CSC salvo. Preencha somente se quiser substituir.'
                    : 'Informe o código secreto do CSC quando a empresa usar NFC-e.')
                }
                disabled={!canUpdate || clearFiscalNfceCscCode}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />
              {canUpdate && profile.has_fiscal_nfce_csc_code ? (
                <Tooltip title="Remover CSC salvo">
                  <span>
                    <IconButton
                      onClick={() => {
                        setClearFiscalNfceCscCode((current) => !current)
                        setFiscalNfceCscCode('')
                      }}
                      color={clearFiscalNfceCscCode ? 'error' : 'default'}
                      sx={{ mt: { md: 1 }, width: UI_SIZE.iconButton, height: UI_SIZE.iconButton, borderRadius: UI_RADIUS.md }}
                    >
                      <DeleteOutlineOutlinedIcon />
                    </IconButton>
                  </span>
                </Tooltip>
              ) : null}
            </Stack>

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ alignItems: { md: 'flex-start' } }}>
              <PasswordField
                label="Token do provedor fiscal"
                value={fiscalProviderApiToken}
                onChange={(event) => {
                  setFiscalProviderApiToken(event.target.value)
                  setClearFiscalProviderApiToken(false)
                }}
                error={Boolean(fieldErrors.fiscal_provider_api_token)}
                helperText={
                  fieldErrors.fiscal_provider_api_token?.[0]
                  ?? (profile.has_fiscal_provider_api_token && !clearFiscalProviderApiToken
                    ? 'Já existe um token salvo. Preencha somente se quiser substituir.'
                    : 'Use para provedores externos como Focus NFe, PlugNotas ou NFe.io.')
                }
                disabled={!canUpdate || clearFiscalProviderApiToken}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 5000 } }}
              />
              {canUpdate && profile.has_fiscal_provider_api_token ? (
                <Tooltip title="Remover token salvo">
                  <span>
                    <IconButton
                      onClick={() => {
                        setClearFiscalProviderApiToken((current) => !current)
                        setFiscalProviderApiToken('')
                      }}
                      color={clearFiscalProviderApiToken ? 'error' : 'default'}
                      sx={{ mt: { md: 1 }, width: UI_SIZE.iconButton, height: UI_SIZE.iconButton, borderRadius: UI_RADIUS.md }}
                    >
                      <DeleteOutlineOutlinedIcon />
                    </IconButton>
                  </span>
                </Tooltip>
              ) : null}
            </Stack>

            <Stack spacing={1.25}>
              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                Certificado A1 (.pfx ou .p12)
              </Typography>
              <TextField
                type="file"
                error={Boolean(fieldErrors.fiscal_certificate_a1)}
                helperText={
                  fieldErrors.fiscal_certificate_a1?.[0]
                  ?? (profile.has_fiscal_certificate_a1 && !clearFiscalCertificateA1
                    ? `Certificado salvo: ${profile.fiscal_certificate_a1_name ?? 'arquivo enviado'}`
                    : 'Envie o certificado somente quando a empresa usar emissão própria com A1.')
                }
                disabled={!canUpdate || clearFiscalCertificateA1}
                fullWidth
                slotProps={{
                  htmlInput: {
                    accept: '.pfx,.p12',
                    onChange: (event: ChangeEvent<HTMLInputElement>) => {
                      setFiscalCertificateA1File(event.target.files?.[0] ?? null)
                      setClearFiscalCertificateA1(false)
                    },
                  },
                }}
              />

              <PasswordField
                label="Senha do certificado A1"
                value={fiscalCertificateA1Password}
                onChange={(event) => setFiscalCertificateA1Password(event.target.value)}
                error={Boolean(fieldErrors.fiscal_certificate_a1_password)}
                helperText={
                  fieldErrors.fiscal_certificate_a1_password?.[0]
                  ?? (profile.has_fiscal_certificate_a1 && !clearFiscalCertificateA1
                    ? 'Já existe um certificado salvo. Preencha a senha apenas se for atualizar ou trocar o arquivo.'
                    : 'Senha usada para abrir o certificado digital, quando aplicável.')
                }
                disabled={!canUpdate || clearFiscalCertificateA1}
                fullWidth
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />

              {canUpdate && profile.has_fiscal_certificate_a1 ? (
                <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
                  <Button
                    variant={clearFiscalCertificateA1 ? 'contained' : 'outlined'}
                    color="error"
                    onClick={() => {
                      setClearFiscalCertificateA1((current) => !current)
                      setFiscalCertificateA1File(null)
                      setFiscalCertificateA1Password('')
                    }}
                    sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}
                  >
                    {clearFiscalCertificateA1 ? 'Certificado será removido' : 'Remover certificado salvo'}
                  </Button>
                </Stack>
              ) : null}
            </Stack>
          </Stack>
        </>
      ) : null}

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
