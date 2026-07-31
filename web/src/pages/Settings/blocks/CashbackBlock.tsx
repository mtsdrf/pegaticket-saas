import { Alert, Button, FormControlLabel, InputAdornment, Skeleton, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useTenantSettingsData } from './useTenantSettingsData'
import * as tenantSettingsService from '../../../services/tenantSettingsService'
import { getApiErrorMessage } from '../../../types/api'

/** Bloco "Cashback e Fidelidade" — subconjunto de `tenant_settings` (extraído de `TenantSettingsPage`, 2026-07-24). */
export function CashbackBlock() {
  const { settings, setSettings, isLoading, loadError, reload } = useTenantSettingsData()

  const [cashbackEnabled, setCashbackEnabled] = useState(false)
  const [cashbackPercentage, setCashbackPercentage] = useState('')
  const [cashbackMaxPerOrder, setCashbackMaxPerOrder] = useState('')
  const [cashbackHoldDays, setCashbackHoldDays] = useState('')
  const [cashbackExpirationDays, setCashbackExpirationDays] = useState('')
  const [cashbackRedeemMaxPercentage, setCashbackRedeemMaxPercentage] = useState('')
  const [cashbackName, setCashbackName] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    setCashbackEnabled(settings.cashback_enabled)
    setCashbackPercentage(settings.cashback_percentage !== null ? String(settings.cashback_percentage) : '')
    setCashbackMaxPerOrder(settings.cashback_max_per_order !== null ? String(settings.cashback_max_per_order) : '')
    setCashbackHoldDays(settings.cashback_hold_days !== null ? String(settings.cashback_hold_days) : '')
    setCashbackExpirationDays(settings.cashback_expiration_days !== null ? String(settings.cashback_expiration_days) : '')
    setCashbackRedeemMaxPercentage(
      settings.cashback_redeem_max_percentage !== null ? String(settings.cashback_redeem_max_percentage) : '',
    )
    setCashbackName(settings.cashback_name ?? '')
  }, [settings])

  async function handleSubmit() {
    if (!settings) return
    setFormError(null)
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await tenantSettingsService.updateTenantSettings({
        send_tracking_link_whatsapp: settings.send_tracking_link_whatsapp,
        block_order_without_stock: settings.block_order_without_stock,
        minimum_order_value: settings.minimum_order_value,
        estimated_preparation_minutes: settings.estimated_preparation_minutes,
        allow_store_pickup: settings.allow_store_pickup,
        allow_delivery: settings.allow_delivery,
        storefront_enabled: settings.storefront_enabled,
        catalog_layout: settings.catalog_layout,
        accepted_payment_methods: settings.accepted_payment_methods,
        payment_receiving_method: settings.payment_receiving_method,
        payment_pix_key: settings.payment_pix_key,
        cashback_enabled: cashbackEnabled,
        cashback_percentage: cashbackPercentage.trim() ? Number(cashbackPercentage) : null,
        cashback_max_per_order: cashbackMaxPerOrder.trim() ? Number(cashbackMaxPerOrder) : null,
        cashback_hold_days: cashbackHoldDays.trim() ? Number(cashbackHoldDays) : null,
        cashback_expiration_days: cashbackExpirationDays.trim() ? Number(cashbackExpirationDays) : null,
        cashback_redeem_max_percentage: cashbackRedeemMaxPercentage.trim() ? Number(cashbackRedeemMaxPercentage) : null,
        cashback_name: cashbackName.trim() ? cashbackName.trim() : null,
      })
      setSettings(updated)
      setSuccessMessage('Cashback salvo com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o cashback agora.'))
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

      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2 }}>
        Cliente ganha crédito ao comprar na loja online e pode usar em compras futuras.
      </Typography>

      <FormControlLabel
        control={<Switch checked={cashbackEnabled} onChange={(event) => setCashbackEnabled(event.target.checked)} />}
        label="Ativar cashback na loja online"
      />

      {cashbackEnabled && (
        <Stack spacing={2} sx={{ mt: 2 }}>
          <TextField
            label="Nome do programa (opcional)"
            value={cashbackName}
            onChange={(event) => setCashbackName(event.target.value)}
            placeholder="Ex.: eCash"
            helperText="Deixe em branco para usar 'Cashback'."
            sx={{ maxWidth: { sm: 320 } }}
            fullWidth
            slotProps={{ htmlInput: { maxLength: 60 } }}
          />
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField
              label="% de cashback"
              type="number"
              value={cashbackPercentage}
              onChange={(event) => setCashbackPercentage(event.target.value)}
              helperText="Sobre o valor líquido pago (sem frete)."
              fullWidth
              slotProps={{
                input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
                htmlInput: { min: 0, max: 100, step: '0.01' },
              }}
            />
            <TextField
              label="Teto de cashback por pedido"
              type="number"
              value={cashbackMaxPerOrder}
              onChange={(event) => setCashbackMaxPerOrder(event.target.value)}
              helperText="Deixe em branco para não limitar."
              fullWidth
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
            />
          </Stack>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField
              label="Carência antes de poder usar"
              type="number"
              value={cashbackHoldDays}
              onChange={(event) => setCashbackHoldDays(event.target.value)}
              helperText="Dias até o saldo ficar disponível. 0 = disponível na hora."
              fullWidth
              slotProps={{
                input: { endAdornment: <InputAdornment position="end">dias</InputAdornment> },
                htmlInput: { min: 0, step: 1 },
              }}
            />
            <TextField
              label="Validade do cashback"
              type="number"
              value={cashbackExpirationDays}
              onChange={(event) => setCashbackExpirationDays(event.target.value)}
              helperText="Dias até o crédito expirar depois de ganho."
              fullWidth
              slotProps={{
                input: { endAdornment: <InputAdornment position="end">dias</InputAdornment> },
                htmlInput: { min: 1, step: 1 },
              }}
            />
          </Stack>
          <TextField
            label="Máximo do pedido pagável em cashback"
            type="number"
            value={cashbackRedeemMaxPercentage}
            onChange={(event) => setCashbackRedeemMaxPercentage(event.target.value)}
            helperText="% do subtotal que o cliente pode pagar usando cashback."
            sx={{ maxWidth: { sm: 320 } }}
            fullWidth
            slotProps={{
              input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
              htmlInput: { min: 0, max: 100, step: '0.01' },
            }}
          />
        </Stack>
      )}

      <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
        <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 140 }}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </Stack>
    </>
  )
}
