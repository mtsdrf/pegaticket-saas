import { Alert, Button, Checkbox, FormControlLabel, FormGroup, MenuItem, Skeleton, Stack, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useTenantSettingsData } from './useTenantSettingsData'
import { PAYMENT_METHODS, PAYMENT_METHOD_SETTINGS_LABELS, type PaymentMethod } from '../../../constants/paymentMethods'
import * as tenantSettingsService from '../../../services/tenantSettingsService'
import { ApiRequestError, getApiErrorMessage } from '../../../types/api'

/** Bloco "Pagamento" — subconjunto de `tenant_settings` (extraído de `TenantSettingsPage`, 2026-07-24). */
export function PaymentBlock() {
  const { settings, setSettings, isLoading, loadError, reload } = useTenantSettingsData()

  const [acceptedPaymentMethods, setAcceptedPaymentMethods] = useState<PaymentMethod[]>([])
  const [paymentReceivingMethod, setPaymentReceivingMethod] = useState<'manual' | 'pix_key'>('manual')
  const [paymentPixKey, setPaymentPixKey] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    setAcceptedPaymentMethods(settings.accepted_payment_methods ?? [])
    setPaymentReceivingMethod(settings.payment_receiving_method ?? 'manual')
    setPaymentPixKey(settings.payment_pix_key ?? '')
  }, [settings])

  async function handleSubmit() {
    if (!settings) return
    setFormError(null)
    setSuccessMessage(null)
    setFieldErrors({})

    if (paymentReceivingMethod === 'pix_key' && !paymentPixKey.trim()) {
      setFieldErrors({
        payment_pix_key: ['Informe a chave Pix da empresa para receber diretamente por Pix.'],
      })
      return
    }

    setIsSubmitting(true)

    try {
      const updated = await tenantSettingsService.updateTenantSettings({
        storefront_enabled: settings.storefront_enabled,
        catalog_layout: settings.catalog_layout,
        accepted_payment_methods: acceptedPaymentMethods,
        payment_receiving_method: paymentReceivingMethod,
        payment_pix_key: paymentReceivingMethod === 'pix_key' ? paymentPixKey.trim() || null : null,
      })
      setSettings(updated)
      setSuccessMessage('Formas de pagamento salvas com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar as formas de pagamento agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
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
    return <Skeleton variant="rounded" height={180} />
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

      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1 }}>
        Exibidas no perfil público da bilheteria online para o cliente saber como pode pagar.
      </Typography>
      <FormGroup>
        {PAYMENT_METHODS.map((method) => (
          <FormControlLabel
            key={method}
            control={
              <Checkbox
                checked={acceptedPaymentMethods.includes(method)}
                onChange={(event) =>
                  setAcceptedPaymentMethods((current) =>
                    event.target.checked ? [...current, method] : current.filter((item) => item !== method),
                  )
                }
              />
            }
            label={PAYMENT_METHOD_SETTINGS_LABELS[method]}
          />
        ))}
      </FormGroup>

      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mt: 3, mb: 1 }}>
        Cadastre aqui os dados que a sua empresa precisa informar para integrar o recebimento fora da plataforma.
      </Typography>

      <Stack spacing={2} sx={{ maxWidth: 560 }}>
        <TextField
          select
          label="Recebimento da empresa"
          value={paymentReceivingMethod}
          onChange={(event) => setPaymentReceivingMethod(event.target.value as 'manual' | 'pix_key')}
          helperText="Escolha como sua empresa recebe pagamentos combinados fora do gateway da plataforma."
          fullWidth
        >
          <MenuItem value="manual">Combinado manualmente</MenuItem>
          <MenuItem value="pix_key">Por chave Pix da empresa</MenuItem>
        </TextField>

        {paymentReceivingMethod === 'pix_key' && (
          <TextField
            label="Chave Pix da empresa"
            placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"
            value={paymentPixKey}
            onChange={(event) => setPaymentPixKey(event.target.value)}
            error={Boolean(fieldErrors.payment_pix_key)}
            helperText={
              fieldErrors.payment_pix_key?.[0] ??
              'A chave fica protegida no backend e pode ser usada pelos fluxos que exibem o Pix direto da empresa.'
            }
            fullWidth
            slotProps={{ htmlInput: { maxLength: 140 } }}
          />
        )}
      </Stack>

      <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
        <Button variant="contained" disabled={isSubmitting} onClick={() => void handleSubmit()} sx={{ minWidth: 140 }}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </Stack>
    </>
  )
}
