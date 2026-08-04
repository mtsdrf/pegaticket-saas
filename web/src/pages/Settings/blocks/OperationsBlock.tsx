import { Alert, Button, FormControlLabel, Radio, RadioGroup, Skeleton, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useTenantSettingsData } from './useTenantSettingsData'
import * as tenantSettingsService from '../../../services/tenantSettingsService'
import { getApiErrorMessage } from '../../../types/api'
import type { StorefrontCatalogLayout } from '../../../types/storefront'

/**
 * Bloco "Vendas e Operação" — subconjunto de `tenant_settings` (extraído de
 * `TenantSettingsPage`, 2026-07-24). Edita só os campos operacionais; o
 * restante do objeto (`accepted_payment_methods`, dados de recebimento) é preservado
 * do payload carregado por `useTenantSettingsData`.
 */
export function OperationsBlock() {
  const { settings, setSettings, isLoading, loadError, reload } = useTenantSettingsData()

  const [storefrontEnabled, setStorefrontEnabled] = useState(true)
  const [catalogLayout, setCatalogLayout] = useState<StorefrontCatalogLayout>('list')
  const [metaPixelId, setMetaPixelId] = useState('')
  const [googleAnalyticsId, setGoogleAnalyticsId] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    setStorefrontEnabled(settings.storefront_enabled)
    setCatalogLayout(settings.catalog_layout)
    setMetaPixelId(settings.meta_pixel_id ?? '')
    setGoogleAnalyticsId(settings.google_analytics_id ?? '')
  }, [settings])

  async function handleSubmit() {
    if (!settings) return
    setFormError(null)
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await tenantSettingsService.updateTenantSettings({
        storefront_enabled: storefrontEnabled,
        catalog_layout: catalogLayout,
        accepted_payment_methods: settings.accepted_payment_methods,
        payment_receiving_method: settings.payment_receiving_method,
        payment_pix_key: settings.payment_pix_key,
        pagbank_integration_mode: settings.pagbank_integration_mode,
        pagbank_environment: settings.pagbank_environment,
        pagbank_receiver_account_id: settings.pagbank_receiver_account_id,
        meta_pixel_id: metaPixelId.trim() || null,
        google_analytics_id: googleAnalyticsId.trim() || null,
      })
      setSettings(updated)
      setSuccessMessage('Configurações salvas com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar as configurações agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  if (loadError && !settings) {
    return (
      <Alert
        severity="error"
        action={
          <Button color="inherit" size="small" onClick={reload}>
            Tentar novamente
          </Button>
        }
      >
        {loadError}
      </Alert>
    )
  }

  if (isLoading && !settings) {
    return <Skeleton variant="rounded" height={220} />
  }

  if (!settings) return null

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

      <FormControlLabel
        control={<Switch checked={storefrontEnabled} onChange={(event) => setStorefrontEnabled(event.target.checked)} />}
        label="Ativar bilheteria online"
      />
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5, ml: { xs: 0, sm: 6 } }}>
        Quando desligado, a página pública continua acessível apenas como vitrine institucional; o catálogo e o checkout deixam de aparecer.
      </Typography>

      <Typography sx={{ fontWeight: 600, fontSize: 16, mt: 3, mb: 0.5 }}>Layout do catálogo</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1 }}>
        Escolha como os itens aparecem para o comprador no canal público.
      </Typography>
      <RadioGroup
        value={catalogLayout}
        onChange={(event) => setCatalogLayout(event.target.value as StorefrontCatalogLayout)}
      >
        <FormControlLabel
          value="list"
          control={<Radio />}
          label={
            <Stack spacing={0}>
              <Typography sx={{ fontWeight: 600 }}>Lista compacta</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Lista com imagem à direita — mostra mais produtos por tela.
              </Typography>
            </Stack>
          }
          sx={{ alignItems: 'flex-start', mt: 0.5 }}
        />
        <FormControlLabel
          value="grid"
          control={<Radio />}
          label={
            <Stack spacing={0}>
              <Typography sx={{ fontWeight: 600 }}>Cards com foto grande</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Grade de cards com foto em destaque — visual mais atrativo.
              </Typography>
            </Stack>
          }
          sx={{ alignItems: 'flex-start', mt: 1 }}
        />
      </RadioGroup>

      <Typography sx={{ fontWeight: 600, fontSize: 16, mt: 3, mb: 0.5 }}>Pixels de marketing</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1.5 }}>
        Opcional — quando preenchido, a loja pública injeta o script correspondente para rastrear campanhas. Deixe em
        branco para não carregar nenhum script extra.
      </Typography>
      <Stack spacing={2}>
        <TextField
          label="Meta Pixel ID"
          value={metaPixelId}
          onChange={(event) => setMetaPixelId(event.target.value)}
          size="small"
          fullWidth
          placeholder="Ex.: 1234567890"
        />
        <TextField
          label="Google Analytics 4 (Measurement ID)"
          value={googleAnalyticsId}
          onChange={(event) => setGoogleAnalyticsId(event.target.value)}
          size="small"
          fullWidth
          placeholder="Ex.: G-XXXXXXXXXX"
        />
      </Stack>

      <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
        <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 140 }}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </Stack>
    </>
  )
}
