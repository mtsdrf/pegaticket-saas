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
  const [paymentReceivingMethod, setPaymentReceivingMethod] = useState<'manual' | 'pix_key' | 'pagbank_token'>('manual')
  const [paymentPixKey, setPaymentPixKey] = useState('')
  const [pagbankEnvironment, setPagbankEnvironment] = useState<'sandbox' | 'production'>('sandbox')
  const [pagbankAccessToken, setPagbankAccessToken] = useState('')
  const [hasPagbankAccessToken, setHasPagbankAccessToken] = useState(false)
  const [pagbankReceiverAccountId, setPagbankReceiverAccountId] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    setAcceptedPaymentMethods(settings.accepted_payment_methods ?? [])
    setPaymentReceivingMethod(settings.payment_receiving_method ?? 'manual')
    setPaymentPixKey(settings.payment_pix_key ?? '')
    setPagbankEnvironment(settings.pagbank_environment ?? 'sandbox')
    setPagbankAccessToken('')
    setHasPagbankAccessToken(settings.has_pagbank_access_token ?? false)
    setPagbankReceiverAccountId(settings.pagbank_receiver_account_id ?? '')
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

    if (paymentReceivingMethod === 'pagbank_token' && !hasPagbankAccessToken && !pagbankAccessToken.trim()) {
      setFieldErrors({
        pagbank_access_token: ['Informe o token PagBank da empresa para habilitar o recebimento direto.'],
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
        pagbank_integration_mode: paymentReceivingMethod === 'pagbank_token' ? 'manual_token' : 'disabled',
        pagbank_environment: pagbankEnvironment,
        pagbank_access_token: paymentReceivingMethod === 'pagbank_token'
          ? (pagbankAccessToken.trim() || undefined)
          : null,
        pagbank_receiver_account_id: pagbankReceiverAccountId.trim() || null,
      })
      setSettings(updated)
      setPagbankAccessToken('')
      setHasPagbankAccessToken(updated.has_pagbank_access_token ?? false)
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
          onChange={(event) => setPaymentReceivingMethod(event.target.value as 'manual' | 'pix_key' | 'pagbank_token')}
          helperText="Escolha como sua empresa recebe pagamentos combinados fora do gateway da plataforma."
          fullWidth
        >
          <MenuItem value="manual">Combinado manualmente</MenuItem>
          <MenuItem value="pix_key">Por chave Pix da empresa</MenuItem>
          <MenuItem value="pagbank_token">PagBank direto na conta da empresa</MenuItem>
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

        {paymentReceivingMethod === 'pagbank_token' && (
          <>
            <Alert severity="info">
              O valor da venda será processado com a conta PagBank da própria empresa. Nesse modelo, o dinheiro não passa pela plataforma.
            </Alert>

            <TextField
              select
              label="Ambiente PagBank"
              value={pagbankEnvironment}
              onChange={(event) => setPagbankEnvironment(event.target.value as 'sandbox' | 'production')}
              helperText="Use sandbox para homologação e production quando a empresa já estiver pronta para operar."
              fullWidth
            >
              <MenuItem value="sandbox">Sandbox</MenuItem>
              <MenuItem value="production">Produção</MenuItem>
            </TextField>

            <TextField
              label="Conta recebedora PagBank"
              placeholder="Ex.: ACCO_12345678-1234-1234-1234-123456789012"
              value={pagbankReceiverAccountId}
              onChange={(event) => setPagbankReceiverAccountId(event.target.value)}
              error={Boolean(fieldErrors.pagbank_receiver_account_id)}
              helperText={
                fieldErrors.pagbank_receiver_account_id?.[0]
                ?? 'Conta PagBank da empresa usada no split com custódia quando a plataforma operar como recebedora primária.'
              }
              fullWidth
              slotProps={{ htmlInput: { maxLength: 80 } }}
            />

            <TextField
              label={hasPagbankAccessToken ? 'Novo token PagBank da empresa' : 'Token PagBank da empresa'}
              placeholder="Cole o token de autenticação da conta PagBank da empresa"
              value={pagbankAccessToken}
              onChange={(event) => setPagbankAccessToken(event.target.value)}
              error={Boolean(fieldErrors.pagbank_access_token)}
              helperText={
                fieldErrors.pagbank_access_token?.[0]
                ?? (hasPagbankAccessToken
                  ? 'Já existe um token salvo. Preencha este campo apenas se quiser substituí-lo.'
                  : 'O token será salvo de forma protegida no backend e usado nas cobranças da empresa.')
              }
              type="password"
              fullWidth
              slotProps={{ htmlInput: { maxLength: 4096 } }}
            />
          </>
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
