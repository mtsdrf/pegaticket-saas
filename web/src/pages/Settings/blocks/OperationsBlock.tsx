import {
  Alert,
  Button,
  FormControlLabel,
  InputAdornment,
  Radio,
  RadioGroup,
  Skeleton,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { useTenantSettingsData } from './useTenantSettingsData'
import * as tenantSettingsService from '../../../services/tenantSettingsService'
import { getApiErrorMessage } from '../../../types/api'
import type { StorefrontCatalogLayout } from '../../../types/storefront'

/**
 * Bloco "Pedidos e Operação" — subconjunto de `tenant_settings` (extraído de
 * `TenantSettingsPage`, 2026-07-24). Edita só os campos dela; o restante do
 * objeto (`accepted_payment_methods`, `cashback_*`) vai preservado do que
 * foi carregado por `useTenantSettingsData`, não editado aqui.
 */
export function OperationsBlock() {
  const { settings, setSettings, isLoading, loadError, reload } = useTenantSettingsData()

  const [sendTrackingLink, setSendTrackingLink] = useState(false)
  const [blockOrderWithoutStock, setBlockOrderWithoutStock] = useState(false)
  const [minimumOrderValue, setMinimumOrderValue] = useState('')
  const [estimatedPreparationMinutes, setEstimatedPreparationMinutes] = useState('')
  const [allowStorePickup, setAllowStorePickup] = useState(false)
  const [allowDelivery, setAllowDelivery] = useState(true)
  const [storefrontEnabled, setStorefrontEnabled] = useState(true)
  const [catalogLayout, setCatalogLayout] = useState<StorefrontCatalogLayout>('list')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    setSendTrackingLink(settings.send_tracking_link_whatsapp)
    setBlockOrderWithoutStock(settings.block_order_without_stock)
    setMinimumOrderValue(settings.minimum_order_value !== null ? String(settings.minimum_order_value) : '')
    setEstimatedPreparationMinutes(
      settings.estimated_preparation_minutes !== null ? String(settings.estimated_preparation_minutes) : '',
    )
    setAllowStorePickup(settings.allow_store_pickup)
    setAllowDelivery(settings.allow_delivery)
    setStorefrontEnabled(settings.storefront_enabled)
    setCatalogLayout(settings.catalog_layout)
  }, [settings])

  async function handleSubmit() {
    if (!settings) return
    setFormError(null)
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await tenantSettingsService.updateTenantSettings({
        send_tracking_link_whatsapp: sendTrackingLink,
        block_order_without_stock: blockOrderWithoutStock,
        minimum_order_value: minimumOrderValue.trim() ? Number(minimumOrderValue) : null,
        estimated_preparation_minutes: estimatedPreparationMinutes.trim() ? Number(estimatedPreparationMinutes) : null,
        allow_store_pickup: allowStorePickup,
        allow_delivery: allowDelivery,
        storefront_enabled: storefrontEnabled,
        catalog_layout: catalogLayout,
        cashback_enabled: settings.cashback_enabled,
        cashback_percentage: settings.cashback_percentage,
        cashback_max_per_order: settings.cashback_max_per_order,
        cashback_hold_days: settings.cashback_hold_days,
        cashback_expiration_days: settings.cashback_expiration_days,
        cashback_redeem_max_percentage: settings.cashback_redeem_max_percentage,
        cashback_name: settings.cashback_name,
        accepted_payment_methods: settings.accepted_payment_methods,
        payment_receiving_method: settings.payment_receiving_method,
        payment_pix_key: settings.payment_pix_key,
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
        label="Ativar loja online"
      />
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5, ml: { xs: 0, sm: 6 } }}>
        Quando desligado, a página pública da empresa continua mostrando endereço, horários, contatos e reservas, mas o catálogo de produtos e o checkout deixam de aparecer.
      </Typography>

      <FormControlLabel
        control={<Switch checked={sendTrackingLink} onChange={(event) => setSendTrackingLink(event.target.checked)} />}
        label="Enviar link de rastreio na mensagem do WhatsApp"
        sx={{ mt: 2.5 }}
      />
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5, ml: { xs: 0, sm: 6 } }}>
        Quando ativado, as mensagens de WhatsApp enviadas para o cliente (na criação do pedido e no resumo enviado
        sob demanda) incluem um link para acompanhar o status da entrega.
      </Typography>

      <FormControlLabel
        control={
          <Switch checked={blockOrderWithoutStock} onChange={(event) => setBlockOrderWithoutStock(event.target.checked)} />
        }
        label="Bloquear pedido quando não há estoque suficiente"
        sx={{ mt: 2.5 }}
      />
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 0.5, ml: { xs: 0, sm: 6 } }}>
        Quando ativado, um pedido não pode ser criado se algum item não tiver estoque suficiente reservado — vale
        hoje principalmente para pedidos feitos pela loja pública.
      </Typography>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
        <TextField
          label="Pedido mínimo da loja online"
          type="number"
          value={minimumOrderValue}
          onChange={(event) => setMinimumOrderValue(event.target.value)}
          helperText="Deixe em branco para não exigir um valor mínimo."
          fullWidth
          slotProps={{
            input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
            htmlInput: { min: 0, step: '0.01' },
          }}
        />
        <TextField
          label="Tempo estimado de preparo"
          type="number"
          value={estimatedPreparationMinutes}
          onChange={(event) => setEstimatedPreparationMinutes(event.target.value)}
          helperText="Deixe em branco para não exibir estimativa no catálogo."
          fullWidth
          slotProps={{
            input: { endAdornment: <InputAdornment position="end">min</InputAdornment> },
            htmlInput: { min: 1, step: 1 },
          }}
        />
      </Stack>

      <Typography sx={{ fontWeight: 600, fontSize: 16, mt: 3, mb: 0.5 }}>Retirada na loja</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1 }}>
        Permite que o cliente escolha retirar o pedido no endereço da sua loja em vez de receber em casa.
      </Typography>
      <FormControlLabel
        control={<Switch checked={allowStorePickup} onChange={(event) => setAllowStorePickup(event.target.checked)} />}
        label="Permitir retirada na loja"
      />
      {allowStorePickup && (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mt: 0.5, ml: { xs: 0, sm: 6 } }}>
          Cadastre o endereço da sua loja no bloco "Horário e Endereço" — sem endereço, o cliente não consegue
          escolher a retirada.
        </Typography>
      )}

      <Typography sx={{ fontWeight: 600, fontSize: 16, mt: 3, mb: 0.5 }}>Entrega</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1 }}>
        Permite que o cliente escolha receber o pedido no endereço dele em vez de retirar na loja.
      </Typography>
      <FormControlLabel
        control={<Switch checked={allowDelivery} onChange={(event) => setAllowDelivery(event.target.checked)} />}
        label="Aceita entrega"
      />

      <Typography sx={{ fontWeight: 600, fontSize: 16, mt: 3, mb: 0.5 }}>Layout do catálogo</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1 }}>
        Escolha como os produtos aparecem para o cliente na loja online.
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

      <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
        <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 140 }}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </Stack>
    </>
  )
}
